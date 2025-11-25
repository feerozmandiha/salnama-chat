<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1 class="wp-heading-inline"><?php _e('مدیریت مشتریان', 'salnama-chat'); ?></h1>
    
    <div class="customers-header">
        <div class="customers-filters">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="salnama-chat-customers">
                <div class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" 
                           placeholder="<?php _e('جستجو در مشتریان...', 'salnama-chat'); ?>" class="search-input">
                    <button type="submit" class="button button-primary">
                        <?php _e('جستجو', 'salnama-chat'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="customers-actions">
            <span class="customers-count">
                <?php 
                printf(
                    _n('%s مشتری', '%s مشتری', $customers_data['pagination']['total'], 'salnama-chat'),
                    number_format_i18n($customers_data['pagination']['total'])
                );
                ?>
            </span>
        </div>
    </div>

    <div class="customers-table-container">
        <?php if (!empty($customers_data['customers'])): ?>
            <table class="wp-list-table widefat fixed striped customers">
                <thead>
                    <tr>
                        <th scope="col" class="column-customer"><?php _e('مشتری', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-contact"><?php _e('اطلاعات تماس', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-stats"><?php _e('آمار', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-activity"><?php _e('فعالیت', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-status"><?php _e('وضعیت', 'salnama-chat'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers_data['customers'] as $customer): ?>
                        <tr>
                            <td class="column-customer">
                                <div class="customer-avatar">
                                    <?php echo get_avatar($customer['customer_email'] ?? '', 40, '', '', ['class' => 'avatar']); ?>
                                </div>
                                <div class="customer-info">
                                    <strong class="customer-name"><?php echo esc_html($customer['customer_name'] ?: 'مشتری ناشناس'); ?></strong>
                                    <?php if ($customer['user_id'] > 0): ?>
                                        <span class="customer-badge registered"><?php _e('ثبت‌نام کرده', 'salnama-chat'); ?></span>
                                    <?php else: ?>
                                        <span class="customer-badge guest"><?php _e('مهمان', 'salnama-chat'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-contact">
                                <?php if ($customer['customer_email']): ?>
                                    <div class="contact-email">
                                        <span class="dashicons dashicons-email"></span>
                                        <?php echo esc_html($customer['customer_email']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($customer['customer_phone']): ?>
                                    <div class="contact-phone">
                                        <span class="dashicons dashicons-phone"></span>
                                        <?php echo esc_html($customer['customer_phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="column-stats">
                                <div class="customer-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo esc_html($customer['total_conversations']); ?></span>
                                        <span class="stat-label"><?php _e('مکالمه', 'salnama-chat'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="column-activity">
                                <div class="last-visit">
                                    <span class="activity-time">
                                        <?php echo human_time_diff(strtotime($customer['last_visit']), current_time('timestamp')); ?>
                                        <?php _e('پیش', 'salnama-chat'); ?>
                                    </span>
                                    <small class="first-visit">
                                        <?php _e('اولین بازدید:', 'salnama-chat'); ?>
                                        <?php echo date_i18n('Y/m/d', strtotime($customer['first_visit'])); ?>
                                    </small>
                                </div>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($customer['status']); ?>">
                                    <?php 
                                    $status_labels = [
                                        'active' => __('فعال', 'salnama-chat'),
                                        'inactive' => __('غیرفعال', 'salnama-chat'), 
                                        'blocked' => __('مسدود', 'salnama-chat')
                                    ];
                                    echo $status_labels[$customer['status']] ?? $customer['status'];
                                    ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($customers_data['pagination']['total_pages'] > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php 
                            printf(
                                _n('%s مشتری', '%s مشتری', $customers_data['pagination']['total'], 'salnama-chat'),
                                number_format_i18n($customers_data['pagination']['total'])
                            );
                            ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $customers_data['pagination']['total_pages'],
                                'current' => $page
                            ]);
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-items">
                <div class="no-items-icon">👥</div>
                <h3><?php _e('هیچ مشتری یافت نشد', 'salnama-chat'); ?></h3>
                <p><?php _e('هنوز هیچ مشتری در سیستم ثبت نشده است.', 'salnama-chat'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>