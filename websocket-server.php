<?php
/**
 * فایل راه‌اندازی سرور WebSocket
 * اجرا با دستور: php websocket-server.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Autoloader.php';

use SalnamaChat\Core\Autoloader;
use SalnamaChat\Services\WebSocketService;

// ثبت autoloader
Autoloader::register();

// راه‌اندازی سرور WebSocket
$websocket_service = new WebSocketService();
$websocket_service->start_websocket_server();