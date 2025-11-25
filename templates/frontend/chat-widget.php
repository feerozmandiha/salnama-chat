<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="salnama-chat-widget" class="salnama-chat-widget">
    <!-- دکمه باز کردن چت -->
    <div class="chat-toggle-button">
        <div class="chat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"/>
            </svg>
        </div>
        <span class="chat-label">چت آنلاین</span>
        <div class="chat-badge" id="unread-count" style="display: none;">0</div>
    </div>

    <!-- پنجره چت -->
    <div class="chat-window">
        <div class="chat-header">
            <div class="chat-header-info">
                <h4>سالنمای نو</h4>
                <div class="chat-status">
                    <span class="status-indicator online"></span>
                    <span id="status-text">آنلاین</span>
                </div>
            </div>
            <button type="button" class="chat-close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M12 4L4 12M4 4L12 12"/>
                </svg>
            </button>
        </div>

        <div class="chat-body" id="chat-messages">
            <div class="welcome-message">
                <p>سلام! به چت سالنمای نو خوش آمدید. چطور می‌تونم کمکتون کنم؟</p>
            </div>
        </div>

        <div class="chat-footer">
            <div class="chat-input-container">
                <textarea id="chat-input" placeholder="پیام خود را بنویسید..." rows="1"></textarea>
                <button type="button" class="chat-send" id="chat-send">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M18 2L2 10L8 12M18 2L12 18L8 12M18 2L8 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>