<?php

namespace SalnamaChat\Core;

/**
 * مدیریت متمرکز تمام ثابت‌های پلاگین
 */
class Constants {
    
    // نسخه‌شناسی
    const VERSION = '1.0.0';
    const DB_VERSION = '1.0.0';
    const API_VERSION = 'v1';
    
    // مسیرها
    const PLUGIN_FILE = SALNAMA_CHAT_PLUGIN_FILE;
    const PLUGIN_DIR = SALNAMA_CHAT_PLUGIN_DIR;
    const PLUGIN_URL = SALNAMA_CHAT_PLUGIN_URL;
    
    // تنظیمات پایه
    const TEXT_DOMAIN = 'salnama-chat';
    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION = '5.6';
    const REQUIRED_WP_CAPABILITY = 'manage_options';
    
    // جداول دیتابیس
    const TABLE_CUSTOMERS = 'salnama_chat_customers';
    const TABLE_CONVERSATIONS = 'salnama_chat_conversations';
    const TABLE_MESSAGES = 'salnama_chat_messages';
    const TABLE_OPERATORS = 'salnama_chat_operators';
    
    // وضعیت‌ها
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_PENDING = 'pending';
    
    // انواع پیام
    const MESSAGE_TYPE_TEXT = 'text';
    const MESSAGE_TYPE_IMAGE = 'image';
    const MESSAGE_TYPE_FILE = 'file';
    const MESSAGE_TYPE_SYSTEM = 'system';
    
    // اولویت‌ها
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';
    
    // وضعیت مکالمات
    const CONVERSATION_OPEN = 'open';
    const CONVERSATION_PENDING = 'pending';
    const CONVERSATION_CLOSED = 'closed';
    const CONVERSATION_RESOLVED = 'resolved';
    
    // محدودیت‌ها
    const MESSAGE_MAX_LENGTH = 2000;
    const FILE_MAX_SIZE = 10 * 1024 * 1024; // 10MB
    const SESSION_TIMEOUT = 30 * MINUTE_IN_SECONDS;
    
    // کلیدهای option
    const OPTION_SETTINGS = 'salnama_chat_settings';
    const OPTION_VERSION = 'salnama_chat_version';
    const OPTION_DB_VERSION = 'salnama_chat_db_version';
    
    // REST API Routes
    const API_NAMESPACE = 'salnama-chat/v1';
    
    // WebSocket
    const WS_PORT = 8080;
    const WS_HOST = '127.0.0.1';
    
    // Cache Keys
    const CACHE_PREFIX = 'salnama_chat_';
    const CACHE_CUSTOMER_DATA = 'customer_data_';
    const CACHE_CONVERSATION = 'conversation_';
    
    /**
     * دریافت نام کامل جدول با پیشوند وردپرس
     */
    public static function get_table_name(string $table_name): string {
        global $wpdb;
        return $wpdb->prefix . $table_name;
    }
    
    /**
     * دریافت تمام ثابت‌های وضعیت
     */
    public static function get_statuses(): array {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_BLOCKED,
            self::STATUS_PENDING
        ];
    }
    
    /**
     * دریافت تمام انواع پیام
     */
    public static function get_message_types(): array {
        return [
            self::MESSAGE_TYPE_TEXT,
            self::MESSAGE_TYPE_IMAGE,
            self::MESSAGE_TYPE_FILE,
            self::MESSAGE_TYPE_SYSTEM
        ];
    }
    
    /**
     * بررسی معتبر بودن نوع پیام
     */
    public static function is_valid_message_type(string $type): bool {
        return in_array($type, self::get_message_types());
    }
    
    /**
     * بررسی معتبر بودن وضعیت
     */
    public static function is_valid_status(string $status): bool {
        return in_array($status, self::get_statuses());
    }
    
    /**
     * دریافت مسیر فایل‌های قالب
     */
    public static function get_template_path(string $template_name): string {
        return self::PLUGIN_DIR . '/templates/' . $template_name . '.php';
    }
    
    /**
     * دریافت URL فایل‌های asset
     */
    public static function get_asset_url(string $asset_path): string {
        return self::PLUGIN_URL . 'assets/' . $asset_path;
    }
    
    // جلوگیری از instantiation
    private function __construct() {}
    private function __clone() {}
}