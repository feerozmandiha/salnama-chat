<?php

namespace SalnamaChat\Core;

use SalnamaChat\Controllers\Admin\AdminController;
use SalnamaChat\Controllers\Frontend\ChatController;
use SalnamaChat\Controllers\Frontend\WidgetController;
use SalnamaChat\Services\WebSocketService;
use SalnamaChat\Services\CustomerService;
use SalnamaChat\Services\ConversationService;
use SalnamaChat\Api\RestApi;

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
            'database' => new Database(),
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
            'admin' => new AdminController($this->services['conversation']),
            'chat' => new ChatController(
                $this->services['conversation'],
                $this->services['customer']
            ),
            'widget' => new WidgetController($this->services['websocket']),
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
            'salnama-chat-widget',
            SALNAMA_CHAT_PLUGIN_URL . 'src/Frontend/Assets/css/chat-widget.css',
            [],
            Constants::VERSION
        );
        
        wp_enqueue_script(
            'salnama-chat-widget',
            SALNAMA_CHAT_PLUGIN_URL . 'src/Frontend/Assets/js/chat-widget.js',
            ['jquery'],
            Constants::VERSION,
            true
        );
        
        // Localize script data
        $this->localizeScriptData();
    }
    
    /**
     * مدیریت خطاها
     */
    private function handleError(string $message, \Exception $e): void {
        // لاگ کردن خطا
        error_log("salnama Chat Error: {$message} - {$e->getMessage()}");
        
        // نمایش خطا در حالت دیباگ
        if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
            add_action('admin_notices', function() use ($message, $e) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>salnama Chat:</strong> 
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
        // منطق بررسی - مثلاً در صفحات خاص یا برای کاربران خاص
        return !is_admin() && !wp_is_json_request();
    }
    
    /**
     * ارسال داده به جاوااسکریپت
     */
    private function localizeScriptData(): void {
        wp_localize_script('salnama-chat-widget', 'salnamaChatConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('salnama_chat_nonce'),
            'currentUser' => $this->getCurrentUserData(),
            'settings' => $this->getChatSettings(),
            'i18n' => $this->getTranslations(),
        ]);
    }
    
    /**
     * دریافت اطلاعات کاربر جاری
     */
    private function getCurrentUserData(): array {
        // منطق دریافت اطلاعات کاربر
        return [];
    }
    
    /**
     * دریافت تنظیمات چت
     */
    private function getChatSettings(): array {
        // منطق دریافت تنظیمات
        return [];
    }
    
    /**
     * دریافت متن‌های ترجمه
     */
    private function getTranslations(): array {
        return [
            'typeMessage' => __('پیام خود را بنویسید...', 'salnama-chat'),
            'send' => __('ارسال', 'salnama-chat'),
            'connecting' => __('در حال اتصال...', 'salnama-chat'),
        ];
    }
}