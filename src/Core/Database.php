<?php

namespace SalnamaChat\Core;

/**
 * مدیریت ارتباط با دیتابیس
 */
class Database {
    
    use Traits\Singleton;
    
    /**
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * @var string پیشوند جداول
     */
    private $table_prefix;
    
    protected function init(): void {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix;
    }
    
    /**
     * دریافت نام کامل جدول
     */
    public function get_table(string $table_name): string {
        return $this->table_prefix . $table_name;
    }
    
    /**
     * اجرای کوئری و مدیریت خطا
     */
    public function query(string $sql, array $params = []) {
        try {
            if (!empty($params)) {
                $sql = $this->wpdb->prepare($sql, $params);
            }
            
            $result = $this->wpdb->query($sql);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Database Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * دریافت ردیف از دیتابیس
     */
    public function get_row(string $sql, array $params = []) {
        try {
            if (!empty($params)) {
                $sql = $this->wpdb->prepare($sql, $params);
            }
            
            return $this->wpdb->get_row($sql, ARRAY_A);
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Database Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت نتایج از دیتابیس
     */
    public function get_results(string $sql, array $params = []) {
        try {
            if (!empty($params)) {
                $sql = $this->wpdb->prepare($sql, $params);
            }
            
            return $this->wpdb->get_results($sql, ARRAY_A);
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Database Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * درج ردیف جدید
     */
    public function insert(string $table, array $data): int {
        try {
            $table = $this->get_table($table);
            
            $result = $this->wpdb->insert($table, $data);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $this->wpdb->insert_id;
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Database Insert Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * بروزرسانی ردیف
     */
    public function update(string $table, array $data, array $where): bool {
        try {
            $table = $this->get_table($table);
            
            $result = $this->wpdb->update($table, $data, $where);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $result > 0;
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Database Update Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * حذف ردیف
     */
    public function delete(string $table, array $where): bool {
        try {
            $table = $this->get_table($table);
            
            $result = $this->wpdb->delete($table, $where);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $result > 0;
            
        } catch (\Exception $e) {
            error_log('Salnama Chat Database Delete Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * شروع تراکنش
     */
    public function begin_transaction(): void {
        $this->wpdb->query('START TRANSACTION');
    }
    
    /**
     * commit تراکنش
     */
    public function commit(): void {
        $this->wpdb->query('COMMIT');
    }
    
    /**
     * rollback تراکنش
     */
    public function rollback(): void {
        $this->wpdb->query('ROLLBACK');
    }
    
    /**
     * بررسی وجود جدول
     */
    public function table_exists(string $table_name): bool {
        $table_name = $this->get_table($table_name);
        return $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }
}