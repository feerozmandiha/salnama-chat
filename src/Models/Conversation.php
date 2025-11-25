<?php

namespace SalnamaChat\Models;

use SalnamaChat\Core\Constants;
use SalnamaChat\Core\Database;
use SalnamaChat\Traits\Validator;

/**
 * مدل مدیریت مکالمات
 */
class Conversation {
    
    use Validator;
    
    private $db;
    private $customer_model;
    
    private $fields = [
        'conversation_id', 'customer_id', 'operator_id', 'subject', 'status',
        'priority', 'rating', 'tags', 'created_at', 'updated_at', 'closed_at'
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->customer_model = new Customer();
    }
    
    /**
     * ایجاد مکالمه جدید
     */
    public function create(array $data): array {
        $this->db->begin_transaction();
        
        try {
            // اعتبارسنجی داده‌ها
            $this->validate_conversation_data($data);
            
            // آماده‌سازی داده‌ها
            $conversation_data = $this->prepare_conversation_data($data);
            
            // ایجاد مکالمه
            $conversation_id = $this->db->insert(Constants::TABLE_CONVERSATIONS, $conversation_data);
            
            if (!$conversation_id) {
                throw new \Exception('خطا در ایجاد مکالمه جدید');
            }
            
            // افزایش تعداد مکالمات مشتری
            $this->customer_model->increment_conversation_count($data['customer_id']);
            
            $this->db->commit();
            
            return $this->get_by_id($conversation_id);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * به روزرسانی مکالمه
     */
    public function update(int $conversation_id, array $data): array {
        $this->validate_conversation_id($conversation_id);
        
        $update_data = $this->prepare_conversation_data($data, true);
        $update_data['updated_at'] = current_time('mysql');
        
        $where = ['conversation_id' => $conversation_id];
        
        $result = $this->db->update(Constants::TABLE_CONVERSATIONS, $update_data, $where);
        
        if (!$result) {
            throw new \Exception('خطا در به روزرسانی مکالمه');
        }
        
        return $this->get_by_id($conversation_id);
    }
    
    /**
     * بستن مکالمه
     */
    public function close(int $conversation_id, int $operator_id = 0): array {
        $data = [
            'status' => Constants::CONVERSATION_CLOSED,
            'closed_at' => current_time('mysql')
        ];
        
        if ($operator_id > 0) {
            $data['operator_id'] = $operator_id;
        }
        
        return $this->update($conversation_id, $data);
    }
    
    /**
     * دریافت مکالمه بر اساس ID
     */
    public function get_by_id(int $conversation_id): array {
        $this->validate_conversation_id($conversation_id);
        
        $table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        $customers_table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        
        $sql = "
            SELECT c.*, cust.customer_name, cust.customer_email, cust.customer_phone
            FROM {$table} c
            LEFT JOIN {$customers_table} cust ON c.customer_id = cust.customer_id
            WHERE c.conversation_id = %d
        ";
        
        $conversation = $this->db->get_row($sql, [$conversation_id]);
        
        if (!$conversation) {
            throw new \Exception('مکالمه یافت نشد');
        }
        
        return $this->format_conversation_data($conversation);
    }
    
    /**
     * دریافت مکالمات فعال یک مشتری
     */
    public function get_active_by_customer(int $customer_id): array {
        $table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        $sql = "SELECT * FROM {$table} WHERE customer_id = %d AND status IN ('open', 'pending') ORDER BY created_at DESC";
        
        $conversations = $this->db->get_results($sql, [$customer_id]);
        
        return array_map([$this, 'format_conversation_data'], $conversations);
    }
    
    /**
     * دریافت مکالمات بر اساس وضعیت
     */
    public function get_by_status(string $status, int $page = 1, int $per_page = 20): array {
        if (!in_array($status, ['open', 'pending', 'closed', 'resolved'])) {
            throw new \Exception('وضعیت نامعتبر');
        }
        
        $table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        $customers_table = Constants::get_table_name(Constants::TABLE_CUSTOMERS);
        
        $offset = ($page - 1) * $per_page;
        
        $sql = "
            SELECT c.*, cust.customer_name, cust.customer_email, cust.customer_phone
            FROM {$table} c
            LEFT JOIN {$customers_table} cust ON c.customer_id = cust.customer_id
            WHERE c.status = %s
            ORDER BY c.updated_at DESC
            LIMIT %d, %d
        ";
        
        $conversations = $this->db->get_results($sql, [$status, $offset, $per_page]);
        
        // تعداد کل
        $count_sql = "SELECT COUNT(*) as total FROM {$table} WHERE status = %s";
        $total = $this->db->get_row($count_sql, [$status])['total'] ?? 0;
        
        return [
            'conversations' => array_map([$this, 'format_conversation_data'], $conversations),
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int)$total,
                'total_pages' => ceil($total / $per_page)
            ]
        ];
    }
    
