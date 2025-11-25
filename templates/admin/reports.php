<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1 class="wp-heading-inline"><?php _e('گزارشات چت', 'salnama-chat'); ?></h1>
    
    <div class="reports-filters">
        <form method="get">
            <input type="hidden" name="page" value="salnama-chat-reports">
            <label for="date_from">از تاریخ:</label>
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
            
            <label for="date_to">تا تاریخ:</label>
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
            
            <button type="submit" class="button">فیلتر</button>
        </form>
    </div>

    <div class="reports-stats">
        <h2>آمار کلی</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo esc_html($conversation_stats['total_conversations']); ?></h3>
                <p>کل مکالمات</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo esc_html($conversation_stats['open_conversations']); ?></h3>
                <p>مکالمات باز</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo esc_html($conversation_stats['resolved_conversations']); ?></h3>
                <p>حل شده</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo esc_html(round($conversation_stats['avg_resolution_time'], 1)); ?> دقیقه</h3>
                <p>میانگین زمان پاسخ</p>
            </div>
        </div>
        
        <div class="customer-stats">
            <h2>آمار مشتریان</h2>
            <p>کل مشتریان: <?php echo esc_html($customer_stats['total_customers']); ?></p>
            <p>مشتریان فعال: <?php echo esc_html($customer_stats['active_customers']); ?></p>
        </div>
    </div>
</div>