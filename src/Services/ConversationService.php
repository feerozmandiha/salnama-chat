<?php

namespace SalnamaChat\Services;

use SalnamaChat\Models\Conversation;
use SalnamaChat\Models\Message;
use SalnamaChat\Models\Customer;
use SalnamaChat\Core\Constants;

/**
 * سرویس business logic برای مدیریت مکالمات
 */
class ConversationService {
    
    private $conversation_model;
    private $message_model;
    private $customer_model;
    
    public function __construct() {
        $this->conversation_model = new Conversation();
        $this->message_model = new Message();
        $this->customer_model = new Customer();
    }
    
    /**
     * شروع مکالمه جدید
     */
    public function start_conversation(int $customer_id, array $initial_data = []): array {
        try {
            // بررسی وجود مشتری
            $customer = $this->customer_model->get_by_id($customer_id);
            
            // ایجاد مکالمه
            $conversation_data = [
                'customer_id' => $customer_id,
                'subject' => $initial_data['subject'] ?? 'مکالمه جدید',
                'priority' => $initial_data['priority'] ?? Constants::PRIORITY_MEDIUM
            ];
            
            $conversation = $this->conversation_model->create($conversation_data);
            
            // ارسال پیام اولیه اگر وجود دارد
            if (!empty($initial_data['initial_message'])) {
                $this->send_message(
                    $conversation['conversation_id'],
                    $customer_id,
                    'customer',
                    $initial_data['initial_message']
                );
            }
            
            return $conversation;
            
        } catch (\Exception $e) {
            error_log('Start Conversation Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * ارسال پیام
     */
    public function send_message(int $conversation_id, int $sender_id, string $sender_type, string $content, array $attachment = []): array {
        // اعتبارسنجی sender_type
        if (!in_array($sender_type, ['customer', 'operator'])) {
            throw new \Exception('sender_type نامعتبر');
        }
        
        // آماده‌سازی داده‌های پیام
        $message_data = [
            'conversation_id' => $conversation_id,
            'sender_id' => $sender_id,
            'sender_type' => $sender_type,
            'message_content' => $content
        ];
        
        // مدیریت attachment اگر وجود دارد
        if (!empty($attachment)) {
            $message_data = array_merge($message_data, $this->handle_attachment($attachment));
        }
        
        // ارسال پیام
        $message = $this->message_model->create($message_data);
        
        // به روزرسانی وضعیت مکالمه اگر لازم باشد
        $this->update_conversation_status($conversation_id, $sender_type);
        
        return $message;
    }
    
    /**
     * مدیریت فایل پیوست
     */
    private function handle_attachment(array $attachment): array {
        $validation = $this->validate_file_upload($attachment);
        
        if (!$validation['valid']) {
            throw new \Exception(implode(', ', $validation['errors']));
        }
        
        // آپلود فایل
        $upload_result = $this->upload_file($attachment);
        
        return [
            'message_type' => $upload_result['type'],
            'attachment_url' => $upload_result['url'],
            'attachment_name' => $upload_result['name'],
            'attachment_size' => $upload_result['size']
        ];
    }
    
    /**
     * آپلود فایل
     */
    private function upload_file(array $file): array {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $upload = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($upload['error'])) {
            throw new \Exception('خطا در آپلود فایل: ' . $upload['error']);
        }
        
        // تعیین نوع پیام بر اساس mime type
        $mime_type = $file['type'];
        $message_type = Constants::MESSAGE_TYPE_FILE;
        
        if (strpos($mime_type, 'image/') === 0) {
            $message_type = Constants::MESSAGE_TYPE_IMAGE;
        }
        
        return [
            'url' => $upload['url'],
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $message_type
        ];
    }
    
    /**
     * اعتبارسنجی آپلود فایل
     */
    private function validate_file_upload(array $file): array {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->get_upload_error_message($file['error']);
        }
        
        // انواع فایل مجاز
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'نوع فایل مجاز نیست';
        }
        
        // حداکثر سایز فایل
        $max_size = Constants::FILE_MAX_SIZE;
        if ($file['size'] > $max_size) {
            $errors[] = 'حجم فایل بیش از حد مجاز است (حداکثر 10MB)';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * دریافت پیام خطای آپلود
     */
    private function get_upload_error_message(int $error_code): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم فایل بیش از حد مجاز سرور است',
            UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز فرم است',
            UPLOAD_ERR_PARTIAL => 'فایل به طور کامل آپلود نشد',
            UPLOAD_ERR_NO_FILE => 'هیچ فایلی آپلود نشد',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت یافت نشد',
            UPLOAD_ERR_CANT_WRITE => 'خطا در ذخیره فایل',
            UPLOAD_ERR_EXTENSION => 'افزونه PHP آپلود فایل را متوقف کرد'
        ];
        
        return $messages[$error_code] ?? 'خطای ناشناخته در آپلود فایل';
    }
    