    /**
     * اختصاص مکالمه به اپراتور
     */
    public function assign_to_operator(int $conversation_id, int $operator_id): array {
        $this->validate_conversation_id($conversation_id);
        
        if ($operator_id <= 0) {
            throw new \Exception('operator_id نامعتبر');
        }
        
        $data = [
            'operator_id' => $operator_id,
            'status' => Constants::CONVERSATION_PENDING,
            'updated_at' => current_time('mysql')
        ];
        
        return $this->update($conversation_id, $data);
    }
    
    /**
     * دریافت آمار مکالمات
     */
    public function get_stats(array $filters = []): array {
        $table = Constants::get_table_name(Constants::TABLE_CONVERSATIONS);
        
        $where_conditions = [];
        $params = [];
        
        // فیلترهای تاریخ
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "
            SELECT 
                COUNT(*) as total_conversations,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_conversations,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_conversations,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_conversations,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_conversations,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) as avg_resolution_time,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM {$table}
            {$where_sql}
        ";
        
        $stats = $this->db->get_row($sql, $params);
        
        return [
            'total_conversations' => (int)($stats['total_conversations'] ?? 0),
            'open_conversations' => (int)($stats['open_conversations'] ?? 0),
            'pending_conversations' => (int)($stats['pending_conversations'] ?? 0),
            'closed_conversations' => (int)($stats['closed_conversations'] ?? 0),
            'resolved_conversations' => (int)($stats['resolved_conversations'] ?? 0),
            'avg_resolution_time' => (float)($stats['avg_resolution_time'] ?? 0),
            'unique_customers' => (int)($stats['unique_customers'] ?? 0)
        ];
    }
    
    /**
     * آماده‌سازی داده‌های مکالمه
     */
    private function prepare_conversation_data(array $data, bool $is_update = false): array {
        $prepared_data = [];
        
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $data)) {
                $prepared_data[$field] = $data[$field];
            } elseif (!$is_update) {
                $prepared_data[$field] = $this->get_default_value($field);
            }
        }
        
        // مدیریت tags
        if (isset($prepared_data['tags']) && is_array($prepared_data['tags'])) {
            $prepared_data['tags'] = implode(',', $prepared_data['tags']);
        }
        
        return $prepared_data;
    }
    
    /**
     * دریافت مقدار پیش‌فرض برای فیلد
     */
    private function get_default_value(string $field) {
        $defaults = [
            'operator_id' => 0,
            'subject' => '',
            'status' => Constants::CONVERSATION_OPEN,
            'priority' => Constants::PRIORITY_MEDIUM,
            'rating' => null,
            'tags' => '',
            'closed_at' => null
        ];
        
        return $defaults[$field] ?? null;
    }
    
    /**
     * فرمت داده‌های مکالمه
     */
    private function format_conversation_data(array $conversation): array {
        // تبدیل tags به آرایه
        if (isset($conversation['tags']) && is_string($conversation['tags'])) {
            $conversation['tags'] = !empty($conversation['tags']) ? explode(',', $conversation['tags']) : [];
        }
        
        // تبدیل تاریخ‌ها به timestamp
        $date_fields = ['created_at', 'updated_at', 'closed_at'];
        foreach ($date_fields as $field) {
            if (isset($conversation[$field])) {
                $conversation[$field . '_timestamp'] = strtotime($conversation[$field]);
            }
        }
        
        return $conversation;
    }
    
    /**
     * اعتبارسنجی داده‌های مکالمه
     */
    private function validate_conversation_data(array $data): void {
        if (empty($data['customer_id'])) {
            throw new \Exception('customer_id الزامی است');
        }
        
        if (!empty($data['status']) && !in_array($data['status'], Constants::get_conversation_statuses())) {
            throw new \Exception('وضعیت مکالمه نامعتبر');
        }
        
        if (!empty($data['priority']) && !in_array($data['priority'], Constants::get_priorities())) {
            throw new \Exception('اولویت نامعتبر');
        }
    }
    
    /**
     * اعتبارسنجی conversation_id
     */
    private function validate_conversation_id(int $conversation_id): void {
        if ($conversation_id <= 0) {
            throw new \Exception('conversation_id نامعتبر');
        }
    }
}