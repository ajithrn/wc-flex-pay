<?php
/**
 * Admin dashboard template
 *
 * @package WC_Flex_Pay\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get all orders with flex pay enabled items
$orders = wc_get_orders(array(
    'limit' => -1,
));

// Initialize counters and data arrays
$total_pending = 0;
$total_pending_amount = 0;
$total_completed = 0;
$total_completed_amount = 0;
$total_overdue = 0;
$total_overdue_amount = 0;
$payments = array();
$current_time = current_time('timestamp');

foreach ($orders as $order) {
    foreach ($order->get_items() as $item) {
        if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
            continue;
        }

        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (empty($payment_status)) continue;

        foreach ($payment_status as $status) {
            $amount = $status['amount'] * $item->get_quantity();
            
            // Count statistics
            if ($status['status'] === 'pending') {
                $total_pending++;
                $total_pending_amount += $amount;
                if (strtotime($status['due_date']) < $current_time) {
                    $total_overdue++;
                    $total_overdue_amount += $amount;
                }
            } elseif ($status['status'] === 'completed') {
                $total_completed++;
                $total_completed_amount += $amount;
            }

            // Get order data
            $order_data = array(
                'id' => $status['number'],
                'order_id' => $order->get_id(),
                'order_status' => $order->get_status(),
                '_billing_first_name' => $order->get_billing_first_name(),
                '_billing_last_name' => $order->get_billing_last_name(),
                '_billing_email' => $order->get_billing_email(),
                '_payment_method_title' => $order->get_payment_method_title(),
                'amount' => $status['amount'],
                'due_date' => $status['due_date'],
                'status' => $status['status'],
                'payment_date' => $status['payment_date'] ?? '',
            );

            // Get installment number from payment status for parent orders
            $order_data['installment_number'] = $status['number'];

            // Check if this is a sub-order
            $parent_id = $order->get_meta('_wcfp_parent_order');
            $sub_installment_number = $order->get_meta('_wcfp_installment_number');
            if ($parent_id) {
                $parent_order = wc_get_order($parent_id);
                if ($parent_order) {
                    $order_data['order_type'] = sprintf('I%d of #%s', $sub_installment_number, $parent_id);
                    $order_data['parent_order_id'] = $parent_id;
                    $order_data['installment_number'] = $sub_installment_number;
                }
            } else {
                $order_data['order_type'] = 'Parent';
            }

            // Get product data
            $product = $item->get_product();
            if ($product) {
                $order_data['_product_name'] = $product->get_name();
                if ($product->is_type('variation')) {
                    $order_data['_product_variation'] = wc_get_formatted_variation($product, true);
                }
            }

            $payments[] = $order_data;
        }
    }
}

// Sort payments by order ID (latest first)
usort($payments, function($a, $b) {
    return $b['order_id'] - $a['order_id'];
});

// Calculate upcoming payments total
$upcoming_total = array_reduce($payments, function($carry, $payment) use ($current_time) {
    if ($payment['status'] === 'pending' && strtotime($payment['due_date']) > $current_time) {
        $carry += $payment['amount'];
    }
    return $carry;
}, 0);
?>

<div class="wrap">
    <h1><?php esc_html_e('Flex Pay Dashboard', 'wc-flex-pay'); ?></h1>

    <!-- Statistics Widgets -->
    <div class="wcfp-dashboard-widgets">
        <div class="wcfp-widget">
            <h3><?php esc_html_e('Pending Payments', 'wc-flex-pay'); ?></h3>
            <div class="wcfp-widget-content">
                <span class="wcfp-stat my-8"><?php echo esc_html($total_pending); ?></span>
                <span class="wcfp-amount"><?php echo wc_price($total_pending_amount); ?></span>
            </div>
        </div>

        <div class="wcfp-widget">
            <h3><?php esc_html_e('Completed Payments', 'wc-flex-pay'); ?></h3>
            <div class="wcfp-widget-content">
                <span class="wcfp-stat my-8"><?php echo esc_html($total_completed); ?></span>
                <span class="wcfp-amount"><?php echo wc_price($total_completed_amount); ?></span>
            </div>
        </div>

        <div class="wcfp-widget wcfp-widget-warning">
            <h3><?php esc_html_e('Overdue Payments', 'wc-flex-pay'); ?></h3>
            <div class="wcfp-widget-content">
                <span class="wcfp-stat my-8"><?php echo esc_html($total_overdue); ?></span>
                <span class="wcfp-amount"><?php echo wc_price($total_overdue_amount); ?></span>
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
                    <th><?php esc_html_e('Customer', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Order', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Product', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Due / Paid Date', 'wc-flex-pay'); ?></th>
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
                            <?php 
                            echo esc_html($customer_name);
                            if ($payment['_billing_email']) {
                                echo '<br><small>' . esc_html($payment['_billing_email']) . '</small>';
                            }
                            ?>
                        </td>
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
                                if (!empty($payment['_product_variation'])) {
                                    echo ' - ' . esc_html($payment['_product_variation']);
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($payment['order_type'] === 'Parent') {
                                if ($payment['installment_number'] === 1) {
                                    echo __('Initial Payment', 'wc-flex-pay');
                                } else {
                                    echo sprintf(__('Installment #%s', 'wc-flex-pay'), $payment['installment_number']);
                                }
                            } else {
                                echo sprintf(__('Installment #%s', 'wc-flex-pay'), $payment['installment_number']);
                            }
                            ?>
                        </td>
                        <td><?php echo wc_price($payment['amount']); ?></td>
                        <td>
                            <?php 
                            if ($payment['status'] === 'completed' && $payment['payment_date']) {
                                echo sprintf(__('Paid: %s', 'wc-flex-pay'), 
                                    date_i18n(get_option('date_format'), strtotime($payment['payment_date']))
                                );
                            } else {
                                echo sprintf(__('Due: %s', 'wc-flex-pay'),
                                    date_i18n(get_option('date_format'), strtotime($payment['due_date']))
                                );
                                if ($is_overdue) {
                                    echo ' <span class="wcfp-status overdue">' . esc_html__('Overdue', 'wc-flex-pay') . '</span>';
                                }
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
            ['Customer', 'Order', 'Product', 'Installment', 'Amount', 'Due/Paid Date', 'Status', 'Payment Method']
        ];

        $('.wcfp-payments-table tbody tr:visible').each(function() {
            var $row = $(this);
            var rowData = [
                $row.find('td:eq(0)').text().trim(), // Customer
                $row.find('td:eq(1)').text().trim(), // Order
                $row.find('td:eq(2)').text().trim(), // Product
                $row.find('td:eq(3)').text().trim(), // Installment
                $row.find('td:eq(4)').text().trim(), // Amount
                $row.find('td:eq(5)').text().trim(), // Due/Paid Date
                $row.find('td:eq(6)').text().trim(), // Status
                $row.find('td:eq(7)').text().trim()  // Payment Method
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

    // Initialize payment date picker
    var $paymentDatePicker = $('<input type="text" class="wcfp-payment-date-picker" style="display:none;">').appendTo('body');
    $paymentDatePicker.datepicker({
        dateFormat: 'yy-mm-dd',
        onSelect: function(dateText) {
            var button = $paymentDatePicker.data('button');
            if (button) {
                var paymentId = button.data('payment-id');
                var nonce = button.data('nonce');
                
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wcfp_set_payment_date',
                    payment_id: paymentId,
                    payment_date: dateText,
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
            $paymentDatePicker.hide();
        }
    });

    // Handle set payment date button
    $('.set-payment-date').on('click', function(e) {
        var button = $(this);
        var offset = button.offset();
        
        $paymentDatePicker
            .data('button', button)
            .css({
                top: offset.top + button.outerHeight(),
                left: offset.left
            })
            .show();
            
        e.stopPropagation();
    });

    // Hide payment date picker when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wcfp-payment-date-picker').length) {
            $paymentDatePicker.hide();
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
