<?php

namespace SalnamaChat\Core;

use SalnamaChat\Controllers\Admin\AdminController;
use SalnamaChat\Controllers\Frontend\ChatController;
use SalnamaChat\Api\RestApi;
use SalnamaChat\Services\WebSocketService;
use SalnamaChat\Services\CustomerService;
use SalnamaChat\Services\ConversationService;

/**
 * کلاس اصلی مدیریت پلاگین (Singleton)
 */
class Application {
    
    use Traits\Singleton;
    
    /**
     * @var array نمونه‌های سرویس‌ها
     */
    private $services = [];
    
    /**
     * @var array نمونه‌های کنترلرها
     */
    private $controllers = [];
    
    /**
     * راه‌اندازی پلاگین
     */
    public function init(): void {
        try {
            // بررسی dependencies
            $this->checkDependencies();
            
            // بارگذاری متن‌ها
            add_action('init', [$this, 'loadTextdomain']);
            
            // مقداردهی سرویس‌ها
            $this->initServices();
            
            // مقداردهی کنترلرها
            $this->initControllers();
            
            // ثبت هوک‌های وردپرس
            $this->registerHooks();
            
            // راه‌اندازی API
            $this->initApi();
            
        } catch (\Exception $e) {
            $this->handleError('خطا در راه‌اندازی پلاگین', $e);
        }
    }
    
