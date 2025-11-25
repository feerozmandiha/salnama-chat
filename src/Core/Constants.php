<?php

namespace SalnamaChat\Core;

/**
 * مدیریت متمرکز تمام ثابت‌های پلاگین
 */
class Constants {
    
    // استفاده از ثابت‌های تعریف شده در فایل اصلی
    const VERSION = SALNAMA_CHAT_VERSION;
    const DB_VERSION = SALNAMA_CHAT_DB_VERSION;
    const API_VERSION = SALNAMA_CHAT_API_VERSION;
    
    // مسیرها
    const PLUGIN_FILE = SALNAMA_CHAT_PLUGIN_FILE;
    const PLUGIN_DIR = SALNAMA_CHAT_PLUGIN_DIR;
    const PLUGIN_URL = SALNAMA_CHAT_PLUGIN_URL;
    
    // تنظیمات پایه
    const TEXT_DOMAIN = SALNAMA_CHAT_TEXT_DOMAIN;
    const MIN_PHP_VERSION = SALNAMA_CHAT_MIN_PHP_VERSION;
    const MIN_WP_VERSION = SALNAMA_CHAT_MIN_WP_VERSION;
    const REQUIRED_WP_CAPABILITY = SALNAMA_CHAT_REQUIRED_WP_CAPABILITY;
    
    // جداول دیتابیس
    const TABLE_CUSTOMERS = SALNAMA_CHAT_TABLE_CUSTOMERS;
    const TABLE_CONVERSATIONS = SALNAMA_CHAT_TABLE_CONVERSATIONS;
    const TABLE_MESSAGES = SALNAMA_CHAT_TABLE_MESSAGES;
    const TABLE_OPERATORS = SALNAMA_CHAT_TABLE_OPERATORS;
    
    // وضعیت‌ها
    const STATUS_ACTIVE = SALNAMA_CHAT_STATUS_ACTIVE;
    const STATUS_INACTIVE = SALNAMA_CHAT_STATUS_INACTIVE;
    const STATUS_BLOCKED = SALNAMA_CHAT_STATUS_BLOCKED;
    const STATUS_PENDING = SALNAMA_CHAT_STATUS_PENDING;
    
    // انواع پیام
    const MESSAGE_TYPE_TEXT = SALNAMA_CHAT_MESSAGE_TYPE_TEXT;
    const MESSAGE_TYPE_IMAGE = SALNAMA_CHAT_MESSAGE_TYPE_IMAGE;
    const MESSAGE_TYPE_FILE = SALNAMA_CHAT_MESSAGE_TYPE_FILE;
    const MESSAGE_TYPE_SYSTEM = SALNAMA_CHAT_MESSAGE_TYPE_SYSTEM;
    
    // اولویت‌ها
    const PRIORITY_LOW = SALNAMA_CHAT_PRIORITY_LOW;
    const PRIORITY_MEDIUM = SALNAMA_CHAT_PRIORITY_MEDIUM;
    const PRIORITY_HIGH = SALNAMA_CHAT_PRIORITY_HIGH;
    const PRIORITY_URGENT = SALNAMA_CHAT_PRIORITY_URGENT;
    
    // وضعیت مکالمات
    const CONVERSATION_OPEN = SALNAMA_CHAT_CONVERSATION_OPEN;
    const CONVERSATION_PENDING = SALNAMA_CHAT_CONVERSATION_PENDING;
    const CONVERSATION_CLOSED = SALNAMA_CHAT_CONVERSATION_CLOSED;
    const CONVERSATION_RESOLVED = SALNAMA_CHAT_CONVERSATION_RESOLVED;
    
    // محدودیت‌ها
    const MESSAGE_MAX_LENGTH = SALNAMA_CHAT_MESSAGE_MAX_LENGTH;
    const FILE_MAX_SIZE = SALNAMA_CHAT_FILE_MAX_SIZE;
    const SESSION_TIMEOUT = SALNAMA_CHAT_SESSION_TIMEOUT;
    
    // کلیدهای option
    const OPTION_SETTINGS = SALNAMA_CHAT_OPTION_SETTINGS;
    const OPTION_VERSION = SALNAMA_CHAT_OPTION_VERSION;
    const OPTION_DB_VERSION = SALNAMA_CHAT_OPTION_DB_VERSION;
    
    // REST API Routes
    const API_NAMESPACE = SALNAMA_CHAT_API_NAMESPACE;
    
    // WebSocket
    const WS_PORT = SALNAMA_CHAT_WS_PORT;
    const WS_HOST = SALNAMA_CHAT_WS_HOST;
    
    // Cache Keys
    const CACHE_PREFIX = SALNAMA_CHAT_CACHE_PREFIX;
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
     * دریافت تمام اولویت‌ها
     */
    public static function get_priorities(): array {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ];
    }
    
    /**
     * دریافت تمام وضعیت‌های مکالمه
     */
    public static function get_conversation_statuses(): array {
        return [
            self::CONVERSATION_OPEN,
            self::CONVERSATION_PENDING,
            self::CONVERSATION_CLOSED,
            self::CONVERSATION_RESOLVED
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
     * بررسی معتبر بودن اولویت
     */
    public static function is_valid_priority(string $priority): bool {
        return in_array($priority, self::get_priorities());
    }
    
    /**
     * بررسی معتبر بودن وضعیت مکالمه
     */
    public static function is_valid_conversation_status(string $status): bool {
        return in_array($status, self::get_conversation_statuses());
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