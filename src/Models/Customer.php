<?php

namespace SalnamaChat\Models;

use SalnamaChat\Core\Constants;
use SalnamaChat\Core\Database;
use SalnamaChat\Traits\Validator;

/**
 * مدل مدیریت مشتریان
 */
class Customer {
    
    use Validator;
    
    /**
     * @var Database
     */
    private $db;
    
    /**
     * @var array فیلدهای مدل
     */
    private $fields = [
        'customer_id', 'user_id', 'customer_email', 'customer_name', 'customer_phone',
        'unique_visitor_id', 'ip_address', 'user_agent', 'first_visit', 'last_visit',
        'total_conversations', 'status', 'metadata', 'created_at', 'updated_at'
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * ایجاد یا به روزرسانی مشتری
     */
    public function create_or_update(array $data): array {
        try {
            // اعتبارسنجی داده‌ها
            $this->validate_customer_data($data);
            
            // بررسی وجود مشتری
            $existing_customer = $this->get_by_visitor_id($data['unique_visitor_id']);
            
            if ($existing_customer) {
                return $this->update($existing_customer['customer_id'], $data);
            } else {
                return $this->create($data);
            }
            
        } catch (\Exception $e) {
            error_log('Customer Create/Update Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * ایجاد مشتری جدید
     */
    public function create(array $data): array {
        $this->db->begin_transaction();
        
        try {
            // آماده‌سازی داده‌ها
            $customer_data = $this->prepare_customer_data($data);
            
            // درج در دیتابیس
            $customer_id = $this->db->insert(Constants::TABLE_CUSTOMERS, $customer_data);
            
            if (!$customer_id) {
                throw new \Exception('خطا در ایجاد مشتری جدید');
            }
            
            $this->db->commit();
            
            return $this->get_by_id($customer_id);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * به روزرسانی مشتری
     */
    public function update(int $customer_id, array $data): array {
        $this->validate_customer_id($customer_id);
        
        // آماده‌سازی داده‌ها برای به روزرسانی
        $update_data = $this->prepare_customer_data($data, true);
        $update_data['updated_at'] = current_time('mysql');
        
        $where = ['customer_id' => $customer_id];
        
        $result = $this->db->update(Constants::TABLE_CUSTOMERS, $update_data, $where);
        
        if (!$result) {
            throw new \Exception('خطا در به روزرسانی اطلاعات مشتری');
        }
        
        return $this->get_by_id($customer_id);
    }
    
    /**
     * دریافت مشتری بر اساس ID
     */
    public function get_by_id(int $customer_id): array {
        $this->validate_customer_id($customer_id);
        
        $table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        $sql = "SELECT * FROM {$table} WHERE customer_id = %d";
        
        $customer = $this->db->get_row($sql, [$customer_id]);
        
        if (!$customer) {
            throw new \Exception('مشتری یافت نشد');
        }
        
        return $this->format_customer_data($customer);
    }
    
    /**
     * دریافت مشتری بر اساس visitor_id
     */
    public function get_by_visitor_id(string $visitor_id): ?array {
        if (empty($visitor_id)) {
            return null;
        }
        
        $table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        $sql = "SELECT * FROM {$table} WHERE unique_visitor_id = %s";
        
        $customer = $this->db->get_row($sql, [$visitor_id]);
        
        return $customer ? $this->format_customer_data($customer) : null;
    }
    
    /**
     * دریافت مشتری بر اساس user_id
     */
    public function get_by_user_id(int $user_id): ?array {
        $table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        $sql = "SELECT * FROM {$table} WHERE user_id = %d";
        
        $customer = $this->db->get_row($sql, [$user_id]);
        
        return $customer ? $this->format_customer_data($customer) : null;
    }
    
    /**
     * جستجوی مشتریان
     */
    public function search(array $criteria, int $page = 1, int $per_page = 20): array {
        $where_conditions = [];
        $params = [];
        
        // ساخت شرط‌های جستجو
        if (!empty($criteria['search'])) {
            $where_conditions[] = "(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)";
            $search_term = '%' . $this->db->esc_like($criteria['search']) . '%';
            $params = array_merge($params, [$search_term, $search_term, $search_term]);
        }
        
        if (!empty($criteria['status'])) {
            $where_conditions[] = "status = %s";
            $params[] = $criteria['status'];
        }
        
        if (!empty($criteria['date_from'])) {
            $where_conditions[] = "last_visit >= %s";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $where_conditions[] = "last_visit <= %s";
            $params[] = $criteria['date_to'];
        }
        
        // ساخت query نهایی
        $table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // محاسبه pagination
        $offset = ($page - 1) * $per_page;
        
        // query برای دریافت داده
        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY last_visit DESC LIMIT %d, %d";
        $params = array_merge($params, [$offset, $per_page]);
        
        $customers = $this->db->get_results($sql, $params);
        
        // query برای تعداد کل
        $count_sql = "SELECT COUNT(*) as total FROM {$table} {$where_sql}";
        $total = $this->db->get_row($count_sql, $params)['total'] ?? 0;
        
        return [
            'customers' => array_map([$this, 'format_customer_data'], $customers),
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int)$total,
                'total_pages' => ceil($total / $per_page)
            ]
        ];
    }
    
    /**
     * افزایش تعداد مکالمات مشتری
     */
    public function increment_conversation_count(int $customer_id): bool {
        $this->validate_customer_id($customer_id);
        
        $table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        $sql = "UPDATE {$table} SET total_conversations = total_conversations + 1, updated_at = %s WHERE customer_id = %d";
        
        return (bool)$this->db->query($sql, [current_time('mysql'), $customer_id]);
    }
    
    /**
     * به روزرسانی آخرین بازدید
     */
    public function update_last_visit(int $customer_id): bool {
        $this->validate_customer_id($customer_id);
        
        $data = [
            'last_visit' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $where = ['customer_id' => $customer_id];
        
        return $this->db->update(Constants::TABLE_CUSTOMERS, $data, $where);
    }
    
    /**
     * تغییر وضعیت مشتری
     */
    public function change_status(int $customer_id, string $status): bool {
        $this->validate_customer_id($customer_id);
        
        if (!in_array($status, Constants::get_statuses())) {
            throw new \Exception('وضعیت نامعتبر');
        }
        
        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];
        
        $where = ['customer_id' => $customer_id];
        
        return $this->db->update(Constants::TABLE_CUSTOMERS, $data, $where);
    }
    
    /**
     * دریافت آمار مشتری
     */
    public function get_stats(int $customer_id): array {
        $this->validate_customer_id($customer_id);
        
        $conversations_table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        $messages_table = Constants::get_table_name(Constants::TABLE_MESSAGES);
        
        $sql = "
            SELECT 
                c.total_conversations,
                COUNT(DISTINCT conv.conversation_id) as active_conversations,
                COUNT(msg.message_id) as total_messages,
                MAX(conv.created_at) as last_conversation_date
            FROM {$this->db->get_table(Constants::TABLE_CUSTOMERS)} c
            LEFT JOIN {$conversations_table} conv ON c.customer_id = conv.customer_id
            LEFT JOIN {$messages_table} msg ON conv.conversation_id = msg.conversation_id
            WHERE c.customer_id = %d
            GROUP BY c.customer_id
        ";
        
        $stats = $this->db->get_row($sql, [$customer_id]);
        
        return [
            'total_conversations' => (int)($stats['total_conversations'] ?? 0),
            'active_conversations' => (int)($stats['active_conversations'] ?? 0),
            'total_messages' => (int)($stats['total_messages'] ?? 0),
            'last_conversation_date' => $stats['last_conversation_date'] ?? null,
            'first_visit' => $this->get_by_id($customer_id)['first_visit']
        ];
    }
    
    /**
     * آماده‌سازی داده‌های مشتری
     */
    private function prepare_customer_data(array $data, bool $is_update = false): array {
        $prepared_data = [];
        
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $data)) {
                $prepared_data[$field] = $data[$field];
            } elseif (!$is_update) {
                // مقادیر پیش‌فرض برای ایجاد جدید
                $prepared_data[$field] = $this->get_default_value($field);
            }
        }
        
        // مدیریت metadata
        if (isset($prepared_data['metadata']) && is_array($prepared_data['metadata'])) {
            $prepared_data['metadata'] = json_encode($prepared_data['metadata']);
        }
        
        return $prepared_data;
    }
    
    /**
     * دریافت مقدار پیش‌فرض برای فیلد
     */
    private function get_default_value(string $field) {
        $defaults = [
            'user_id' => 0,
            'customer_email' => null,
            'customer_name' => null,
            'customer_phone' => null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'first_visit' => current_time('mysql'),
            'last_visit' => current_time('mysql'),
            'total_conversations' => 0,
            'status' => Constants::STATUS_ACTIVE,
            'metadata' => '{}'
        ];
        
        return $defaults[$field] ?? null;
    }
    
    /**
     * فرمت داده‌های مشتری
     */
    private function format_customer_data(array $customer): array {
        // تبدیل metadata به آرایه
        if (isset($customer['metadata']) && is_string($customer['metadata'])) {
            $customer['metadata'] = json_decode($customer['metadata'], true) ?: [];
        }
        
        // تبدیل تاریخ‌ها به timestamp
        $date_fields = ['first_visit', 'last_visit', 'created_at', 'updated_at'];
        foreach ($date_fields as $field) {
            if (isset($customer[$field])) {
                $customer[$field . '_timestamp'] = strtotime($customer[$field]);
            }
        }
        
        return $customer;
    }
    
    /**
     * اعتبارسنجی داده‌های مشتری
     */
    private function validate_customer_data(array $data): void {
        // بررسی وجود visitor_id
        if (empty($data['unique_visitor_id'])) {
            throw new \Exception('unique_visitor_id الزامی است');
        }
        
        // اعتبارسنجی ایمیل
        if (!empty($data['customer_email']) && !$this->is_valid_email($data['customer_email'])) {
            throw new \Exception('فرمت ایمیل نامعتبر است');
        }
        
        // اعتبارسنجی وضعیت
        if (!empty($data['status']) && !in_array($data['status'], Constants::get_statuses())) {
            throw new \Exception('وضعیت نامعتبر');
        }
    }
    
    /**
     * اعتبارسنجی customer_id
     */
    private function validate_customer_id(int $customer_id): void {
        if ($customer_id <= 0) {
            throw new \Exception('customer_id نامعتبر');
        }
    }
}