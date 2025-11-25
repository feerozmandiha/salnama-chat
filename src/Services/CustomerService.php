<?php

namespace SalnamaChat\Services;

use SalnamaChat\Models\Customer;
use SalnamaChat\Core\Constants;

/**
 * سرویس business logic برای مدیریت مشتریان
 */
class CustomerService {
    
    private $customer_model;
    
    public function __construct() {
        $this->customer_model = new Customer();
    }
    
    /**
     * شناسایی یا ایجاد مشتری
     */
    public function identify_customer(array $session_data = []): array {
        try {
            // ایجاد شناسه یکتا برای بازدیدکننده
            $visitor_id = $this->generate_visitor_id($session_data);
            
            // داده‌های پایه مشتری
            $customer_data = [
                'unique_visitor_id' => $visitor_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
            
            // اگر کاربر لاگین کرده باشد
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $customer_data['user_id'] = $user->ID;
                $customer_data['customer_email'] = $user->user_email;
                $customer_data['customer_name'] = $user->display_name;
            }
            
            // ادغام با داده‌های session
            if (!empty($session_data)) {
                $customer_data = array_merge($customer_data, $session_data);
            }
            
            // ایجاد یا به روزرسانی مشتری
            $customer = $this->customer_model->create_or_update($customer_data);
            
            // به روزرسانی آخرین بازدید
            $this->customer_model->update_last_visit($customer['customer_id']);
            
            return $customer;
            
        } catch (\Exception $e) {
            error_log('Customer Identification Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * تولید شناسه یکتا برای بازدیدکننده
     */
    private function generate_visitor_id(array $session_data): string {
        // اگر از قبل در session وجود دارد
        if (!empty($session_data['visitor_id'])) {
            return $session_data['visitor_id'];
        }
        
        // اگر کاربر لاگین کرده باشد
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return 'user_' . $user->ID;
        }
        
        // ایجاد شناسه جدید بر اساس IP و User Agent
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . $user_agent . time());
    }
    
    /**
     * دریافت اطلاعات کامل مشتری
     */
    public function get_customer_profile(int $customer_id): array {
        $customer = $this->customer_model->get_by_id($customer_id);
        $stats = $this->customer_model->get_stats($customer_id);
        
        return [
            'profile' => $customer,
            'stats' => $stats,
            'recent_conversations' => $this->get_recent_conversations($customer_id)
        ];
    }
    
    /**
     * دریافت مکالمات اخیر مشتری
     */
    private function get_recent_conversations(int $customer_id, int $limit = 5): array {
        $conversation_model = new \SalnamaChat\Models\Conversation();
        
        try {
            $conversations = $conversation_model->get_active_by_customer($customer_id);
            return array_slice($conversations, 0, $limit);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * جستجوی پیشرفته مشتریان
     */
    public function search_customers(array $filters = [], int $page = 1, int $per_page = 20): array {
        return $this->customer_model->search($filters, $page, $per_page);
    }
    
    /**
     * به روزرسانی پروفایل مشتری
     */
    public function update_customer_profile(int $customer_id, array $profile_data): array {
        // فیلدهای مجاز برای به روزرسانی
        $allowed_fields = ['customer_name', 'customer_email', 'customer_phone', 'metadata'];
        $update_data = array_intersect_key($profile_data, array_flip($allowed_fields));
        
        return $this->customer_model->update($customer_id, $update_data);
    }
    
    /**
     * مسدود کردن مشتری
     */
    public function block_customer(int $customer_id, string $reason = ''): bool {
        // ذخیره دلیل مسدودسازی در metadata
        $metadata = ['block_reason' => $reason, 'blocked_at' => current_time('mysql')];
        
        $this->customer_model->update($customer_id, [
            'status' => Constants::STATUS_BLOCKED,
            'metadata' => $metadata
        ]);
        
        // بستن مکالمات فعال مشتری
        $this->close_active_conversations($customer_id);
        
        return true;
    }
    
    /**
     * بستن مکالمات فعال مشتری مسدود شده
     */
    private function close_active_conversations(int $customer_id): void {
        $conversation_model = new \SalnamaChat\Models\Conversation();
        
        try {
            $active_conversations = $conversation_model->get_active_by_customer($customer_id);
            
            foreach ($active_conversations as $conversation) {
                $conversation_model->close($conversation['conversation_id'], 0);
            }
        } catch (\Exception $e) {
            error_log('Error closing conversations for blocked customer: ' . $e->getMessage());
        }
    }
    
    /**
     * دریافت آمار کلی مشتریان
     */
    public function get_customers_stats(): array {
        $table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        
        $sql = "
            SELECT 
                COUNT(*) as total_customers,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_customers,
                SUM(CASE WHEN user_id > 0 THEN 1 ELSE 0 END) as registered_customers,
                AVG(total_conversations) as avg_conversations_per_customer,
                COUNT(DISTINCT DATE(last_visit)) as active_days
            FROM {$table}
        ";
        
        $stats = $this->customer_model->get_db()->get_row($sql);
        
        return [
            'total_customers' => (int)($stats['total_customers'] ?? 0),
            'active_customers' => (int)($stats['active_customers'] ?? 0),
            'blocked_customers' => (int)($stats['blocked_customers'] ?? 0),
            'registered_customers' => (int)($stats['registered_customers'] ?? 0),
            'avg_conversations_per_customer' => round(($stats['avg_conversations_per_customer'] ?? 0), 2),
            'active_days' => (int)($stats['active_days'] ?? 0)
        ];
    }
}