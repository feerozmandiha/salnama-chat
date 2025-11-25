<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1><?php _e('تنظیمات چت سالنمای نو', 'salnama-chat'); ?></h1>
    
    <div class="settings-container">
        <div class="settings-main">
            <form method="post" action="options.php" class="settings-form">
                <?php
                settings_fields('salnama_chat_settings');
                do_settings_sections('salnama-chat-settings');
                submit_button(__('ذخیره تغییرات', 'salnama-chat'));
                ?>
            </form>
        </div>
        
        <div class="settings-sidebar">
            <div class="settings-card">
                <h3><?php _e('راهنمای تنظیمات', 'salnama-chat'); ?></h3>
                <div class="settings-info">
                    <h4><?php _e('تنظیمات عمومی', 'salnama-chat'); ?></h4>
                    <p><?php _e('تنظیمات اصلی سیستم چت شامل ساعات کاری و پیام‌های پیش‌فرض.', 'salnama-chat'); ?></p>
                    
                    <h4><?php _e('تنظیمات ظاهر', 'salnama-chat'); ?></h4>
                    <p><?php _e('شامل رنگ‌ها، موقعیت ویجت و پیام خوشامدگویی.', 'salnama-chat'); ?></p>
                    
                    <h4><?php _e('تنظیمات اعلان‌ها', 'salnama-chat'); ?></h4>
                    <p><?php _e('مدیریت صداها و اعلان‌های دسکتاپ.', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="settings-card">
                <h3><?php _e('وضعیت سیستم', 'salnama-chat'); ?></h3>
                <div class="system-status">
                    <div class="status-item">
                        <span class="status-label"><?php _e('نسخه پلاگین:', 'salnama-chat'); ?></span>
                        <span class="status-value"><?php echo esc_html(SALNAMA_CHAT_VERSION); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php _e('پایگاه داده:', 'salnama-chat'); ?></span>
                        <span class="status-value status-success"><?php _e('فعال', 'salnama-chat'); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php _e('WebSocket:', 'salnama-chat'); ?></span>
                        <span class="status-value status-warning"><?php _e('غیرفعال', 'salnama-chat'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>