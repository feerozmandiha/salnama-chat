<?php

namespace SalnamaChat\Controllers\Admin;

use SalnamaChat\Services\ConversationService;
use SalnamaChat\Services\CustomerService;
use SalnamaChat\Core\Constants;

/**
 * کنترلر مدیریت پنل ادمین
 */
class AdminController {
    
    private $conversation_service;
    private $customer_service;
    private $page_hook;
    
    public function __construct(ConversationService $conversation_service, CustomerService $customer_service) {
        $this->conversation_service = $conversation_service;
        $this->customer_service = $customer_service;
    }
    
    /**
     * راه‌اندازی کنترلر
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX hooks برای ادمین
        add_action('wp_ajax_salnama_chat_admin_send_message', [$this, 'ajax_admin_send_message']);
        add_action('wp_ajax_salnama_chat_admin_get_conversations', [$this, 'ajax_admin_get_conversations']);
        add_action('wp_ajax_salnama_chat_admin_get_messages', [$this, 'ajax_admin_get_messages']);
        add_action('wp_ajax_salnama_chat_admin_assign_conversation', [$this, 'ajax_admin_assign_conversation']);
        add_action('wp_ajax_salnama_chat_admin_close_conversation', [$this, 'ajax_admin_close_conversation']);
        add_action('wp_ajax_salnama_chat_admin_get_stats', [$this, 'ajax_admin_get_stats']);
    }
    
    /**
     * افزودن منو به پنل ادمین
     */
    public function add_admin_menu(): void {
        $this->page_hook = add_menu_page(
            __('چت سالنمای نو', 'salnama-chat'),
            __('چت سالنمای نو', 'salnama-chat'),
            Constants::REQUIRED_WP_CAPABILITY,
            'salnama-chat',
            [$this, 'render_dashboard'],
            'dashicons-format-chat',
            25
        );
        
        // زیرمنوها
        add_submenu_page(
            'salnama-chat',
            __('داشبورد', 'salnama-chat'),
            __('داشبورد', 'salnama-chat'),
            Constants::REQUIRED_WP_CAPABILITY,
            'salnama-chat',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'salnama-chat',
            __('مکالمات', 'salnama-chat'),
            __('مکالمات', 'salnama-chat'),
            'salnama_chat_manage_conversations',
            'salnama-chat-conversations',
            [$this, 'render_conversations']
        );
        
        add_submenu_page(
            'salnama-chat',
            __('مشتریان', 'salnama-chat'),
            __('مشتریان', 'salnama-chat'),
            'salnama_chat_view_customers',
            'salnama-chat-customers',
            [$this, 'render_customers']
        );
        
        add_submenu_page(
            'salnama-chat',
            __('گزارشات', 'salnama-chat'),
            __('گزارشات', 'salnama-chat'),
            'salnama_chat_view_reports',
            'salnama-chat-reports',
            [$this, 'render_reports']
        );
        
        add_submenu_page(
            'salnama-chat',
            __('تنظیمات', 'salnama-chat'),
            __('تنظیمات', 'salnama-chat'),
            'salnama_chat_manage_settings',
            'salnama-chat-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * مقداردهی اولیه ادمین
     */
    public function admin_init(): void {
        register_setting('salnama_chat_settings', Constants::OPTION_SETTINGS);
        
        // اضافه کردن بخش‌های تنظیمات
        $this->register_settings_sections();
    }
    
    /**
     * ثبت بخش‌های تنظیمات
     */
    private function register_settings_sections(): void {
        // بخش عمومی
        add_settings_section(
            'salnama_chat_general',
            __('تنظیمات عمومی', 'salnama-chat'),
            [$this, 'render_general_section'],
            'salnama-chat-settings'
        );
        
        // بخش ظاهر
        add_settings_section(
            'salnama_chat_appearance',
            __('تنظیمات ظاهر', 'salnama-chat'),
            [$this, 'render_appearance_section'],
            'salnama-chat-settings'
        );
        
        // بخش اعلان‌ها
        add_settings_section(
            'salnama_chat_notifications',
            __('تنظیمات اعلان‌ها', 'salnama-chat'),
            [$this, 'render_notifications_section'],
            'salnama-chat-settings'
        );
    }
    
    /**
     * رندر داشبورد
     */
    public function render_dashboard(): void {
        if (!current_user_can(Constants::REQUIRED_WP_CAPABILITY)) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'salnama-chat'));
        }
        
        $stats = $this->get_dashboard_stats();
        $recent_conversations = $this->conversation_service->get_pending_conversations(1, 10);
        
