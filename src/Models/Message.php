<?php

namespace SalnamaChat\Models;

use SalnamaChat\Core\Constants;
use SalnamaChat\Core\Database;
use SalnamaChat\Traits\Validator;

/**
 * مدل مدیریت پیام‌ها
 */
class Message {
    
    use Validator;
    
    private $db;
    private $fields = [
        'message_id', 'conversation_id', 'sender_type', 'sender_id', 'message_type',
        'message_content', 'attachment_url', 'attachment_name', 'attachment_size',
        'read_status', 'delivered_status', 'sent_at', 'read_at'
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * ارسال پیام جدید
     */
    public function create(array $data): array {
        $this->db->begin_transaction();
        
        try {
            // اعتبارسنجی داده‌ها
            $this->validate_message_data($data);
            
            // آماده‌سازی داده‌ها
            $message_data = $this->prepare_message_data($data);
            
            // ایجاد پیام
            $message_id = $this->db->insert(Constants::TABLE_MESSAGES, $message_data);
            
            if (!$message_id) {
                throw new \Exception('خطا در ارسال پیام');
            }
            
            // به روزرسانی زمان مکالمه
            $this->update_conversation_timestamp($data['conversation_id']);
            
            $this->db->commit();
            
            return $this->get_by_id($message_id);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * دریافت پیام بر اساس ID
     */
    public function get_by_id(int $message_id): array {
        $this->validate_message_id($message_id);
        
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        $sql = "SELECT * FROM {$table} WHERE message_id = %d";
        
        $message = $this->db->get_row($sql, [$message_id]);
        
        if (!$message) {
            throw new \Exception('پیام یافت نشد');
        }
        
        return $this->format_message_data($message);
    }
    
    /**
     * دریافت پیام‌های یک مکالمه
     */
    public function get_by_conversation(int $conversation_id, int $page = 1, int $per_page = 50): array {
        $this->validate_conversation_id($conversation_id);
        
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        $offset = ($page - 1) * $per_page;
        
        $sql = "
            SELECT * FROM {$table} 
            WHERE conversation_id = %d 
            ORDER BY sent_at ASC 
            LIMIT %d, %d
        ";
        
        $messages = $this->db->get_results($sql, [$conversation_id, $offset, $per_page]);
        
        // تعداد کل پیام‌ها
        $count_sql = "SELECT COUNT(*) as total FROM {$table} WHERE conversation_id = %d";
        $total = $this->db->get_row($count_sql, [$conversation_id])['total'] ?? 0;
        
        return [
            'messages' => array_map([$this, 'format_message_data'], $messages),
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int)$total,
                'total_pages' => ceil($total / $per_page)
            ]
        ];
    }
    
    /**
     * علامت گذاری پیام به عنوان خوانده شده
     */
    public function mark_as_read(int $message_id): bool {
        $this->validate_message_id($message_id);
        
        $data = [
            'read_status' => 1,
            'read_at' => current_time('mysql')
        ];
        
        $where = ['message_id' => $message_id];
        
        return $this->db->update(Constants::TABLE_MESSAGES, $data, $where);
    }
    
    /**
     * علامت گذاری پیام‌های یک مکالمه به عنوان خوانده شده
     */
    public function mark_conversation_read(int $conversation_id, string $sender_type): bool {
        $this->validate_conversation_id($conversation_id);
        
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        $sql = "
            UPDATE {$table} 
            SET read_status = 1, read_at = %s 
            WHERE conversation_id = %d AND sender_type != %s AND read_status = 0
        ";
        
        return (bool)$this->db->query($sql, [
            current_time('mysql'),
            $conversation_id,
            $sender_type
        ]);
    }
    
    /**
     * دریافت تعداد پیام‌های خوانده نشده
     */
    public function get_unread_count(int $conversation_id, string $sender_type): int {
        $this->validate_conversation_id($conversation_id);
        
        $table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$table} 
            WHERE conversation_id = %d AND sender_type != %s AND read_status = 0
        ";
        
        $result = $this->db->get_row($sql, [$conversation_id, $sender_type]);
        
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * حذف پیام
     */
    public function delete(int $message_id): bool {
        $this->validate_message_id($message_id);
        
        $where = ['message_id' => $message_id];
        return $this->db->delete(Constants::TABLE_MESSAGES, $where);
    }
    
    /**
     * به روزرسانی timestamp مکالمه
     */
    private function update_conversation_timestamp(int $conversation_id): void {
        $conversation_model = new Conversation();
        $conversation_model->update($conversation_id, [
            'updated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * آماده‌سازی داده‌های پیام
     */
    private function prepare_message_data(array $data, bool $is_update = false): array {
        $prepared_data = [];
        
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $data)) {
                $prepared_data[$field] = $data[$field];
            } elseif (!$is_update) {
                $prepared_data[$field] = $this->get_default_value($field);
            }
        }
        
        return $prepared_data;
    }
    
    /**
     * دریافت مقدار پیش‌فرض برای فیلد
     */
    private function get_default_value(string $field) {
        $defaults = [
            'message_type' => Constants::MESSAGE_TYPE_TEXT,
            'read_status' => 0,
            'delivered_status' => 0,
            'attachment_url' => null,
            'attachment_name' => null,
            'attachment_size' => null,
            'read_at' => null
        ];
        
        return $defaults[$field] ?? null;
    }
    
    /**
     * فرمت داده‌های پیام
     */
    private function format_message_data(array $message): array {
        // تبدیل تاریخ‌ها به timestamp
        $date_fields = ['sent_at', 'read_at'];
        foreach ($date_fields as $field) {
            if (isset($message[$field])) {
                $message[$field . '_timestamp'] = strtotime($message[$field]);
            }
        }
        
        return $message;
    }
    
    /**
     * اعتبارسنجی داده‌های پیام
     */
    private function validate_message_data(array $data): void {
        if (empty($data['conversation_id'])) {
            throw new \Exception('conversation_id الزامی است');
        }
        
        if (empty($data['sender_type']) || !in_array($data['sender_type'], ['customer', 'operator'])) {
            throw new \Exception('sender_type نامعتبر');
        }
        
        if (empty($data['sender_id'])) {
            throw new \Exception('sender_id الزامی است');
        }
        
        if (empty($data['message_content']) && empty($data['attachment_url'])) {
            throw new \Exception('پیام یا فایل پیوست الزامی است');
        }
        
        if (!empty($data['message_content']) && !$this->validate_length($data['message_content'], 1, Constants::MESSAGE_MAX_LENGTH)) {
            throw new \Exception('طول پیام نامعتبر است');
        }
        
        if (!empty($data['message_type']) && !Constants::is_valid_message_type($data['message_type'])) {
            throw new \Exception('نوع پیام نامعتبر');
        }
    }
    
    private function validate_message_id(int $message_id): void {
        if ($message_id <= 0) {
            throw new \Exception('message_id نامعتبر');
        }
    }
    
    private function validate_conversation_id(int $conversation_id): void {
        if ($conversation_id <= 0) {
            throw new \Exception('conversation_id نامعتبر');
        }
    }
}