<?php

namespace SalnamaChat\Core;

/**
 * مدیریت حذف پلاگین
 */
class Uninstaller {
    
    /**
     * حذف پلاگین
     */
    public static function uninstall(): void {
        // بررسی اینکه آیا واقعاً کاربر می‌خواهد پلاگین را حذف کند
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            exit;
        }
        
        $settings = get_option(Constants::OPTION_SETTINGS, []);
        $remove_data = $settings['general']['remove_data_on_uninstall'] ?? false;
        
        if (!$remove_data) {
            return;
        }
        
        self::remove_all_data();
    }
    
    /**
     * حذف تمام داده‌های پلاگین
     */
    private static function remove_all_data(): void {
        global $wpdb;
        
        // حذف جداول دیتابیس
        $tables = [
            Constants::TABLE_CUSTOMERS,
            Constants::TABLE_CONVERSATIONS,
            Constants::TABLE_MESSAGES,
            Constants::TABLE_OPERATORS
        ];
        
        foreach ($tables as $table) {
            $table_name = Constants::get_table_name($table);
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
        
        // حذف optionها
        delete_option(Constants::OPTION_SETTINGS);
        delete_option(Constants::OPTION_VERSION);
        delete_option(Constants::OPTION_DB_VERSION);
        
        // حذف cron jobs
        $cron_hooks = [
            'salnama_chat_cleanup_old_data',
            'salnama_chat_update_operator_status',
            'salnama_chat_session_cleanup',
        ];
        
        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        
        // حذف roleها و capabilityها
        self::remove_roles_capabilities();
    }
    
    /**
     * حذف roleها و capabilityها
     */
    private static function remove_roles_capabilities(): void {
        // حذف role اپراتور چت
        remove_role('salnama_chat_operator');
        
        // حذف capabilityها از administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = [
                'salnama_chat_manage_settings',
                'salnama_chat_manage_conversations',
                'salnama_chat_send_messages',
                'salnama_chat_view_reports',
                'salnama_chat_manage_operators',
                'salnama_chat_view_customers',
            ];
            
            foreach ($capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }
}