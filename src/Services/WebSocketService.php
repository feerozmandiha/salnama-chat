<?php

namespace SalnamaChat\Services;

use SalnamaChat\Core\Constants;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

/**
 * سرویس WebSocket برای ارتباط real-time
 */
class WebSocketService implements MessageComponentInterface {
    
    private $clients;
    private $conversation_service;
    private $customer_service;
    private $server;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->conversation_service = new ConversationService();
        $this->customer_service = new CustomerService();
    }
    
    /**
     * راه‌اندازی سرویس
     */
    public function init(): void {
        // فقط در محیط production سرور WebSocket را راه‌اندازی کن
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return;
        }
        
        add_action('wp_loaded', [$this, 'start_websocket_server']);
    }
    
    /**
     * شروع سرور WebSocket
     */
    public function start_websocket_server(): void {
        if (php_sapi_name() !== 'cli') {
            return;
        }
        
        $port = Constants::WS_PORT;
        $host = Constants::WS_HOST;
        
        try {
            $this->server = new IoServer(
                new HttpServer(
                    new WsServer($this)
                ),
                new \React\Socket\Server("{$host}:{$port}")
            );
            
            echo "WebSocket server running on {$host}:{$port}\n";
            $this->server->run();
            
        } catch (\Exception $e) {
            error_log('WebSocket Server Error: ' . $e->getMessage());
        }
    }
    
    /**
     * هنگام اتصال جدید
     */
    public function onOpen(ConnectionInterface $conn): void {
        $this->clients->attach($conn);
        
        $query_string = $conn->httpRequest->getUri()->getQuery();
        parse_str($query_string, $query_params);
        
        // ذخیره اطلاعات کلاینت
        $conn->client_info = [
            'type' => $query_params['type'] ?? 'unknown', // customer یا operator
            'user_id' => (int)($query_params['user_id'] ?? 0),
            'conversation_id' => (int)($query_params['conversation_id'] ?? 0),
            'connected_at' => time()
        ];
        
        error_log("New connection: {$conn->resourceId} - Type: {$conn->client_info['type']}");
    }
    
    /**
     * هنگام دریافت پیام
     */
    public function onMessage(ConnectionInterface $from, $msg): void {
        try {
            $data = json_decode($msg, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format');
            }
            
            $action = $data['action'] ?? '';
            $payload = $data['payload'] ?? [];
            
            switch ($action) {
                case 'authenticate':
                    $this->handle_authentication($from, $payload);
                    break;
                    
                case 'send_message':
                    $this->handle_send_message($from, $payload);
                    break;
                    
                case 'typing_start':
                    $this->handle_typing_indicator($from, $payload, true);
                    break;
                    
                case 'typing_stop':
                    $this->handle_typing_indicator($from, $payload, false);
                    break;
                    
                case 'join_conversation':
                    $this->handle_join_conversation($from, $payload);
                    break;
                    
                case 'leave_conversation':
                    $this->handle_leave_conversation($from, $payload);
                    break;
                    
                default:
                    $this->send_error($from, 'Action not supported');
            }
            
        } catch (\Exception $e) {
            error_log('WebSocket Message Error: ' . $e->getMessage());
            $this->send_error($from, $e->getMessage());
        }
    }
    
    /**
     * هنگام بسته شدن اتصال
     */
    public function onClose(ConnectionInterface $conn): void {
        $this->clients->detach($conn);
        
        // اطلاع‌رسانی به سایر کاربران در مکالمه
        if (isset($conn->client_info['conversation_id'])) {
            $this->broadcast_to_conversation(
                $conn->client_info['conversation_id'],
                $conn->resourceId,
                [
                    'action' => 'user_disconnected',
                    'payload' => [
                        'user_id' => $conn->client_info['user_id'],
                        'user_type' => $conn->client_info['type']
                    ]
                ]
            );
        }
        
        error_log("Connection closed: {$conn->resourceId}");
    }
    
    /**
     * هنگام خطا
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void {
        error_log("WebSocket Error: {$e->getMessage()}");
        $conn->close();
    }
    
    /**
     * مدیریت احراز هویت
     */
    private function handle_authentication(ConnectionInterface $conn, array $payload): void {
        $token = $payload['token'] ?? '';
        $type = $payload['type'] ?? '';
        $conversation_id = (int)($payload['conversation_id'] ?? 0);
        
        try {
            if ($type === 'operator') {
                $this->authenticate_operator($conn, $token, $conversation_id);
            } elseif ($type === 'customer') {
                $this->authenticate_customer($conn, $token, $conversation_id);
            } else {
                throw new \Exception('Invalid user type');
            }
            
            $this->send_success($conn, 'authenticated', [
                'user_id' => $conn->client_info['user_id'],
                'conversation_id' => $conn->client_info['conversation_id']
            ]);
            
        } catch (\Exception $e) {
            $this->send_error($conn, 'Authentication failed: ' . $e->getMessage());
            $conn->close();
        }
    }
    
    /**
     * احراز هویت اپراتور
     */
    private function authenticate_operator(ConnectionInterface $conn, string $token, int $conversation_id): void {
        // در اینجا باید token validation انجام شود
        // برای سادگی، فرض می‌کنیم token معتبر است
        
        $user_id = (int)$token; // در واقعیت باید decode شود
        
        if (!current_user_can('salnama_chat_manage_conversations')) {
            throw new \Exception('Operator access denied');
        }
        
        $conn->client_info['user_id'] = $user_id;
        $conn->client_info['type'] = 'operator';
        $conn->client_info['conversation_id'] = $conversation_id;
    }
    
    /**
     * احراز هویت مشتری
     */
    private function authenticate_customer(ConnectionInterface $conn, string $visitor_id, int $conversation_id): void {
        $customer = $this->customer_service->identify_customer(['visitor_id' => $visitor_id]);
        
        $conn->client_info['user_id'] = $customer['customer_id'];
        $conn->client_info['type'] = 'customer';
        $conn->client_info['conversation_id'] = $conversation_id;
    }
    
    /**
     * مدیریت ارسال پیام
     */
    private function handle_send_message(ConnectionInterface $from, array $payload): void {
        $conversation_id = $from->client_info['conversation_id'];
        $sender_id = $from->client_info['user_id'];
        $sender_type = $from->client_info['type'];
        $message_content = $payload['message'] ?? '';
        $attachment = $payload['attachment'] ?? [];
        
        if (empty($message_content) && empty($attachment)) {
            throw new \Exception('Message content or attachment required');
        }
        
        // ارسال پیام به دیتابیس
        $message = $this->conversation_service->send_message(
            $conversation_id,
            $sender_id,
            $sender_type,
            $message_content,
            $attachment
        );
        
        // broadcast پیام به تمام شرکت‌کنندگان مکالمه
        $this->broadcast_to_conversation($conversation_id, $from->resourceId, [
            'action' => 'new_message',
            'payload' => $message
        ]);
        
        // ارسال تأیید به فرستنده
        $this->send_success($from, 'message_sent', $message);
    }
    
    /**
     * مدیریت نشانگر تایپینگ
     */
    private function handle_typing_indicator(ConnectionInterface $from, array $payload, bool $is_typing): void {
        $conversation_id = $from->client_info['conversation_id'];
        $user_id = $from->client_info['user_id'];
        $user_type = $from->client_info['type'];
        
        $this->broadcast_to_conversation($conversation_id, $from->resourceId, [
            'action' => $is_typing ? 'user_typing_start' : 'user_typing_stop',
            'payload' => [
                'user_id' => $user_id,
                'user_type' => $user_type
            ]
        ]);
    }
    
    /**
     * مدیریت پیوستن به مکالمه
     */
    private function handle_join_conversation(ConnectionInterface $conn, array $payload): void {
        $conversation_id = (int)($payload['conversation_id'] ?? 0);
        
        if ($conversation_id <= 0) {
            throw new \Exception('Invalid conversation ID');
        }
        
        $conn->client_info['conversation_id'] = $conversation_id;
        
        // اطلاع‌رسانی به سایر کاربران
        $this->broadcast_to_conversation($conversation_id, $conn->resourceId, [
            'action' => 'user_joined',
            'payload' => [
                'user_id' => $conn->client_info['user_id'],
                'user_type' => $conn->client_info['type']
            ]
        ]);
        
        $this->send_success($conn, 'joined_conversation', [
            'conversation_id' => $conversation_id
        ]);
    }
    
    /**
     * مدیریت ترک مکالمه
     */
    private function handle_leave_conversation(ConnectionInterface $conn, array $payload): void {
        $conversation_id = $conn->client_info['conversation_id'];
        
        if ($conversation_id) {
            $this->broadcast_to_conversation($conversation_id, $conn->resourceId, [
                'action' => 'user_left',
                'payload' => [
                    'user_id' => $conn->client_info['user_id'],
                    'user_type' => $conn->client_info['type']
                ]
            ]);
            
            $conn->client_info['conversation_id'] = 0;
        }
        
        $this->send_success($conn, 'left_conversation');
    }
    
    /**
     * ارسال پیام به تمام شرکت‌کنندگان یک مکالمه
     */
    private function broadcast_to_conversation(int $conversation_id, int $exclude_connection_id, array $message): void {
        foreach ($this->clients as $client) {
            if ($client->resourceId === $exclude_connection_id) {
                continue;
            }
            
            if (isset($client->client_info['conversation_id']) && 
                $client->client_info['conversation_id'] === $conversation_id) {
                $client->send(json_encode($message));
            }
        }
    }
    
    /**
     * ارسال پیام موفقیت
     */
    private function send_success(ConnectionInterface $conn, string $action, array $data = []): void {
        $conn->send(json_encode([
            'status' => 'success',
            'action' => $action,
            'data' => $data,
            'timestamp' => time()
        ]));
    }
    
    /**
     * ارسال پیام خطا
     */
    private function send_error(ConnectionInterface $conn, string $message): void {
        $conn->send(json_encode([
            'status' => 'error',
            'message' => $message,
            'timestamp' => time()
        ]));
    }
    
    /**
     * دریافت لیست کاربران آنلاین در یک مکالمه
     */
    public function get_online_users(int $conversation_id): array {
        $online_users = [];
        
        foreach ($this->clients as $client) {
            if (isset($client->client_info['conversation_id']) && 
                $client->client_info['conversation_id'] === $conversation_id) {
                $online_users[] = [
                    'user_id' => $client->client_info['user_id'],
                    'user_type' => $client->client_info['type'],
                    'connected_at' => $client->client_info['connected_at']
                ];
            }
        }
        
        return $online_users;
    }
    
    /**
     * ارسال پیام به کاربر خاص
     */
    public function send_to_user(int $user_id, string $user_type, array $message): void {
        foreach ($this->clients as $client) {
            if (isset($client->client_info['user_id']) && 
                $client->client_info['user_id'] === $user_id &&
                $client->client_info['type'] === $user_type) {
                $client->send(json_encode($message));
            }
        }
    }
}