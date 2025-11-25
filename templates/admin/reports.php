<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1 class="wp-heading-inline"><?php _e('گزارشات چت', 'salnama-chat'); ?></h1>
    
    <div class="reports-filters">
        <form method="get" class="report-filter-form">
            <input type="hidden" name="page" value="salnama-chat-reports">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="date_from"><?php _e('از تاریخ:', 'salnama-chat'); ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>" class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label for="date_to"><?php _e('تا تاریخ:', 'salnama-chat'); ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>" class="filter-input">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="button button-primary"><?php _e('اعمال فیلتر', 'salnama-chat'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=salnama-chat-reports'); ?>" class="button"><?php _e('حذف فیلتر', 'salnama-chat'); ?></a>
                </div>
            </div>
        </form>
    </div>

    <div class="reports-stats">
        <h2><?php _e('آمار کلی مکالمات', 'salnama-chat'); ?></h2>
        
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">💬</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($conversation_stats['total_conversations']); ?></h3>
                    <p><?php _e('کل مکالمات', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($conversation_stats['open_conversations']); ?></h3>
                    <p><?php _e('مکالمات باز', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($conversation_stats['resolved_conversations']); ?></h3>
                    <p><?php _e('حل شده', 'salnama-chat'); ?></p>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">⏱️</div>
                <div class="stat-content">
                    <h3><?php echo esc_html(round($conversation_stats['avg_resolution_time'], 1)); ?>m</h3>
                    <p><?php _e('میانگین زمان پاسخ', 'salnama-chat'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="detailed-stats">
            <div class="stats-row">
                <div class="stats-col">
                    <h3><?php _e('آمار مشتریان', 'salnama-chat'); ?></h3>
                    <div class="stats-list">
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('کل مشتریان:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($customer_stats['total_customers']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('مشتریان فعال:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($customer_stats['active_customers']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('مشتریان مسدود:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($customer_stats['blocked_customers']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('میانگین مکالمات:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($customer_stats['avg_conversations_per_customer']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stats-col">
                    <h3><?php _e('وضعیت مکالمات', 'salnama-chat'); ?></h3>
                    <div class="stats-list">
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('در حال انجام:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($conversation_stats['pending_conversations']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('بسته شده:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($conversation_stats['closed_conversations']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('مشتریان منحصر به فرد:', 'salnama-chat'); ?></span>
                            <span class="stat-value"><?php echo esc_html($conversation_stats['unique_customers']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>