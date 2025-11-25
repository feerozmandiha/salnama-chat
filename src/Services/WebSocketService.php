<?php

namespace SalnamaChat\Services;

use SalnamaChat\Core\Constants;
use SalnamaChat\Core\Database;

/**
 * سرویس ارتباطی - نسخه ساده بدون وابستگی به Ratchet
 */
class WebSocketService {
    
    private $is_websocket_enabled = false;
    
    public function __construct() {
        // WebSocket غیرفعال - از polling استفاده می‌کنیم
    }
    
    /**
     * راه‌اندازی سرویس
     */
    public function init(): void {
        // در این نسخه کاری انجام نمی‌دهیم
        // هوک‌های AJAX در کنترلرها مدیریت می‌شوند
    }
    
    /**
     * بررسی فعال بودن WebSocket
     */
    public function is_enabled(): bool {
        return false;
    }
    
    /**
     * ارسال پیام به کاربر (برای سازگاری)
     */
    public function send_to_user(int $user_id, string $user_type, array $message): bool {
        // در این نسخه کاری انجام نمی‌دهد
        return true;
    }
    
    /**
     * شروع سرور WebSocket (غیرفعال)
     */
    public function start_websocket_server(): void {
        // غیرفعال
    }
    
    /**
     * دریافت لیست کاربران آنلاین (غیرفعال)
     */
    public function get_online_users(int $conversation_id): array {
        return [];
    }
}