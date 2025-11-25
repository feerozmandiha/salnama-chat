<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1 class="wp-heading-inline"><?php _e('داشبورد چت سالنمای نو', 'salnama-chat'); ?></h1>
    
    <div class="salnama-chat-dashboard">
        <!-- کارت‌های آمار -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo esc_html($stats['total_conversations']); ?></h3>
                    <p><?php _e('کل مکالمات', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo esc_html($stats['open_conversations']); ?></h3>
                    <p><?php _e('مکالمات باز', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo esc_html($stats['pending_conversations']); ?></h3>
                    <p><?php _e('در انتظار پاسخ', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo esc_html($stats['resolved_conversations']); ?></h3>
                    <p><?php _e('حل شده', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo esc_html($stats['total_customers']); ?></h3>
                    <p><?php _e('کل مشتریان', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-performance"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo esc_html(round($stats['avg_resolution_time'], 1)); ?>m</h3>
                    <p><?php _e('میانگین زمان پاسخ', 'salnama-chat'); ?></p>
                </div>
            </div>
        </div>

        <!-- مکالمات اخیر -->
        <div class="dashboard-row">
            <div class="dashboard-col-8">
                <div class="card">
                    <div class="card-header">
                        <h3><?php _e('مکالمات نیازمند پاسخ', 'salnama-chat'); ?></h3>
                        <a href="<?php echo admin_url('admin.php?page=salnama-chat-conversations&status=open'); ?>" 
                           class="button button-primary">
                            <?php _e('مشاهده همه', 'salnama-chat'); ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_conversations['conversations'])): ?>
                            <div class="conversations-list">
                                <?php foreach ($recent_conversations['conversations'] as $conversation): ?>
                                    <div class="conversation-item" data-conversation-id="<?php echo esc_attr($conversation['conversation_id']); ?>">
                                        <div class="conversation-avatar">
                                            <?php echo get_avatar($conversation['customer_email'] ?? '', 40, '', '', ['class' => 'avatar']); ?>
                                        </div>
                                        <div class="conversation-details">
                                            <div class="conversation-header">
                                                <h4><?php echo esc_html($conversation['customer_name'] ?: 'مشتری ناشناس'); ?></h4>
                                                <span class="conversation-time">
                                                    <?php echo human_time_diff(strtotime($conversation['created_at']), current_time('timestamp')); ?>
                                                    <?php _e('پیش', 'salnama-chat'); ?>
                                                </span>
                                            </div>
                                            <p class="conversation-preview">
                                                <?php echo esc_html($conversation['subject']); ?>
                                            </p>
                                            <div class="conversation-meta">
                                                <span class="priority-badge priority-<?php echo esc_attr($conversation['priority']); ?>">
                                                    <?php echo $this->get_priority_label($conversation['priority']); ?>
                                                </span>
                                                <?php if (!$conversation['operator_id']): ?>
                                                    <span class="unassigned-badge"><?php _e('اختصاص داده نشده', 'salnama-chat'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="conversation-actions">
                                            <button class="button button-small start-chat" 
                                                    data-conversation-id="<?php echo esc_attr($conversation['conversation_id']); ?>">
                                                <?php _e('شروع چت', 'salnama-chat'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-conversations">
                                <p><?php _e('هیچ مکالمه‌ای نیازمند پاسخ نیست.', 'salnama-chat'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-col-4">
                <!-- اپراتورهای آنلاین -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php _e('اپراتورهای آنلاین', 'salnama-chat'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="online-operators" id="online-operators-list">
                            <!-- لیست اپراتورها با AJAX پر می‌شود -->
                        </div>
                    </div>
                </div>
                
                <!-- فعالیت اخیر -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php _e('فعالیت اخیر', 'salnama-chat'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="recent-activity" id="recent-activity-list">
                            <!-- فعالیت‌ها با AJAX پر می‌شود -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال چت -->
<div id="chat-modal" class="salnama-chat-modal" style="display: none;">
    <div class="chat-modal-header">
        <h3 id="chat-modal-title"><?php _e('چت با مشتری', 'salnama-chat'); ?></h3>
        <button type="button" class="close-chat-modal">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="chat-modal-body">
        <div id="chat-window">
            <!-- محتوای چت اینجا لود می‌شود -->
        </div>
    </div>
    <div class="chat-modal-footer">
        <div class="chat-input-container">
            <textarea id="chat-message-input" placeholder="<?php _e('پیام خود را بنویسید...', 'salnama-chat'); ?>" rows="3"></textarea>
            <button id="send-chat-message" class="button button-primary">
                <?php _e('ارسال', 'salnama-chat'); ?>
            </button>
        </div>
    </div>
</div>