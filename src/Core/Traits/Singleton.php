<?php

namespace SalnamaChat\Core\Traits;

trait Singleton {
    
    private static $instance = null;
    
    final public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    final private function __construct() {
        $this->init();
    }
    
    protected function init() {}
    
    final private function __clone() {}
    
    final public function __wakeup() {}
}