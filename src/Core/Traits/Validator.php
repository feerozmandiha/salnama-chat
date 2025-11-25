<?php

namespace SalnamaChat\Traits;

/**
 * Trait برای اعتبارسنجی داده‌ها
 */
trait Validator {
    
    /**
     * اعتبارسنجی ایمیل
     */
    protected function is_valid_email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * اعتبارسنجی شماره تلفن (ساده)
     */
    protected function is_valid_phone(string $phone): bool {
        // الگوی ساده برای شماره تلفن ایرانی
        $pattern = '/^09[0-9]{9}$/';
        return preg_match($pattern, $phone) === 1;
    }
    
    /**
     * اعتبارسنجی طول رشته
     */
    protected function validate_length(string $string, int $min, int $max): bool {
        $length = mb_strlen($string, 'UTF-8');
        return $length >= $min && $length <= $max;
    }
    
    /**
     * اعتبارسنجی عدد
     */
    protected function is_valid_number($number, $min = null, $max = null): bool {
        if (!is_numeric($number)) {
            return false;
        }
        
        $number = (float)$number;
        
        if ($min !== null && $number < $min) {
            return false;
        }
        
        if ($max !== null && $number > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * اعتبارسنجی تاریخ
     */
    protected function is_valid_date(string $date, string $format = 'Y-m-d H:i:s'): bool {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * پاکسازی رشته
     */
    protected function sanitize_string(string $string): string {
        return trim(sanitize_text_field($string));
    }
    
    /**
     * پاکسازی متن طولانی
     */
    protected function sanitize_text(string $text): string {
        return trim(wp_kses_post($text));
    }
    
    /**
     * اعتبارسنجی آپلود فایل
     */
    protected function validate_file_upload(array $file): array {
        $errors = [];
        
        // بررسی خطاهای آپلود
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->get_upload_error_message($file['error']);
        }
        
        // بررسی نوع فایل
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'نوع فایل مجاز نیست';
        }
        
        // بررسی سایز فایل
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            $errors[] = 'حجم فایل بیش از حد مجاز است';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * دریافت پیام خطای آپلود
     */
    private function get_upload_error_message(int $error_code): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم فایل بیش از حد مجاز سرور است',
            UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز فرم است',
            UPLOAD_ERR_PARTIAL => 'فایل به طور کامل آپلود نشد',
            UPLOAD_ERR_NO_FILE => 'هیچ فایلی آپلود نشد',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت یافت نشد',
            UPLOAD_ERR_CANT_WRITE => 'خطا در ذخیره فایل',
            UPLOAD_ERR_EXTENSION => 'افزونه PHP آپلود فایل را متوقف کرد'
        ];
        
        return $messages[$error_code] ?? 'خطای ناشناخته در آپلود فایل';
    }
}