<?php

namespace SalnamaChat\Core;

/**
 * مدیریت فعال‌سازی پلاگین
 */
class Activator {
    
    /**
     * فعال‌سازی پلاگین
     */
    public static function activate(): void {
        try {
            // بررسی dependencies
            self::check_dependencies();
            
            // ایجاد جداول دیتابیس
            self::create_tables();
            
            // ایجاد roleها و capabilityها
            self::setup_roles_capabilities();
            
            // افزودن optionهای پیش‌فرض
            self::set_default_options();
            
            // برنامه‌ریزی cron jobs
            self::schedule_cron_jobs();
            
            // ثبت نسخه
            update_option(Constants::OPTION_VERSION, Constants::VERSION);
            update_option(Constants::OPTION_DB_VERSION, Constants::DB_VERSION);
            
            // flush rewrite rules در بارگذاری بعدی
            add_option('salnama_chat_flush_rewrite_rules', '1');
            
        } catch (\Exception $e) {
            self::handle_activation_error($e);
        }
    }
    
    /**
     * بررسی وابستگی‌ها
     */
    private static function check_dependencies(): void {
        global $wp_version;
        
        // بررسی نسخه PHP
        if (version_compare(PHP_VERSION, Constants::MIN_PHP_VERSION, '<')) {
            throw new \Exception(
                sprintf(
                    'نیاز به PHP نسخه %s یا بالاتر دارید. نسخه فعلی: %s',
                    Constants::MIN_PHP_VERSION,
                    PHP_VERSION
                )
            );
        }
        
        // بررسی نسخه وردپرس
        if (version_compare($wp_version, Constants::MIN_WP_VERSION, '<')) {
            throw new \Exception(
                sprintf(
                    'نیاز به وردپرس نسخه %s یا بالاتر دارید. نسخه فعلی: %s',
                    Constants::MIN_WP_VERSION,
                    $wp_version
                )
            );
        }
        
        // بررسی extensionهای مورد نیاز
        $required_extensions = ['json', 'mbstring', 'pdo_mysql'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        
        if (!empty($missing_extensions)) {
            throw new \Exception(
                'Extensionهای PHP زیر مورد نیاز هستند: ' . implode(', ', $missing_extensions)
            );
        }
    }
    
    /**
     * ایجاد جداول دیتابیس
     */
    private static function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = [
            Constants::TABLE_CUSTOMERS => self::get_customers_table_sql($wpdb, $charset_collate),
            Constants::TABLE_CONVERSATIONS => self::get_conversations_table_sql($wpdb, $charset_collate),
            Constants::TABLE_MESSAGES => self::get_messages_table_sql($wpdb, $charset_collate),
            Constants::TABLE_OPERATORS => self::get_operators_table_sql($wpdb, $charset_collate),
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }
        
        // بررسی خطاهای ایجاد جدول
        if (!empty($wpdb->last_error)) {
            throw new \Exception('خطا در ایجاد جداول دیتابیس: ' . $wpdb->last_error);
        }
    }
    
