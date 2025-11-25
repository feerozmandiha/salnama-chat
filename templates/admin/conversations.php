<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap salnama-chat-admin">
    <h1 class="wp-heading-inline"><?php _e('مدیریت مکالمات', 'salnama-chat'); ?></h1>
    
    <div class="conversations-filters">
        <ul class="subsubsub">
            <li>
                <a href="<?php echo admin_url('admin.php?page=salnama-chat-conversations&status=open'); ?>" 
                   class="<?php echo $status === 'open' ? 'current' : ''; ?>">
                    <?php _e('باز', 'salnama-chat'); ?>
                    <span class="count">(<?php echo $conversations_data['pagination']['total'] ?? 0; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=salnama-chat-conversations&status=pending'); ?>" 
                   class="<?php echo $status === 'pending' ? 'current' : ''; ?>">
                    <?php _e('در حال انجام', 'salnama-chat'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=salnama-chat-conversations&status=closed'); ?>" 
                   class="<?php echo $status === 'closed' ? 'current' : ''; ?>">
                    <?php _e('بسته شده', 'salnama-chat'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=salnama-chat-conversations&status=resolved'); ?>" 
                   class="<?php echo $status === 'resolved' ? 'current' : ''; ?>">
                    <?php _e('حل شده', 'salnama-chat'); ?>
                </a>
            </li>
        </ul>
        
        <form method="get" class="search-form">
            <input type="hidden" name="page" value="salnama-chat-conversations">
            <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" 
                   placeholder="<?php _e('جستجو در مکالمات...', 'salnama-chat'); ?>">
            <button type="submit" class="button"><?php _e('جستجو', 'salnama-chat'); ?></button>
        </form>
    </div>

    <div class="conversations-table-container">
        <table class="wp-list-table widefat fixed striped conversations">
            <thead>
                <tr>
                    <th scope="col" class="column-customer"><?php _e('مشتری', 'salnama-chat'); ?></th>
                    <th scope="col" class="column-subject"><?php _e('موضوع', 'salnama-chat'); ?></th>
                    <th scope="col" class="column-priority"><?php _e('اولویت', 'salnama-chat'); ?></th>
                    <th scope="col" class="column-operator"><?php _e('اپراتور', 'salnama-chat'); ?></th>
                    <th scope="col" class="column-status"><?php _e('وضعیت', 'salnama-chat'); ?></th>
                    <th scope="col" class="column-date"><?php _e('تاریخ', 'salnama-chat'); ?></th>
                    <th scope="col" class="column-actions"><?php _e('عملیات', 'salnama-chat'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($conversations_data['conversations'])): ?>
                    <?php foreach ($conversations_data['conversations'] as $conversation): ?>
                        <tr data-conversation-id="<?php echo esc_attr($conversation['conversation_id']); ?>">
                            <td class="column-customer">
                                <div class="customer-info">
                                    <?php echo get_avatar($conversation['customer_email'] ?? '', 32, '', '', ['class' => 'avatar']); ?>
                                    <div class="customer-details">
                                        <strong><?php echo esc_html($conversation['customer_name'] ?: 'مشتری ناشناس'); ?></strong>
                                        <?php if ($conversation['customer_email']): ?>
                                            <br><small><?php echo esc_html($conversation['customer_email']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="column-subject">
                                <?php echo esc_html($conversation['subject']); ?>
                            </td>
                            <td class="column-priority">
                                <span class="priority-badge priority-<?php echo esc_attr($conversation['priority']); ?>">
                                    <?php echo $this->get_priority_label($conversation['priority']); ?>
                                </span>
                            </td>
                            <td class="column-operator">
                                <?php if ($conversation['operator_id']): ?>
                                    <?php 
                                    $operator = get_userdata($conversation['operator_id']);
                                    echo $operator ? esc_html($operator->display_name) : __('نامشخص', 'salnama-chat');
                                    ?>
                                <?php else: ?>
                                    <span class="no-operator"><?php _e('اختصاص داده نشده', 'salnama-chat'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($conversation['status']); ?>">
                                    <?php echo $this->get_status_label($conversation['status']); ?>
                                </span>
                            </td>
                            <td class="column-date">
                                <?php echo human_time_diff(strtotime($conversation['created_at']), current_time('timestamp')); ?>
                                <?php _e('پیش', 'salnama-chat'); ?>
                            </td>
                            <td class="column-actions">
                                <div class="row-actions">
                                    <button class="button button-small view-conversation" 
                                            data-conversation-id="<?php echo esc_attr($conversation['conversation_id']); ?>">
                                        <?php _e('مشاهده', 'salnama-chat'); ?>
                                    </button>
                                    
                                    <?php if ($conversation['status'] === 'open' && !$conversation['operator_id']): ?>
                                        <button class="button button-small assign-conversation" 
                                                data-conversation-id="<?php echo esc_attr($conversation['conversation_id']); ?>">
                                            <?php _e('اختصاص به من', 'salnama-chat'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($conversation['status'], ['open', 'pending'])): ?>
                                        <button class="button button-small close-conversation" 
                                                data-conversation-id="<?php echo esc_attr($conversation['conversation_id']); ?>">
                                            <?php _e('بستن', 'salnama-chat'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-items">
                            <?php _e('هیچ مکالمه‌ای یافت نشد.', 'salnama-chat'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($conversations_data['pagination']['total_pages'] > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php 
                        printf(
                            _n('%s مکالمه', '%s مکالمه', $conversations_data['pagination']['total'], 'salnama-chat'),
                            number_format_i18n($conversations_data['pagination']['total'])
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
                            'total' => $conversations_data['pagination']['total_pages'],
                            'current' => $page
                        ]);
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>