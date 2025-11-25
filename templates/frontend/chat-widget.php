<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="salnama-chat-widget" class="salnama-chat-widget <?php echo esc_attr($position); ?> <?php echo esc_attr($theme); ?>">
    <div class="chat-widget-container">
        <!-- دکمه باز کردن چت -->
        <div class="chat-toggle-button">
            <div class="chat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="currentColor"/>
                </svg>
            </div>
            <span class="chat-label"><?php _e('چت آنلاین', 'salnama-chat'); ?></span>
            <div class="chat-badge" id="unread-count" style="display: none;">0</div>
        </div>

        <!-- پنجره چت -->
        <div class="chat-window" style="display: none;">
            <div class="chat-header">
                <div class="chat-header-info">
                    <h4><?php echo esc_html($welcome_message); ?></h4>
                    <div class="chat-status">
                        <span class="status-indicator online" id="status-indicator"></span>
                        <span id="status-text"><?php _e('آنلاین', 'salnama-chat'); ?></span>
                    </div>
                </div>
                <button type="button" class="chat-close">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </div>

            <div class="chat-body" id="chat-messages">
                <!-- پیام‌ها اینجا نمایش داده می‌شوند -->
            </div>

            <div class="chat-footer">
                <div class="chat-input-container">
                    <textarea id="chat-input" placeholder="<?php _e('پیام خود را بنویسید...', 'salnama-chat'); ?>" rows="1"></textarea>
                    <div class="chat-actions">
                        <button type="button" class="chat-attachment" id="chat-attachment">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M15 6H5C3.3 6 2 7.3 2 9V15C2 16.7 3.3 18 5 18H15C16.7 18 18 16.7 18 15V9C18 7.3 16.7 6 15 6Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M6 6V4C6 2.3 7.3 1 9 1H11C12.7 1 14 2.3 14 4V6" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                        <button type="button" class="chat-send" id="chat-send">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M18 2L2 10L8 12M18 2L12 18L8 12M18 2L8 12" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <input type="file" id="file-input" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt">
            </div>
        </div>
    </div>
</div>