        include Constants::get_template_path('admin/dashboard');
    }
    
    /**
     * رندر صفحه مکالمات
     */
    public function render_conversations(): void {
        if (!current_user_can('salnama_chat_manage_conversations')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'salnama-chat'));
        }
        
        $status = $_GET['status'] ?? 'open';
        $page = (int)($_GET['paged'] ?? 1);
        
        $conversations_data = $this->conversation_service->get_conversations_by_status($status, $page);
        
        include Constants::get_template_path('admin/conversations');
    }
    
    /**
     * رندر صفحه مشتریان
     */
    public function render_customers(): void {
        if (!current_user_can('salnama_chat_view_customers')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'salnama-chat'));
        }
        
        $search = sanitize_text_field($_GET['s'] ?? '');
        $page = (int)($_GET['paged'] ?? 1);
        
        $filters = [];
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        $customers_data = $this->customer_service->search_customers($filters, $page);
        
        include Constants::get_template_path('admin/customers');
    }
    
    /**
     * رندر صفحه گزارشات
     */
    public function render_reports(): void {
        if (!current_user_can('salnama_chat_view_reports')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'salnama-chat'));
        }
        
        $date_from = sanitize_text_field($_GET['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_GET['date_to'] ?? date('Y-m-d'));
        
        $filters = [
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
        
        $conversation_stats = $this->conversation_service->get_conversations_stats($filters);
        $customer_stats = $this->customer_service->get_customers_stats();
        
        include Constants::get_template_path('admin/reports');
    }
    
    /**
     * رندر صفحه تنظیمات
     */
    public function render_settings(): void {
        if (!current_user_can('salnama_chat_manage_settings')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'salnama-chat'));
        }
        
        $settings = get_option(Constants::OPTION_SETTINGS, []);
        
        include Constants::get_template_path('admin/settings');
    }
    
    /**
     * دریافت آمار داشبورد
     */
    private function get_dashboard_stats(): array {
        $conversation_stats = $this->conversation_service->get_conversations_stats();
        $customer_stats = $this->customer_service->get_customers_stats();
        
        return [
            'total_conversations' => $conversation_stats['total_conversations'],
            'open_conversations' => $conversation_stats['open_conversations'],
            'pending_conversations' => $conversation_stats['pending_conversations'],
            'resolved_conversations' => $conversation_stats['resolved_conversations'],
            'total_customers' => $customer_stats['total_customers'],
            'active_customers' => $customer_stats['active_customers'],
            'avg_resolution_time' => $conversation_stats['avg_resolution_time']
        ];
    }
    
    /**
     * بارگذاری assets ادمین
     */
    public function enqueue_admin_assets($hook): void {
        // فقط در صفحات مربوط به پلاگین بارگذاری شود
        if (strpos($hook, 'salnama-chat') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'salnama-chat-admin',
            Constants::get_asset_url('css/admin/admin.css'),
            [],
            Constants::VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'salnama-chat-admin',
            Constants::get_asset_url('js/admin/admin.js'),
            ['jquery', 'wp-util'],
            Constants::VERSION,
            true
        );
        
        // Chart.js برای گزارشات
        if ($hook === 'salnama-chat_page_salnama-chat-reports') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '3.9.1',
                true
            );
        }
        
        // Localize script
        $this->localize_admin_script();
    }
    
    /**
     * ارسال داده به JavaScript ادمین
     */
    private function localize_admin_script(): void {
        $current_user = wp_get_current_user();
        
        wp_localize_script('salnama-chat-admin', 'salnamaChatAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('salnama_chat_admin_nonce'),
            'current_user' => [
                'id' => $current_user->ID,
                'name' => $current_user->display_name,
                'avatar' => get_avatar_url($current_user->ID)
            ],
            'i18n' => $this->get_admin_translations(),
            'websocket' => [
                'enabled' => true,
                'url' => $this->get_websocket_url()
            ]
        ]);
    }
    
    /**
     * دریافت URL WebSocket
     */
    private function get_websocket_url(): string {
        $settings = get_option(Constants::OPTION_SETTINGS, []);
        $ws_host = $settings['websocket']['host'] ?? Constants::WS_HOST;
        $ws_port = $settings['websocket']['port'] ?? Constants::WS_PORT;
        
        return "ws://{$ws_host}:{$ws_port}";
    }
    
    /**
     * دریافت متن‌های ترجمه ادمین
     */
    private function get_admin_translations(): array {
        return [
            'send' => __('ارسال', 'salnama-chat'),
            'type_message' => __('پیام خود را بنویسید...', 'salnama-chat'),
            'assign' => __('اختصاص', 'salnama-chat'),
            'close' => __('بستن', 'salnama-chat'),
            'resolve' => __('حل شده', 'salnama-chat'),
            'online' => __('آنلاین', 'salnama-chat'),
            'offline' => __('آفلاین', 'salnama-chat'),
            'typing' => __('در حال نوشتن...', 'salnama-chat'),
            'new_message' => __('پیام جدید', 'salnama-chat'),
            'no_conversations' => __('مکالمه‌ای یافت نشد', 'salnama-chat'),
            'confirm_close' => __('آیا از بستن این مکالمه اطمینان دارید؟', 'salnama-chat')
        ];
    }
    
    /**
     * AJAX - ارسال پیام از ادمین
     */
    public function ajax_admin_send_message(): void {
        $this->verify_admin_nonce();
        
        try {
            $conversation_id = (int)($_POST['conversation_id'] ?? 0);
            $message_content = sanitize_textarea_field($_POST['message'] ?? '');
            $operator_id = get_current_user_id();
            
            if (empty($conversation_id)) {
                throw new \Exception('شناسه مکالمه نامعتبر');
            }
            
            if (empty($message_content)) {
                throw new \Exception('متن پیام الزامی است');
            }
            
            $message = $this->conversation_service->send_message(
                $conversation_id,
                $operator_id,
                'operator',
                $message_content
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
     * AJAX - دریافت مکالمات
     */
    public function ajax_admin_get_conversations(): void {
        $this->verify_admin_nonce();
        
        try {
            $status = sanitize_text_field($_POST['status'] ?? 'open');
            $page = (int)($_POST['page'] ?? 1);
            
            $conversations_data = $this->conversation_service->get_conversations_by_status($status, $page);
            
            wp_send_json_success($conversations_data);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * AJAX - دریافت پیام‌های یک مکالمه
     */
    public function ajax_admin_get_messages(): void {
        $this->verify_admin_nonce();
        
        try {
            $conversation_id = (int)($_POST['conversation_id'] ?? 0);
            $page = (int)($_POST['page'] ?? 1);
            
            if (empty($conversation_id)) {
                throw new \Exception('شناسه مکالمه نامعتبر');
            }
            
            $data = $this->conversation_service->get_conversation_with_messages($conversation_id, $page);
            
            // علامت گذاری پیام‌ها به عنوان خوانده شده
            $this->conversation_service->mark_messages_as_read($conversation_id, 'operator');
            
            wp_send_json_success($data);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * AJAX - اختصاص مکالمه به اپراتور
     */
    public function ajax_admin_assign_conversation(): void {
        $this->verify_admin_nonce();
        
        try {
            $conversation_id = (int)($_POST['conversation_id'] ?? 0);
            $operator_id = (int)($_POST['operator_id'] ?? get_current_user_id());
            
            if (empty($conversation_id)) {
                throw new \Exception('شناسه مکالمه نامعتبر');
            }
            
            $conversation = $this->conversation_service->assign_conversation($conversation_id, $operator_id);
            
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
     * AJAX - بستن مکالمه
     */
    public function ajax_admin_close_conversation(): void {
        $this->verify_admin_nonce();
        
        try {
            $conversation_id = (int)($_POST['conversation_id'] ?? 0);
            $resolution_notes = sanitize_textarea_field($_POST['resolution_notes'] ?? '');
            $operator_id = get_current_user_id();
            
            if (empty($conversation_id)) {
                throw new \Exception('شناسه مکالمه نامعتبر');
            }
            
            $conversation = $this->conversation_service->close_conversation(
                $conversation_id, 
                $operator_id, 
                $resolution_notes
            );
            
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
     * AJAX - دریافت آمار
     */
    public function ajax_admin_get_stats(): void {
        $this->verify_admin_nonce();
        
        try {
            $date_from = sanitize_text_field($_POST['date_from'] ?? '');
            $date_to = sanitize_text_field($_POST['date_to'] ?? '');
            
            $filters = [];
            if (!empty($date_from)) {
                $filters['date_from'] = $date_from;
            }
            if (!empty($date_to)) {
                $filters['date_to'] = $date_to;
            }
            
            $conversation_stats = $this->conversation_service->get_conversations_stats($filters);
            $customer_stats = $this->customer_service->get_customers_stats();
            
            wp_send_json_success([
                'conversation_stats' => $conversation_stats,
                'customer_stats' => $customer_stats
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * بررسی nonce ادمین
     */
    private function verify_admin_nonce(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'salnama_chat_admin_nonce')) {
            wp_send_json_error([
                'message' => 'خطای امنیتی: Nonce نامعتبر'
            ]);
        }
    }
    
    // متدهای رندر بخش‌های تنظیمات
    public function render_general_section(): void {
        echo '<p>' . __('تنظیمات عمومی سیستم چت', 'salnama-chat') . '</p>';
    }
    
    public function render_appearance_section(): void {
        echo '<p>' . __('تنظیمات ظاهر و نمایش چت', 'salnama-chat') . '</p>';
    }
    
    public function render_notifications_section(): void {
        echo '<p>' . __('تنظیمات اعلان‌ها و صداها', 'salnama-chat') . '</p>';
    }
}