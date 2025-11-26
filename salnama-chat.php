<?php
/**
 * Plugin Name: Salnama Chat
 * Plugin URI: https://salenoo.ir
 * Description: سیستم چت آنلاین پیشرفته برای فروشگاه سالنمای نو
 * Version: 1.0.2
 * Author: Salenoo Team
 * Text Domain: salnama-chat
 * Domain Path: /languages
 * Namespace: SalnamaChat
 * Requires PHP: 7.4
 */

// امنیت: دسترسی مستقیم به فایل مسدود شود
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثابت‌های اصلی پلاگین
 */
final class SalnamaChatConstants {
    const VERSION = '1.0.2';
    const PLUGIN_FILE = __FILE__;
    const PLUGIN_DIR = __DIR__;
    const PLUGIN_URL = '';
    const TEXT_DOMAIN = 'salnama-chat';
    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION = '5.6';
    
    // جلوگیری از instantiation
    private function __construct() {}
}

/**
 * ثابت‌های اصلی پلاگین
 */
// نسخه‌شناسی
define('SALNAMA_CHAT_VERSION', '1.0.2');
define('SALNAMA_CHAT_DB_VERSION', '1.0.0');
define('SALNAMA_CHAT_API_VERSION', 'v1');

// مسیرها
define('SALNAMA_CHAT_PLUGIN_FILE', __FILE__);
define('SALNAMA_CHAT_PLUGIN_DIR', __DIR__);
define('SALNAMA_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

// تنظیمات پایه
define('SALNAMA_CHAT_TEXT_DOMAIN', 'salnama-chat');
define('SALNAMA_CHAT_MIN_PHP_VERSION', '7.4');
define('SALNAMA_CHAT_MIN_WP_VERSION', '5.6');
define('SALNAMA_CHAT_REQUIRED_WP_CAPABILITY', 'manage_options');

// جداول دیتابیس
define('SALNAMA_CHAT_TABLE_CUSTOMERS', 'salnama_chat_customers');
define('SALNAMA_CHAT_TABLE_CONVERSATIONS', 'salnama_chat_conversations');
define('SALNAMA_CHAT_TABLE_MESSAGES', 'salnama_chat_messages');
define('SALNAMA_CHAT_TABLE_OPERATORS', 'salnama_chat_operators');

// وضعیت‌ها
define('SALNAMA_CHAT_STATUS_ACTIVE', 'active');
define('SALNAMA_CHAT_STATUS_INACTIVE', 'inactive');
define('SALNAMA_CHAT_STATUS_BLOCKED', 'blocked');
define('SALNAMA_CHAT_STATUS_PENDING', 'pending');

// انواع پیام
define('SALNAMA_CHAT_MESSAGE_TYPE_TEXT', 'text');
define('SALNAMA_CHAT_MESSAGE_TYPE_IMAGE', 'image');
define('SALNAMA_CHAT_MESSAGE_TYPE_FILE', 'file');
define('SALNAMA_CHAT_MESSAGE_TYPE_SYSTEM', 'system');

// اولویت‌ها
define('SALNAMA_CHAT_PRIORITY_LOW', 'low');
define('SALNAMA_CHAT_PRIORITY_MEDIUM', 'medium');
define('SALNAMA_CHAT_PRIORITY_HIGH', 'high');
define('SALNAMA_CHAT_PRIORITY_URGENT', 'urgent');

// وضعیت مکالمات
define('SALNAMA_CHAT_CONVERSATION_OPEN', 'open');
define('SALNAMA_CHAT_CONVERSATION_PENDING', 'pending');
define('SALNAMA_CHAT_CONVERSATION_CLOSED', 'closed');
define('SALNAMA_CHAT_CONVERSATION_RESOLVED', 'resolved');

// محدودیت‌ها
define('SALNAMA_CHAT_MESSAGE_MAX_LENGTH', 2000);
define('SALNAMA_CHAT_FILE_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('SALNAMA_CHAT_SESSION_TIMEOUT', 30 * MINUTE_IN_SECONDS);

// کلیدهای option
define('SALNAMA_CHAT_OPTION_SETTINGS', 'salnama_chat_settings');
define('SALNAMA_CHAT_OPTION_VERSION', 'salnama_chat_version');
define('SALNAMA_CHAT_OPTION_DB_VERSION', 'salnama_chat_db_version');

// REST API Routes
define('SALNAMA_CHAT_API_NAMESPACE', 'salnama-chat/v1');

// WebSocket
define('SALNAMA_CHAT_WS_PORT', 8080);
define('SALNAMA_CHAT_WS_HOST', '127.0.0.1');

// Cache Keys
define('SALNAMA_CHAT_CACHE_PREFIX', 'salnama_chat_');

/**
 * بررسی شرایط سرور قبل از راه‌اندازی
 */
if (!salnama_chat_check_requirements()) {
    add_action('admin_notices', 'salnama_chat_requirements_notice');
    return;
}

/**
 * بررسی نسخه PHP و وردپرس
 */
function salnama_chat_check_requirements(): bool {
    if (version_compare(PHP_VERSION, SalnamaChatConstants::MIN_PHP_VERSION, '<')) {
        return false;
    }
    
    global $wp_version;
    if (version_compare($wp_version, SalnamaChatConstants::MIN_WP_VERSION, '<')) {
        return false;
    }
    
    return true;
}

/**
 * نمایش خطای عدم تطابق شرایط
 */
function salnama_chat_requirements_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>افزونه Salnama Chat:</strong> 
            برای راه‌اندازی این افزونه نیاز به PHP نسخه <?php echo SalnamaChatConstants::MIN_PHP_VERSION; ?> 
            و وردپرس نسخه <?php echo SalnamaChatConstants::MIN_WP_VERSION; ?> یا بالاتر دارید.
        </p>
    </div>
    <?php
}

/**
 * Autoloader کلاس‌ها
 */
require_once __DIR__ . '/src/Core/Autoloader.php';
SalnamaChat\Core\Autoloader::register();

/**
 * راه‌اندازی پلاگین
 */
try {
    add_action('plugins_loaded', function() {
        // بررسی وجود کلاس اصلی
        if (class_exists('SalnamaChat\Core\Application')) {
            $GLOBALS['salnama_chat'] = SalnamaChat\Core\Application::getInstance();
            $GLOBALS['salnama_chat']->init();
        } else {
            throw new Exception('کلاس اصلی Application یافت نشد.');
        }
    });
    
    // رجیستر هوک‌های فعال‌سازی و غیرفعال‌سازی
    register_activation_hook(__FILE__, ['SalnamaChat\Core\Activator', 'activate']);
    register_deactivation_hook(__FILE__, ['SalnamaChat\Core\Deactivator', 'deactivate']);
    register_uninstall_hook(__FILE__, ['SalnamaChat\Core\Uninstaller', 'uninstall']);
    
} catch (Exception $e) {
    add_action('admin_notices', function() use ($e) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>خطا در راه‌اندازی افزونه Salnama Chat:</strong> 
                <?php echo esc_html($e->getMessage()); ?>
            </p>
        </div>
        <?php
    });
    
    // لاگ خطا برای دیباگ
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Salnama Chat Error: ' . $e->getMessage());
    }
}