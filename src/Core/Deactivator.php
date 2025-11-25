<?php

namespace SalnamaChat\Core;

/**
 * مدیریت غیرفعال‌سازی پلاگین
 */
class Deactivator {
    
    /**
     * غیرفعال‌سازی پلاگین
     */
    public static function deactivate(): void {
        try {
            // پاک کردن cron jobs
            self::clear_cron_jobs();
            
            // بروزرسانی وضعیت اپراتورها
            self::update_operators_status();
            
            // بستن مکالمات فعال
            self::close_active_conversations();
            
            // flush rewrite rules
            flush_rewrite_rules();
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Deactivation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * پاک کردن cron jobs
     */
    private static function clear_cron_jobs(): void {
        $cron_hooks = [
            'salnama_chat_cleanup_old_data',
            'salnama_chat_update_operator_status',
            'salnama_chat_session_cleanup',
        ];
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    /**
     * بروزرسانی وضعیت اپراتورها به آفلاین
     */
    private static function update_operators_status(): void {
        global $wpdb;
        
        $table_name = Constants::get_table_name(Constants::TABLE_OPERATORS);
        
        $wpdb->update(
            $table_name,
            ['is_online' => 0, 'current_chats_count' => 0],
            ['is_online' => 1],
            ['%d', '%d'],
            ['%d']
        );
    }
    
    /**
     * بستن مکالمات فعال
     */
    private static function close_active_conversations(): void {
        global $wpdb;
        
        $table_name = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        
        $wpdb->update(
            $table_name,
            [
                'status' => 'closed',
                'closed_at' => current_time('mysql')
            ],
            ['status' => ['open', 'pending']],
            ['%s', '%s'],
            ['%s']
        );
    }
}