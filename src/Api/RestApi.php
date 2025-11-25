<?php

namespace SalnamaChat\Api;

use SalnamaChat\Services\ConversationService;
use SalnamaChat\Services\CustomerService;
use SalnamaChat\Core\Constants;

/**
 * مدیریت REST API پلاگین
 */
class RestApi {
    
    private $conversation_service;
    private $customer_service;
    
    public function __construct(ConversationService $conversation_service, CustomerService $customer_service) {
        $this->conversation_service = $conversation_service;
        $this->customer_service = $customer_service;
    }
    
    /**
     * راه‌اندازی API
     */
    public function init(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * ثبت routeهای API
     */
    public function register_routes(): void {
        // Route برای مکالمات
        register_rest_route(Constants::API_NAMESPACE, '/conversations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_conversations'],
                'permission_callback' => [$this, 'check_operator_permission'],
                'args' => [
                    'status' => [
                        'required' => false,
                        'validate_callback' => function($param) {
                            return in_array($param, ['open', 'pending', 'closed', 'resolved']);
                        }
                    ],
                    'page' => [
                        'required' => false,
                        'default' => 1,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    ],
                    'per_page' => [
                        'required' => false,
                        'default' => 20,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0 && $param <= 100;
                        }
                    ]
                ]
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_conversation'],
                'permission_callback' => [$this, 'check_customer_permission'],
            ]
        ]);
        
        // Route برای یک مکالمه خاص
        register_rest_route(Constants::API_NAMESPACE, '/conversations/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_conversation'],
                'permission_callback' => [$this, 'check_conversation_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_conversation'],
                'permission_callback' => [$this, 'check_operator_permission'],
            ]
        ]);
        
        // Route برای پیام‌ها
        register_rest_route(Constants::API_NAMESPACE, '/conversations/(?P<id>\d+)/messages', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_messages'],
                'permission_callback' => [$this, 'check_conversation_permission'],
                'args' => [
                    'page' => [
                        'required' => false,
                        'default' => 1,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    ],
                    'per_page' => [
                        'required' => false,
                        'default' => 50,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0 && $param <= 100;
                        }
                    ]
                ]
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_message'],
                'permission_callback' => [$this, 'check_conversation_permission'],
            ]
        ]);
        
        // Route برای مشتریان
        register_rest_route(Constants::API_NAMESPACE, '/customers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_customers'],
                'permission_callback' => [$this, 'check_operator_permission'],
                'args' => [
                    'search' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'page' => [
                        'required' => false,
                        'default' => 1,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    ],
                    'per_page' => [
                        'required' => false,
                        'default' => 20,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0 && $param <= 100;
                        }
                    ]
                ]
            ]
        ]);
        
        // Route برای یک مشتری خاص
        register_rest_route(Constants::API_NAMESPACE, '/customers/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_customer'],
                'permission_callback' => [$this, 'check_operator_permission'],
            ]
        ]);
        
        // Route برای آمار
        register_rest_route(Constants::API_NAMESPACE, '/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_operator_permission'],
                'args' => [
                    'date_from' => [
                        'required' => false,
                        'validate_callback' => function($param) {
                            return strtotime($param) !== false;
                        }
                    ],
                    'date_to' => [
                        'required' => false,
                        'validate_callback' => function($param) {
                            return strtotime($param) !== false;
                        }
                    ]
                ]
            ]
        ]);
    }
    
    /**
     * دریافت لیست مکالمات
     */
    public function get_conversations(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $status = $request->get_param('status') ?? 'open';
            $page = $request->get_param('page') ?? 1;
            $per_page = $request->get_param('per_page') ?? 20;
            
            $conversations_data = $this->conversation_service->get_by_status($status, $page, $per_page);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversations_data
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ایجاد مکالمه جدید
     */
    public function create_conversation(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $subject = $request->get_param('subject') ?? 'مکالمه جدید';
            $initial_message = $request->get_param('message') ?? '';
            $priority = $request->get_param('priority') ?? Constants::PRIORITY_MEDIUM;
            
            // شناسایی مشتری
            $customer = $this->customer_service->identify_customer();
            
            // شروع مکالمه جدید
            $conversation = $this->conversation_service->start_conversation($customer['customer_id'], [
                'subject' => $subject,
                'priority' => $priority,
                'initial_message' => $initial_message
            ]);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversation
            ], 201);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * دریافت اطلاعات یک مکالمه
     */
    public function get_conversation(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $conversation_id = (int) $request->get_param('id');
            $page = $request->get_param('page') ?? 1;
            
            $data = $this->conversation_service->get_conversation_with_messages($conversation_id, $page);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $data
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * به روزرسانی مکالمه
     */
    public function update_conversation(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $conversation_id = (int) $request->get_param('id');
            $update_data = $request->get_json_params();
            
            $conversation = $this->conversation_service->update_conversation($conversation_id, $update_data);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversation
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * دریافت پیام‌های یک مکالمه
     */
    public function get_messages(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $conversation_id = (int) $request->get_param('id');
            $page = $request->get_param('page') ?? 1;
            $per_page = $request->get_param('per_page') ?? 50;
            
            $messages_data = $this->conversation_service->get_conversation_messages($conversation_id, $page, $per_page);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $messages_data
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ارسال پیام
     */
    public function send_message(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $conversation_id = (int) $request->get_param('id');
            $message_content = $request->get_param('message') ?? '';
            $sender_type = $request->get_param('sender_type') ?? 'customer';
            $attachment = $request->get_param('attachment') ?? [];
            
            if (empty($message_content) && empty($attachment)) {
                throw new \Exception('پیام یا فایل پیوست الزامی است');
            }
            
            // تشخیص sender_id بر اساس نوع فرستنده
            if ($sender_type === 'operator') {
                $sender_id = get_current_user_id();
            } else {
                $customer = $this->customer_service->identify_customer();
                $sender_id = $customer['customer_id'];
            }
            
            $message = $this->conversation_service->send_message(
                $conversation_id,
                $sender_id,
                $sender_type,
                $message_content,
                $attachment
            );
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $message
            ], 201);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * دریافت لیست مشتریان
     */
    public function get_customers(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $search = $request->get_param('search') ?? '';
            $page = $request->get_param('page') ?? 1;
            $per_page = $request->get_param('per_page') ?? 20;
            
            $filters = [];
            if (!empty($search)) {
                $filters['search'] = $search;
            }
            
            $customers_data = $this->customer_service->search_customers($filters, $page, $per_page);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $customers_data
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * دریافت اطلاعات یک مشتری
     */
    public function get_customer(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $customer_id = (int) $request->get_param('id');
            
            $customer_profile = $this->customer_service->get_customer_profile($customer_id);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $customer_profile
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * دریافت آمار
     */
    public function get_stats(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');
            
            $filters = [];
            if (!empty($date_from)) {
                $filters['date_from'] = $date_from;
            }
            if (!empty($date_to)) {
                $filters['date_to'] = $date_to;
            }
            
            $conversation_stats = $this->conversation_service->get_conversations_stats($filters);
            $customer_stats = $this->customer_service->get_customers_stats();
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'conversations' => $conversation_stats,
                    'customers' => $customer_stats
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * بررسی دسترسی اپراتور
     */
    public function check_operator_permission(\WP_REST_Request $request): bool {
        return current_user_can(Constants::REQUIRED_WP_CAPABILITY) || 
               current_user_can('salnama_chat_manage_conversations');
    }
    
    /**
     * بررسی دسترسی مشتری
     */
    public function check_customer_permission(\WP_REST_Request $request): bool {
        // برای مشتریان، می‌توانیم از nonce یا روش‌های دیگر استفاده کنیم
        // در اینجا به صورت ساده اجازه می‌دهیم
        return true;
    }
    
    /**
     * بررسی دسترسی به مکالمه
     */
    public function check_conversation_permission(\WP_REST_Request $request): bool {
        $conversation_id = (int) $request->get_param('id');
        
        // اگر کاربر اپراتور است
        if ($this->check_operator_permission($request)) {
            return true;
        }
        
        // اگر کاربر مشتری است، بررسی کنیم که مکالمه متعلق به او باشد
        try {
            $customer = $this->customer_service->identify_customer();
            $conversation = $this->conversation_service->get_conversation($conversation_id);
            
            return $conversation['customer_id'] === $customer['customer_id'];
            
        } catch (\Exception $e) {
            return false;
        }
    }
}