    /**
     * SQL جدول مشتریان
     */
    private static function get_customers_table_sql($wpdb, $charset_collate): string {
        $table_name = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        
        return "CREATE TABLE {$table_name} (
            customer_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            customer_email VARCHAR(100) DEFAULT NULL,
            customer_name VARCHAR(100) DEFAULT NULL,
            customer_phone VARCHAR(20) DEFAULT NULL,
            unique_visitor_id VARCHAR(64) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT,
            first_visit DATETIME NOT NULL,
            last_visit DATETIME NOT NULL,
            total_conversations INT UNSIGNED DEFAULT 0,
            status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
            metadata LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (customer_id),
            UNIQUE KEY unique_visitor_id (unique_visitor_id),
            KEY user_id (user_id),
            KEY customer_email (customer_email),
            KEY status (status),
            KEY last_visit (last_visit)
        ) {$charset_collate};";
    }
    
    /**
     * SQL جدول مکالمات
     */
    private static function get_conversations_table_sql($wpdb, $charset_collate): string {
        $table_name = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        
        return "CREATE TABLE {$table_name} (
            conversation_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            operator_id BIGINT(20) UNSIGNED DEFAULT 0,
            subject VARCHAR(255) DEFAULT NULL,
            status ENUM('open', 'pending', 'closed', 'resolved') DEFAULT 'open',
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            rating TINYINT UNSIGNED DEFAULT NULL,
            tags VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at DATETIME NULL,
            PRIMARY KEY (conversation_id),
            KEY customer_id (customer_id),
            KEY operator_id (operator_id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at),
            FOREIGN KEY (customer_id) REFERENCES {$wpdb->prefix}" . Constants::TABLE_CUSTOMERS . "(customer_id) ON DELETE CASCADE
        ) {$charset_collate};";
    }
    
    /**
     * SQL جدول پیام‌ها
     */
    private static function get_messages_table_sql($wpdb, $charset_collate): string {
        $table_name = Constants::get_table_name(Constants::TABLE_MESSAGES);
        
        return "CREATE TABLE {$table_name} (
            message_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            sender_type ENUM('customer', 'operator') NOT NULL,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
            message_content LONGTEXT,
            attachment_url VARCHAR(500) DEFAULT NULL,
            attachment_name VARCHAR(255) DEFAULT NULL,
            attachment_size INT UNSIGNED DEFAULT NULL,
            read_status TINYINT(1) DEFAULT 0,
            delivered_status TINYINT(1) DEFAULT 0,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            PRIMARY KEY (message_id),
            KEY conversation_id (conversation_id),
            KEY sender_type (sender_type),
            KEY read_status (read_status),
            KEY sent_at (sent_at),
            FOREIGN KEY (conversation_id) REFERENCES {$wpdb->prefix}" . Constants::TABLE_CONVERSATIONS . "(conversation_id) ON DELETE CASCADE
        ) {$charset_collate};";
    }
    
    /**
     * SQL جدول اپراتورها
     */
    private static function get_operators_table_sql($wpdb, $charset_collate): string {
        $table_name = Constants::get_table_name(Constants::TABLE_OPERATORS);
        
        return "CREATE TABLE {$table_name} (
            operator_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            max_concurrent_chats TINYINT UNSIGNED DEFAULT 5,
            is_online TINYINT(1) DEFAULT 0,
            last_activity DATETIME NULL,
            current_chats_count TINYINT UNSIGNED DEFAULT 0,
            settings LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (operator_id),
            UNIQUE KEY user_id (user_id),
            KEY is_online (is_online),
            KEY last_activity (last_activity)
        ) {$charset_collate};";
    }
    
    /**
     * تنظیم roleها و capabilityها
     */
    private static function setup_roles_capabilities(): void {
        $capabilities = self::get_plugin_capabilities();
        
        // افزودن capabilityها به administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // ایجاد role جدید برای اپراتور چت
        add_role(
            'salnama_chat_operator',
            'اپراتور چت',
            [
                'read' => true,
                'salnama_chat_manage_conversations' => true,
                'salnama_chat_send_messages' => true,
                'salnama_chat_view_customers' => true,
            ]
        );
    }
    
    /**
     * دریافت لیست capabilityهای پلاگین
     */
    private static function get_plugin_capabilities(): array {
        return [
            'salnama_chat_manage_settings',
            'salnama_chat_manage_conversations',
            'salnama_chat_send_messages',
            'salnama_chat_view_reports',
            'salnama_chat_manage_operators',
            'salnama_chat_view_customers',
        ];
    }
    
    /**
     * تنظیم optionهای پیش‌فرض
     */
    private static function set_default_options(): void {
        $default_settings = [
            'general' => [
                'enable_chat' => true,
                'business_hours' => [
                    'enabled' => false,
                    'timezone' => 'Asia/Tehran',
                    'hours' => [
                        'mon' => ['09:00', '17:00'],
                        'tue' => ['09:00', '17:00'],
                        'wed' => ['09:00', '17:00'],
                        'thu' => ['09:00', '17:00'],
                        'fri' => ['09:00', '17:00'],
                        'sat' => ['09:00', '13:00'],
                        'sun' => ['closed'],
                    ]
                ],
                'offline_message' => 'در حال حاضر آنلاین نیستیم. پیام خود را بگذارید تا با شما تماس بگیریم.',
            ],
            'appearance' => [
                'theme' => 'light',
                'primary_color' => '#007cba',
                'position' => 'bottom-right',
                'welcome_message' => 'سلام! چطور می‌تونم کمکتون کنم؟',
            ],
            'notifications' => [
                'enable_sound' => true,
                'enable_desktop' => true,
                'new_message_sound' => 'default',
            ],
            'security' => [
                'block_suspicious_ips' => true,
                'max_messages_per_minute' => 10,
                'session_timeout' => 30,
            ]
        ];
        
        if (!get_option(Constants::OPTION_SETTINGS)) {
            add_option(Constants::OPTION_SETTINGS, $default_settings);
        }
    }
    
    /**
     * برنامه‌ریزی cron jobs
     */
    private static function schedule_cron_jobs(): void {
        if (!wp_next_scheduled('salnama_chat_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'salnama_chat_cleanup_old_data');
        }
        
        if (!wp_next_scheduled('salnama_chat_update_operator_status')) {
            wp_schedule_event(time(), 'hourly', 'salnama_chat_update_operator_status');
        }
    }
    
    /**
     * مدیریت خطاهای فعال‌سازی
     */
    private static function handle_activation_error(\Exception $e): void {
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(Constants::PLUGIN_FILE));
        
        // Show error message
        wp_die(
            '<h1>خطا در فعال‌سازی افزونه Salnama Chat</h1>' .
            '<p>' . esc_html($e->getMessage()) . '</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">بازگشت به صفحه افزونه‌ها</a></p>'
        );
    }
}