    /**
     * به روزرسانی وضعیت مکالمه
     */
    private function update_conversation_status(int $conversation_id, string $sender_type): void {
        $conversation = $this->conversation_model->get_by_id($conversation_id);
        
        $new_status = $conversation['status'];
        
        if ($sender_type === 'customer' && $conversation['status'] === Constants::CONVERSATION_CLOSED) {
            // اگر مشتری به مکالمه بسته شده پیام داد، آن را باز کند
            $new_status = Constants::CONVERSATION_OPEN;
        } elseif ($sender_type === 'operator' && $conversation['status'] === Constants::CONVERSATION_OPEN) {
            // اگر اپراتور به مکالمه باز پاسخ داد، وضعیت را به pending تغییر دهد
            $new_status = Constants::CONVERSATION_PENDING;
        }
        
        if ($new_status !== $conversation['status']) {
            $this->conversation_model->update($conversation_id, [
                'status' => $new_status
            ]);
        }
    }
    
    /**
     * دریافت مکالمه به همراه پیام‌ها
     */
    public function get_conversation_with_messages(int $conversation_id, int $page = 1): array {
        $conversation = $this->conversation_model->get_by_id($conversation_id);
        $messages = $this->message_model->get_by_conversation($conversation_id, $page);
        
        return [
            'conversation' => $conversation,
            'messages' => $messages['messages'],
            'pagination' => $messages['pagination']
        ];
    }
    
    /**
     * اختصاص مکالمه به اپراتور
     */
    public function assign_conversation(int $conversation_id, int $operator_id): array {
        return $this->conversation_model->assign_to_operator($conversation_id, $operator_id);
    }
    
    /**
     * بستن مکالمه
     */
    public function close_conversation(int $conversation_id, int $operator_id = 0, string $resolution_notes = ''): array {
        $conversation = $this->conversation_model->close($conversation_id, $operator_id);
        
        // ذخیره یادداشت‌های حل مسئله اگر وجود دارد
        if (!empty($resolution_notes)) {
            $this->add_resolution_notes($conversation_id, $operator_id, $resolution_notes);
        }
        
        return $conversation;
    }
    
    /**
     * افزودن یادداشت حل مسئله
     */
    private function add_resolution_notes(int $conversation_id, int $operator_id, string $notes): void {
        $message_data = [
            'conversation_id' => $conversation_id,
            'sender_id' => $operator_id,
            'sender_type' => 'operator',
            'message_type' => Constants::MESSAGE_TYPE_SYSTEM,
            'message_content' => "یادداشت حل مسئله: " . $notes
        ];
        
        $this->message_model->create($message_data);
    }
    
    /**
     * دریافت مکالمات نیازمند پاسخ
     */
    public function get_pending_conversations(int $page = 1, int $per_page = 20): array {
        return $this->conversation_model->get_by_status(Constants::CONVERSATION_OPEN, $page, $per_page);
    }
    
    /**
     * دریافت مکالمات بر اساس وضعیت
     */
    public function get_conversations_by_status(string $status, int $page = 1, int $per_page = 20): array {
        return $this->conversation_model->get_by_status($status, $page, $per_page);
    }
    
    /**
     * دریافت آمار مکالمات
     */
    public function get_conversations_stats(array $filters = []): array {
        return $this->conversation_model->get_stats($filters);
    }
    
    /**
     * بررسی وجود مکالمات فعال برای مشتری
     */
    public function has_active_conversation(int $customer_id): bool {
        try {
            $active_conversations = $this->conversation_model->get_active_by_customer($customer_id);
            return !empty($active_conversations);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * علامت گذاری پیام‌های یک مکالمه به عنوان خوانده شده
     */
    public function mark_messages_as_read(int $conversation_id, string $sender_type): bool {
        return $this->message_model->mark_conversation_read($conversation_id, $sender_type);
    }
    
    /**
     * دریافت پیام‌های یک مکالمه (برای API)
     */
    public function get_conversation_messages(int $conversation_id, int $page = 1, int $per_page = 50): array {
        return $this->message_model->get_by_conversation($conversation_id, $page, $per_page);
    }
    
    /**
     * به روزرسانی مکالمه (برای API)
     */
    public function update_conversation(int $conversation_id, array $data): array {
        return $this->conversation_model->update($conversation_id, $data);
    }
    
    /**
     * دریافت مکالمه (برای API)
     */
    public function get_conversation(int $conversation_id): array {
        return $this->conversation_model->get_by_id($conversation_id);
    }
}