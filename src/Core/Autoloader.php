<?php

namespace SalnamaChat\Core;

class Autoloader {
    
    /**
     * Namespace اصلی پلاگین
     */
    private const MAIN_NAMESPACE = 'SalnamaChat\\';
    
    /**
     * مسیر اصلی فایل‌های کلاس
     */
    private const SOURCE_DIR = __DIR__ . '/../';
    
    /**
     * ثبت Autoloader
     */
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * تابع Autoload
     */
    public static function autoload(string $className): void {
        // فقط کلاس‌های مربوط به namespace ما را مدیریت کن
        if (strpos($className, self::MAIN_NAMESPACE) !== 0) {
            return;
        }
        
        // تبدیل namespace به مسیر فایل
        $relativeClass = substr($className, strlen(self::MAIN_NAMESPACE));
        $classFile = self::SOURCE_DIR . str_replace('\\', '/', $relativeClass) . '.php';
        
        // اگر فایل وجود دارد، آن را بارگذاری کن
        if (file_exists($classFile) && is_readable($classFile)) {
            require_once $classFile;
        } else {
            // لاگ کردن برای دیباگ
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Salnama Chat Autoloader: File not found - {$classFile}");
            }
        }
    }
}