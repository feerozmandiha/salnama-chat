<?php

namespace SalnamaChat\Services;

use SalnamaChat\Core\Constants;
use SalnamaChat\Core\Database;
use SalnamaChat\Models\Conversation;
use SalnamaChat\Models\Customer;
use SalnamaChat\Models\Message;

/**
 * سرویس ارتباطی - نسخه کامل با قابلیت Polling
 */
class WebSocketService {
    
    private $is_websocket_enabled = false;
    private $conversation_service;
    
    public function __construct() {
        // WebSocket غیرفعال - از polling استفاده می‌کنیم
        $this->conversation_service = new ConversationService();
    }
    
    /**
     * راه‌اندازی سرویس
     */
    public function init(): void {
        // ثبت هوک‌های AJAX برای polling
        add_action('wp_ajax_salnama_chat_poll_messages', [$this, 'ajax_poll_messages']);
        add_action('wp_ajax_nopriv_salnama_chat_poll_messages', [$this, 'ajax_poll_messages']);
    }
    
    /**
     * Polling برای دریافت پیام‌های جدید
     */
    public function ajax_poll_messages(): void {
        $this->verify_nonce();
        
        $conversation_id = (int)($_POST['conversation_id'] ?? 0);
        $last_message_id = (int)($_POST['last_message_id'] ?? 0);
        
        if (empty($conversation_id)) {
            wp_send_json_error(['message' => 'شناسه مکالمه نامعتبر است']);
        }
        
        try {
            $messages = $this->get_new_messages($conversation_id, $last_message_id);
            $new_last_message_id = $this->get_last_message_id($conversation_id);
            
            error_log("📤 Polling Response - Conversation: {$conversation_id}, Last ID: {$last_message_id}, New Messages: " . count($messages) . ", New Last ID: {$new_last_message_id}");
            
            wp_send_json_success([
                'messages' => $messages,
                'last_message_id' => $new_last_message_id,
                'has_new_messages' => !empty($messages),
                'conversation_id' => $conversation_id
            ]);
            
        } catch (\Exception $e) {
            error_log('❌ Poll messages error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * دریافت پیام‌های جدید
     */
    private function get_new_messages(int $conversation_id, int $last_message_id): array {
        $db = Database::getInstance();
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        
        // ابتدا بررسی کن مکالمه وجود دارد
        if (!$this->conversation_exists($conversation_id)) {
            throw new \Exception('مکالمه یافت نشد');
        }
        
        if ($last_message_id === 0) {
            // اگر اولین بار است، آخرین ۱۰ پیام را برگردان
            $sql = "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY message_id DESC LIMIT 10";
            $messages = $db->get_results($sql, [$conversation_id]);
            $messages = array_reverse($messages); // به ترتیب زمانی
        } else {
            // پیام‌های جدیدتر از last_message_id را بگیر
            $sql = "SELECT * FROM {$table} WHERE conversation_id = %d AND message_id > %d ORDER BY message_id ASC";
            $messages = $db->get_results($sql, [$conversation_id, $last_message_id]);
        }
        
        // فرمت کردن داده‌های پیام
        $formatted_messages = [];
        foreach ($messages as $message) {
            $formatted_messages[] = $this->format_message_data($message);
        }
        
        error_log("📨 Getting messages for conversation {$conversation_id}, last_message_id: {$last_message_id}, found: " . count($formatted_messages));
        
        return $formatted_messages;
    }
    
    /**
     * بررسی وجود مکالمه
     */
    private function conversation_exists(int $conversation_id): bool {
        $db = Database::getInstance();
        $table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE conversation_id = %d";
        $result = $db->get_row($sql, [$conversation_id]);
        
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * دریافت آخرین message_id یک مکالمه
     */
    private function get_last_message_id(int $conversation_id): int {
        $db = Database::getInstance();
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        
        $sql = "SELECT MAX(message_id) as last_id FROM {$table} WHERE conversation_id = %d";
        $result = $db->get_row($sql, [$conversation_id]);
        
        $last_id = (int)($result['last_id'] ?? 0);
        
        return $last_id;
    }
    
    /**
     * فرمت داده‌های پیام
     */
    private function format_message_data(array $message): array {
        return [
            'message_id' => (int)($message['message_id'] ?? 0),
            'conversation_id' => (int)($message['conversation_id'] ?? 0),
            'sender_type' => $message['sender_type'] ?? 'customer',
            'sender_id' => (int)($message['sender_id'] ?? 0),
            'message_type' => $message['message_type'] ?? 'text',
            'message_content' => $message['message_content'] ?? '',
            'attachment_url' => $message['attachment_url'] ?? null,
            'attachment_name' => $message['attachment_name'] ?? null,
            'read_status' => (bool)($message['read_status'] ?? false),
            'sent_at' => $message['sent_at'] ?? current_time('mysql'),
            'sent_at_timestamp' => strtotime($message['sent_at'] ?? current_time('mysql'))
        ];
    }
    
    /**
     * بررسی nonce
     */
    private function verify_nonce(): void {
        $nonce = $_POST['nonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'salnama_chat_nonce')) {
            wp_send_json_error([
                'message' => 'خطای امنیتی: Nonce نامعتبر'
            ]);
        }
    }
    
    /**
     * بررسی فعال بودن WebSocket
     */
    public function is_enabled(): bool {
        return $this->is_websocket_enabled;
    }
    
    /**
     * ارسال پیام به کاربر (برای سازگاری)
     */
    public function send_to_user(int $user_id, string $user_type, array $message): bool {
        // در این نسخه از polling استفاده می‌کنیم، بنابراین این متد کاری انجام نمی‌دهد
        // پیام‌ها از طریق polling دریافت می‌شوند
        return true;
    }
    
    /**
     * شروع سرور WebSocket (غیرفعال)
     */
    public function start_websocket_server(): void {
        // غیرفعال در این نسخه
        // برای فعال کردن نیاز به نصب کتابخانه Ratchet داریم
    }
    
    /**
     * دریافت لیست کاربران آنلاین (غیرفعال)
     */
    public function get_online_users(int $conversation_id): array {
        // در این نسخه غیرفعال است
        return [];
    }
    
    /**
     * ارسال اعلان به کاربر
     */
    public function send_notification(int $user_id, string $user_type, string $title, string $message): bool {
        // در این نسخه ساده، فقط لاگ می‌کنیم
        error_log("📢 Notification for {$user_type} {$user_id}: {$title} - {$message}");
        return true;
    }
    
    /**
     * به روزرسانی وضعیت آنلاین کاربر
     */
    public function update_user_status(int $user_id, string $user_type, bool $is_online): bool {
        error_log("🔵 Status update: {$user_type} {$user_id} is " . ($is_online ? 'online' : 'offline'));
        return true;
    }
    
    /**
     * دریافت آمار استفاده
     */
    public function get_usage_stats(): array {
        return [
            'websocket_enabled' => $this->is_websocket_enabled,
            'polling_enabled' => true,
            'active_connections' => 0,
            'total_messages' => $this->get_total_messages_count(),
            'active_conversations' => $this->get_active_conversations_count()
        ];
    }
    
    /**
     * دریافت تعداد کل پیام‌ها
     */
    private function get_total_messages_count(): int {
        $db = Database::getInstance();
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        $result = $db->get_row($sql);
        
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * دریافت تعداد مکالمات فعال
     */
    private function get_active_conversations_count(): int {
        $db = Database::getInstance();
        $table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        
        $sql = "SELECT COUNT(*) as total FROM {$table} WHERE status IN ('open', 'pending')";
        $result = $db->get_row($sql);
        
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * پاکسازی پیام‌های قدیمی
     */
    public function cleanup_old_messages(int $days_old = 30): int {
        $db = Database::getInstance();
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $sql = "DELETE FROM {$table} WHERE sent_at < %s";
        $result = $db->query($sql, [$cutoff_date]);
        
        $deleted_count = $result ? $db->db->rows_affected : 0;
        error_log("🧹 Cleaned up {$deleted_count} messages older than {$days_old} days");
        
        return $deleted_count;
    }
}