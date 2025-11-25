<?php

namespace SalnamaChat\Controllers\Frontend;

use SalnamaChat\Services\CustomerService;
use SalnamaChat\Services\ConversationService;
use SalnamaChat\Core\Constants;

/**
 * کنترلر مدیریت چت در فرانت‌اند
 */
class ChatController {
    
    private $customer_service;
    private $conversation_service;
    
    public function __construct(CustomerService $customer_service, ConversationService $conversation_service) {
        $this->customer_service = $customer_service;
        $this->conversation_service = $conversation_service;
    }
    
    /**
     * راه‌اندازی کنترلر
     */
    public function init(): void {
        // ثبت AJAX hooks
        add_action('wp_ajax_salnama_chat_send_message', [$this, 'ajax_send_message']);
        add_action('wp_ajax_nopriv_salnama_chat_send_message', [$this, 'ajax_send_message']);
        
        add_action('wp_ajax_salnama_chat_get_messages', [$this, 'ajax_get_messages']);
        add_action('wp_ajax_nopriv_salnama_chat_get_messages', [$this, 'ajax_get_messages']);
        
        add_action('wp_ajax_salnama_chat_start_conversation', [$this, 'ajax_start_conversation']);
        add_action('wp_ajax_nopriv_salnama_chat_start_conversation', [$this, 'ajax_start_conversation']);
        add_action('wp_footer', [$this, 'add_chat_widget']);
        // ثبت shortcodes
        add_shortcode('salnama_chat', [$this, 'chat_shortcode']);
        
        // بارگذاری assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * ارسال پیام via AJAX
     */
    public function ajax_send_message(): void {
        $this->verify_nonce();
        
        try {
            $conversation_id = (int)($_POST['conversation_id'] ?? 0);
            $message_content = sanitize_textarea_field($_POST['message'] ?? '');
            $attachment = $_FILES['attachment'] ?? [];
            
            if (empty($conversation_id)) {
                throw new \Exception('شناسه مکالمه نامعتبر');
            }
            
            if (empty($message_content) && empty($attachment)) {
                throw new \Exception('پیام یا فایل پیوست الزامی است');
            }
            
            // شناسایی مشتری
            $customer = $this->customer_service->identify_customer();
            
            // ارسال پیام
            $message = $this->conversation_service->send_message(
                $conversation_id,
                $customer['customer_id'],
                'customer',
                $message_content,
                $attachment
            );
            
            wp_send_json_success([
                'message' => $message,
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'success' => false
            ]);
        }
    }
    
    /**
     * دریافت پیام‌ها via AJAX
     */
    public function ajax_get_messages(): void {
        $this->verify_nonce();
        
        try {
            $conversation_id = (int)($_POST['conversation_id'] ?? 0);
            $page = (int)($_POST['page'] ?? 1);
            
            if (empty($conversation_id)) {
                throw new \Exception('شناسه مکالمه نامعتبر');
            }
            
            $data = $this->conversation_service->get_conversation_with_messages($conversation_id, $page);
            
            wp_send_json_success([
                'conversation' => $data['conversation'],
                'messages' => $data['messages'],
                'pagination' => $data['pagination']
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * شروع مکالمه جدید via AJAX
     */
    public function ajax_start_conversation(): void {
        $this->verify_nonce();
        
        try {
            $subject = sanitize_text_field($_POST['subject'] ?? 'مکالمه جدید');
            $initial_message = sanitize_textarea_field($_POST['message'] ?? '');
            $priority = sanitize_text_field($_POST['priority'] ?? Constants::PRIORITY_MEDIUM);
            
            // شناسایی مشتری
            $customer = $this->customer_service->identify_customer();
            
            // بررسی وجود مکالمه فعال
            if ($this->conversation_service->has_active_conversation($customer['customer_id'])) {
                throw new \Exception('شما در حال حاضر یک مکالمه فعال دارید');
            }
            
            // شروع مکالمه جدید
            $conversation = $this->conversation_service->start_conversation($customer['customer_id'], [
                'subject' => $subject,
                'priority' => $priority,
                'initial_message' => $initial_message
            ]);
            
            wp_send_json_success([
                'conversation' => $conversation,
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'success' => false
            ]);
        }
    }
    
    /**
     * بررسی nonce امنیتی
     */
    private function verify_nonce(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'salnama_chat_nonce')) {
            wp_send_json_error([
                'message' => 'خطای امنیتی: Nonce نامعتبر'
            ]);
        }
    }
    
    /**
     * shortcode نمایش چت
     */
    public function chat_shortcode($atts): string {
        $atts = shortcode_atts([
            'position' => 'bottom-right',
            'theme' => 'light',
            'autostart' => 'false'
        ], $atts);
        
        ob_start();
        ?>
        <div id="salnama-chat-widget" 
             data-position="<?php echo esc_attr($atts['position']); ?>"
             data-theme="<?php echo esc_attr($atts['theme']); ?>"
             data-autostart="<?php echo esc_attr($atts['autostart']); ?>">
            <!-- ویجت چت اینجا رندر می‌شود -->
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * بارگذاری assets
     */
    public function enqueue_assets(): void {
        // فقط در صفحات لازم بارگذاری شود
        if (!$this->should_load_assets()) {
            return;
        }
        
        // CSS
        
        wp_enqueue_style(
            'salnama-chat-widget',
            Constants::PLUGIN_URL . 'assets/css/frontend/chat-widget.css',
            [],
            Constants::VERSION
        );


        // JavaScript

        wp_enqueue_script(
            'salnama-chat-widget',
            Constants::PLUGIN_URL . 'assets/js/frontend/chat-widget.js',
            ['jquery'],
            Constants::VERSION,
            true
        );
    
        
        // Localize script
        $this->localize_script();
    }
    
    /**
     * بررسی آیا assets باید بارگذاری شوند
     */
    private function should_load_assets(): bool {
        // عدم بارگذاری در صفحات ادمین
        if (is_admin()) {
            return false;
        }
        
        // عدم بارگذاری در REST API
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        // امکان اضافه کردن شرط‌های دیگر
        $disabled_pages = ['checkout', 'cart']; // صفحاتی که چت نباید نمایش داده شود
        
        foreach ($disabled_pages as $page) {
            if (is_page($page)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * ارسال داده به JavaScript
     */
    private function localize_script(): void {
        $customer_data = [];
        
        try {
            $customer = $this->customer_service->identify_customer();
            $customer_data = [
                'id' => $customer['customer_id'],
                'name' => $customer['customer_name'] ?? 'مهمان',
                'email' => $customer['customer_email'] ?? '',
                'has_active_conversation' => $this->conversation_service->has_active_conversation($customer['customer_id'])
            ];
        } catch (\Exception $e) {
            error_log('Error localizing customer data: ' . $e->getMessage());
        }
        
        wp_localize_script('salnama-chat-frontend', 'salnamaChat', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('salnama_chat_nonce'),
            'customer' => $customer_data,
            'settings' => $this->get_chat_settings(),
            'i18n' => $this->get_translations(),
            'websocket' => [
                'enabled' => false, //暂时禁用，后续实现
                'url' => ''
            ]
        ]);
    }
    
    /**
     * دریافت تنظیمات چت
     */
    private function get_chat_settings(): array {
        $settings = get_option(Constants::OPTION_SETTINGS, []);
        
        return [
            'business_hours' => $settings['general']['business_hours'] ?? [],
            'offline_message' => $settings['general']['offline_message'] ?? '',
            'welcome_message' => $settings['appearance']['welcome_message'] ?? 'سلام! چطور می‌تونم کمکتون کنم؟',
            'theme' => $settings['appearance']['theme'] ?? 'light',
            'position' => $settings['appearance']['position'] ?? 'bottom-right'
        ];
    }

    // اضافه کردن ویجت به footer
    public function add_chat_widget() {
        if ($this->should_load_assets()) {
            include Constants::get_template_path('frontend/chat-widget');
        }
    }
    
    /**
     * دریافت متن‌های ترجمه
     */
    private function get_translations(): array {
        return [
            'welcome' => __('خوش آمدید!', 'salnama-chat'),
            'type_message' => __('پیام خود را بنویسید...', 'salnama-chat'),
            'send' => __('ارسال', 'salnama-chat'),
            'attach_file' => __('افزودن فایل', 'salnama-chat'),
            'start_chat' => __('شروع گفتگو', 'salnama-chat'),
            'online' => __('آنلاین', 'salnama-chat'),
            'offline' => __('آفلاین', 'salnama-chat'),
            'connecting' => __('در حال اتصال...', 'salnama-chat'),
            'no_messages' => __('هنوز پیامی ارسال نشده است', 'salnama-chat'),
            'file_too_large' => __('حجم فایل بیش از حد مجاز است', 'salnama-chat'),
            'invalid_file_type' => __('نوع فایل مجاز نیست', 'salnama-chat')
        ];
    }
}