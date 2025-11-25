<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1><?php _e('تنظیمات چت سالنمای نو', 'salnama-chat'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('salnama_chat_settings');
        do_settings_sections('salnama-chat-settings');
        submit_button();
        ?>
    </form>
    
    <div class="settings-info">
        <h3>راهنمای تنظیمات</h3>
        <p>تنظیمات سیستم چت در این بخش مدیریت می‌شود.</p>
    </div>
</div>