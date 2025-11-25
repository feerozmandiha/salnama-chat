<?php
/**
 * Plugin Name: Salnama Chat
 * Plugin URI: https://salenoo.ir
 * Description: سیستم چت آنلاین پیشرفته برای فروشگاه سالنمای نو
 * Version: 1.0.0
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
    const VERSION = '1.0.0';
    const PLUGIN_FILE = __FILE__;
    const PLUGIN_DIR = __DIR__;
    const PLUGIN_URL = '';
    const TEXT_DOMAIN = 'salnama-chat';
    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION = '5.6';
    
    // جلوگیری از instantiation
    private function __construct() {}
}

// مقداردهی ثابت PLUGIN_URL
define('SALNAMA_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

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