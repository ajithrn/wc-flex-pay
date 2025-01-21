<?php
/**
 * Admin dashboard template
 *
 * @package WC_Flex_Pay\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get payment statistics
$total_pending = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wcfp_order_payments WHERE status = 'pending'"
);
$total_completed = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wcfp_order_payments WHERE status = 'completed'"
);
$total_overdue = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wcfp_order_payments 
     WHERE status = 'pending' AND due_date < CURDATE()"
);

// Get payments data
$payments = $wpdb->get_results(
    "SELECT 
        p.*, 
        o.ID as order_id,
        o.post_status as order_status,
        pm1.meta_value as _billing_first_name,
        pm2.meta_value as _billing_last_name,
        pm3.meta_value as _billing_email,
        pm4.meta_value as _payment_method_title,
        oi.order_item_id,
        oim1.meta_value as _product_id,
        oim2.meta_value as _variation_id,
        products.post_title as _product_name,
        GROUP_CONCAT(DISTINCT CONCAT(attr.meta_key, ': ', attr.meta_value) SEPARATOR ', ') as _product_variation
    FROM {$wpdb->prefix}wcfp_order_payments p
    LEFT JOIN {$wpdb->posts} o ON p.order_id = o.ID
    LEFT JOIN {$wpdb->postmeta} pm1 ON o.ID = pm1.post_id AND pm1.meta_key = '_billing_first_name'
    LEFT JOIN {$wpdb->postmeta} pm2 ON o.ID = pm2.post_id AND pm2.meta_key = '_billing_last_name'
    LEFT JOIN {$wpdb->postmeta} pm3 ON o.ID = pm3.post_id AND pm3.meta_key = '_billing_email'
    LEFT JOIN {$wpdb->postmeta} pm4 ON o.ID = pm4.post_id AND pm4.meta_key = '_payment_method_title'
    LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID = oi.order_id AND oi.order_item_type = 'line_item'
    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim1 ON oi.order_item_id = oim1.order_item_id AND oim1.meta_key = '_product_id'
    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_variation_id'
    LEFT JOIN {$wpdb->posts} products ON COALESCE(NULLIF(oim2.meta_value, ''), oim1.meta_value) = products.ID
    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta attr ON oi.order_item_id = attr.order_item_id 
        AND attr.meta_key LIKE 'pa_%'
    GROUP BY p.id
    ORDER BY p.due_date ASC",
    ARRAY_A
);

// Calculate upcoming payments total
$upcoming_total = 0;
foreach ($payments as $payment) {
    if ($payment['status'] === 'pending' && strtotime($payment['due_date']) > current_time('timestamp')) {
        $upcoming_total += $payment['amount'];
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Flex Pay Dashboard', 'wc-flex-pay'); ?></h1>

    <!-- Statistics Widgets -->
    <div class="wcfp-dashboard-widgets">
        <div class="wcfp-widget">
            <h3><?php esc_html_e('Pending Payments', 'wc-flex-pay'); ?></h3>
            <div class="wcfp-widget-content">
                <span class="wcfp-stat"><?php echo esc_html($total_pending); ?></span>
                <span class="wcfp-amount"><?php echo wc_price($upcoming_total); ?></span>
            </div>
        </div>

        <div class="wcfp-widget">
            <h3><?php esc_html_e('Completed Payments', 'wc-flex-pay'); ?></h3>
            <div class="wcfp-widget-content">
                <span class="wcfp-stat"><?php echo esc_html($total_completed); ?></span>
            </div>
        </div>

        <div class="wcfp-widget wcfp-widget-warning">
            <h3><?php esc_html_e('Overdue Payments', 'wc-flex-pay'); ?></h3>
            <div class="wcfp-widget-content">
                <span class="wcfp-stat"><?php echo esc_html($total_overdue); ?></span>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="wcfp-dashboard-content">
        <div class="wcfp-table-header">
            <div class="wcfp-table-filters">
                <div class="wcfp-filter-group">
                    <select id="wcfp-status-filter">
                        <option value=""><?php esc_html_e('All Statuses', 'wc-flex-pay'); ?></option>
                        <option value="pending"><?php esc_html_e('Pending', 'wc-flex-pay'); ?></option>
                        <option value="completed"><?php esc_html_e('Completed', 'wc-flex-pay'); ?></option>
                        <option value="overdue"><?php esc_html_e('Overdue', 'wc-flex-pay'); ?></option>
                    </select>

                    <input type="text" id="wcfp-search" placeholder="<?php esc_attr_e('Search customer...', 'wc-flex-pay'); ?>">
                </div>

                <div class="wcfp-filter-group">
                    <input type="text" id="wcfp-date-from" class="wcfp-datepicker" placeholder="<?php esc_attr_e('From Date', 'wc-flex-pay'); ?>">
                    <input type="text" id="wcfp-date-to" class="wcfp-datepicker" placeholder="<?php esc_attr_e('To Date', 'wc-flex-pay'); ?>">
                </div>
            </div>

            <div class="wcfp-table-actions">
                <button type="button" class="button" id="wcfp-export-csv">
                    <?php esc_html_e('Export CSV', 'wc-flex-pay'); ?>
                </button>
            </div>
        </div>

        <table class="widefat wcfp-payments-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Product', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Customer', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Status', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Payment Method', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Actions', 'wc-flex-pay'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment) : 
                    $order_url = admin_url('post.php?post=' . $payment['order_id'] . '&action=edit');
                    $customer_name = trim($payment['_billing_first_name'] . ' ' . $payment['_billing_last_name']);
                    $is_overdue = strtotime($payment['due_date']) < current_time('timestamp') && $payment['status'] === 'pending';
                    $status_class = $is_overdue ? 'overdue' : $payment['status'];
                    ?>
                    <tr data-status="<?php echo esc_attr($status_class); ?>" 
                        data-customer="<?php echo esc_attr(strtolower($customer_name)); ?>"
                        data-date="<?php echo esc_attr($payment['due_date']); ?>">
                        <td>
                            <a href="<?php echo esc_url($order_url); ?>">
                                <?php echo sprintf(__('Order #%s', 'wc-flex-pay'), $payment['order_id']); ?>
                            </a>
                        </td>
                        <td>
                            <?php 
                            $product_name = $payment['_product_name'] ?? '';
                            if ($product_name) {
                                echo esc_html($product_name);
                                if ($payment['_product_variation']) {
                                    echo ' - ' . esc_html($payment['_product_variation']);
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            echo esc_html($customer_name);
                            if ($payment['_billing_email']) {
                                echo '<br><small>' . esc_html($payment['_billing_email']) . '</small>';
                            }
                            ?>
                        </td>
                        <td><?php echo wc_price($payment['amount']); ?></td>
                        <td>
                            <?php 
                            echo date_i18n(get_option('date_format'), strtotime($payment['due_date']));
                            if ($is_overdue) {
                                echo ' <span class="wcfp-status overdue">' . esc_html__('Overdue', 'wc-flex-pay') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="wcfp-status <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html(ucfirst($payment['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($payment['_payment_method_title']); ?></td>
                        <td>
                            <?php if ($payment['status'] === 'pending') : ?>
                                <button type="button" 
                                        class="button process-payment" 
                                        data-payment-id="<?php echo esc_attr($payment['id']); ?>"
                                        data-nonce="<?php echo wp_create_nonce('wcfp-admin'); ?>">
                                    <?php esc_html_e('Process Payment', 'wc-flex-pay'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    // Apply all filters function
    function applyFilters() {
        var status = $('#wcfp-status-filter').val();
        var search = $('#wcfp-search').val().toLowerCase();
        var dateFrom = $('#wcfp-date-from').val();
        var dateTo = $('#wcfp-date-to').val();

        $('.wcfp-payments-table tbody tr').each(function() {
            var $row = $(this);
            var showRow = true;

            // Status filter
            if (status && $row.data('status') !== status) {
                showRow = false;
            }

            // Customer search
            if (search && $row.data('customer').indexOf(search) === -1) {
                showRow = false;
            }

            // Date range filter
            if (dateFrom || dateTo) {
                var rowDate = new Date($row.data('date'));
                if (dateFrom) {
                    var fromDate = new Date(dateFrom);
                    if (rowDate < fromDate) {
                        showRow = false;
                    }
                }
                if (dateTo) {
                    var toDate = new Date(dateTo);
                    toDate.setHours(23, 59, 59);
                    if (rowDate > toDate) {
                        showRow = false;
                    }
                }
            }

            $row.toggle(showRow);
        });
    }

    // Status filter
    $('#wcfp-status-filter').on('change', applyFilters);

    // Customer search
    $('#wcfp-search').on('keyup', applyFilters);

    // Date filters
    $('#wcfp-date-from, #wcfp-date-to').on('change', applyFilters);

    // Initialize datepicker
    $('.wcfp-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        onSelect: function() {
            applyFilters();
        }
    });

    // Export CSV
    $('#wcfp-export-csv').on('click', function() {
        var rows = [
            ['Order', 'Product', 'Customer', 'Amount', 'Due Date', 'Status', 'Payment Method']
        ];

        $('.wcfp-payments-table tbody tr:visible').each(function() {
            var $row = $(this);
            var rowData = [
                $row.find('td:eq(0)').text().trim(),
                $row.find('td:eq(1)').text().trim(),
                $row.find('td:eq(2)').text().trim(),
                $row.find('td:eq(3)').text().trim(),
                $row.find('td:eq(4)').text().trim(),
                $row.find('td:eq(5)').text().trim(),
                $row.find('td:eq(6)').text().trim()
            ];

            // Escape CSV values
            rowData = rowData.map(function(value) {
                if (value.indexOf(',') > -1 || value.indexOf('"') > -1 || value.indexOf('\n') > -1) {
                    return '"' + value.replace(/"/g, '""') + '"';
                }
                return value;
            });

            rows.push(rowData);
        });

        var csvContent = rows.map(function(row) {
            return row.join(',');
        }).join('\n');

        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'flex-pay-payments.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });

    // Process payment
    $('.process-payment').on('click', function() {
        var button = $(this);
        var paymentId = button.data('payment-id');
        var nonce = button.data('nonce');
        
        if (confirm(wcfp_admin_params.i18n.confirm_process)) {
            button.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'wcfp_process_payment',
                payment_id: paymentId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                    button.prop('disabled', false);
                }
            });
        }
    });
});
</script>