    /**
     * بررسی dependencies
     */
    private function checkDependencies(): void {
        if (!class_exists('WP_REST_Server')) {
            throw new \Exception('REST API وردپرس فعال نیست.');
        }
        
        // بررسی extensionهای لازم PHP
        $required_extensions = ['json', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new \Exception("Extension PHP {$ext} مورد نیاز است.");
            }
        }
    }
    
    /**
     * مقداردهی سرویس‌ها
     */
    private function initServices(): void {
        $this->services = [
            'websocket' => new WebSocketService(),
            'customer' => new CustomerService(),
            'conversation' => new ConversationService(),
        ];
        
        // راه‌اندازی سرویس‌ها
        foreach ($this->services as $service) {
            if (method_exists($service, 'init')) {
                $service->init();
            }
        }
    }
    
    /**
     * مقداردهی کنترلرها
     */
    private function initControllers(): void {
        $this->controllers = [
            'admin' => new AdminController(
                $this->services['conversation'],
                $this->services['customer']
            ),
            'chat' => new ChatController(
                $this->services['customer'],
                $this->services['conversation']
            ),
        ];
        
        // راه‌اندازی کنترلرها
        foreach ($this->controllers as $controller) {
            if (method_exists($controller, 'init')) {
                $controller->init();
            }
        }
    }
    
    /**
     * راه‌اندازی REST API
     */
    private function initApi(): void {
        $restApi = new RestApi(
            $this->services['conversation'],
            $this->services['customer']
        );
        $restApi->init();
    }
    
    /**
     * ثبت هوک‌های وردپرس
     */
    private function registerHooks(): void {
        // اسکریپت‌ها و استایل‌ها
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // هوک‌های پاکسازی
        add_action('wp_scheduled_delete', [$this, 'cleanupOldData']);
    }
    
    /**
     * بارگذاری فایل‌های ترجمه
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            Constants::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(Constants::PLUGIN_FILE)) . '/languages'
        );
    }
    
    /**
     * بارگذاری اسکریپت‌های فرانت‌اند
     */
    public function enqueueFrontendAssets(): void {
        // فقط در صفحات لازم بارگذاری شود
        if (!$this->shouldLoadFrontendAssets()) {
            return;
        }
        
        wp_enqueue_style(
            'salmama-chat-widget',
            Constants::PLUGIN_URL . 'assets/css/frontend/chat-widget.css',
            [],
            Constants::VERSION
        );
        
        wp_enqueue_script(
            'salmama-chat-widget',
            Constants::PLUGIN_URL . 'assets/js/frontend/chat-widget.js',
            ['jquery'],
            Constants::VERSION,
            true
        );
        
        // Localize script data
        $this->localizeScriptData();
    }
    
    /**
     * بارگذاری اسکریپت‌های ادمین
     */
    public function enqueueAdminAssets($hook): void {
        // فقط در صفحات مربوط به پلاگین بارگذاری شود
        if (strpos($hook, 'salmama-chat') === false) {
            return;
        }
        
        wp_enqueue_style(
            'salmama-chat-admin',
            Constants::PLUGIN_URL . 'assets/css/admin/admin.css',
            [],
            Constants::VERSION
        );
        
        wp_enqueue_script(
            'salmama-chat-admin',
            Constants::PLUGIN_URL . 'assets/js/admin/admin.js',
            ['jquery', 'wp-util'],
            Constants::VERSION,
            true
        );
    }
    
    /**
     * مدیریت خطاها
     */
    private function handleError(string $message, \Exception $e): void {
        // لاگ کردن خطا
        error_log("Salmama Chat Error: {$message} - {$e->getMessage()}");
        
        // نمایش خطا در حالت دیباگ
        if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
            add_action('admin_notices', function() use ($message, $e) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Salmama Chat:</strong> 
                        <?php echo esc_html($message); ?>
                        <br>
                        <small><?php echo esc_html($e->getMessage()); ?></small>
                    </p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * دریافت یک سرویس
     */
    public function getService(string $serviceName) {
        return $this->services[$serviceName] ?? null;
    }
    
    /**
     * دریافت یک کنترلر
     */
    public function getController(string $controllerName) {
        return $this->controllers[$controllerName] ?? null;
    }
    
    /**
     * بررسی آیا اسکریپت‌های فرانت باید بارگذاری شوند
     */
    private function shouldLoadFrontendAssets(): bool {
        // عدم بارگذاری در صفحات ادمین
        if (is_admin()) {
            return false;
        }
        
        // عدم بارگذاری در REST API
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        // عدم بارگذاری در صفحات خاص
        $disabled_pages = ['checkout', 'cart'];
        foreach ($disabled_pages as $page) {
            if (function_exists('is_page') && is_page($page)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * ارسال داده به جاوااسکریپت
     */
    private function localizeScriptData(): void {
        $customer_data = [];
        
        try {
            $customer_service = $this->getService('customer');
            if ($customer_service) {
                $customer = $customer_service->identify_customer();
                $customer_data = [
                    'id' => $customer['customer_id'],
                    'name' => $customer['customer_name'] ?? 'مهمان',
                    'email' => $customer['customer_email'] ?? '',
                ];
                
                // بررسی وجود مکالمه فعال
                $conversation_service = $this->getService('conversation');
                if ($conversation_service) {
                    $customer_data['has_active_conversation'] = $conversation_service->has_active_conversation($customer['customer_id']);
                }
            }
        } catch (\Exception $e) {
            error_log('Error localizing customer data: ' . $e->getMessage());
        }
        
        wp_localize_script('salmama-chat-widget', 'salmamaChat', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('salmama_chat_nonce'),
            'customer' => $customer_data,
            'settings' => $this->getChatSettings(),
            'i18n' => $this->getTranslations(),
        ]);
    }
    
    /**
     * دریافت تنظیمات چت
     */
    private function getChatSettings(): array {
        $settings = get_option(Constants::OPTION_SETTINGS, []);
        
        return [
            'business_hours' => $settings['general']['business_hours'] ?? [],
            'offline_message' => $settings['general']['offline_message'] ?? '',
            'welcome_message' => $settings['appearance']['welcome_message'] ?? 'سلام! چطور می‌تونم کمکتون کنم؟',
            'theme' => $settings['appearance']['theme'] ?? 'light',
            'position' => $settings['appearance']['position'] ?? 'bottom-right'
        ];
    }
    
    /**
     * دریافت متن‌های ترجمه
     */
    private function getTranslations(): array {
        return [
            'welcome' => __('خوش آمدید!', 'salmama-chat'),
            'type_message' => __('پیام خود را بنویسید...', 'salmama-chat'),
            'send' => __('ارسال', 'salmama-chat'),
            'attach_file' => __('افزودن فایل', 'salmama-chat'),
            'start_chat' => __('شروع گفتگو', 'salmama-chat'),
            'online' => __('آنلاین', 'salmama-chat'),
            'offline' => __('آفلاین', 'salmama-chat'),
            'connecting' => __('در حال اتصال...', 'salmama-chat'),
            'no_messages' => __('هنوز پیامی ارسال نشده است', 'salmama-chat'),
        ];
    }
    
    /**
     * پاکسازی داده‌های قدیمی
     */
    public function cleanupOldData(): void {
        // این متد می‌تواند برای پاکسازی داده‌های قدیمی استفاده شود
        // مثلاً پیام‌های بسیار قدیمی یا sessionهای منقضی شده
    }
}