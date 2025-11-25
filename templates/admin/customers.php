<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1 class="wp-heading-inline"><?php _e('مدیریت مشتریان', 'salnama-chat'); ?></h1>
    
    <div class="customers-filters">
        <form method="get" class="search-form">
            <input type="hidden" name="page" value="salnama-chat-customers">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" 
                   placeholder="<?php _e('جستجو در مشتریان...', 'salnama-chat'); ?>">
            <button type="submit" class="button"><?php _e('جستجو', 'salnama-chat'); ?></button>
        </form>
    </div>

    <div class="customers-table-container">
        <?php if (!empty($customers_data['customers'])): ?>
            <table class="wp-list-table widefat fixed striped customers">
                <thead>
                    <tr>
                        <th scope="col" class="column-name"><?php _e('نام', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-email"><?php _e('ایمیل', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-phone"><?php _e('تلفن', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-conversations"><?php _e('مکالمات', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-last-visit"><?php _e('آخرین بازدید', 'salnama-chat'); ?></th>
                        <th scope="col" class="column-status"><?php _e('وضعیت', 'salnama-chat'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers_data['customers'] as $customer): ?>
                        <tr>
                            <td class="column-name">
                                <strong><?php echo esc_html($customer['customer_name'] ?: 'ناشناس'); ?></strong>
                            </td>
                            <td class="column-email">
                                <?php echo esc_html($customer['customer_email'] ?: '-'); ?>
                            </td>
                            <td class="column-phone">
                                <?php echo esc_html($customer['customer_phone'] ?: '-'); ?>
                            </td>
                            <td class="column-conversations">
                                <?php echo esc_html($customer['total_conversations']); ?>
                            </td>
                            <td class="column-last-visit">
                                <?php echo human_time_diff(strtotime($customer['last_visit']), current_time('timestamp')); ?>
                                <?php _e('پیش', 'salnama-chat'); ?>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($customer['status']); ?>">
                                    <?php 
                                    $status_labels = [
                                        'active' => 'فعال',
                                        'inactive' => 'غیرفعال', 
                                        'blocked' => 'مسدود'
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
                <p><?php _e('هیچ مشتری یافت نشد.', 'salnama-chat'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>