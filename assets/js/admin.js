jQuery(function($) {
    'use strict';

    var WCFP_Admin = {
        init: function() {
            this.initDatepicker();
            this.initPaymentSchedule();
            this.initDashboardFilters();
            this.initPaymentActions();
            this.initSubOrderActions();
        },

        // Initialize datepicker for due dates
        initDatepicker: function() {
            $('.wcfp-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0
            });
        },

        // Initialize payment schedule table
        initPaymentSchedule: function() {
            var $scheduleTable = $('.wcfp-schedule-table');
            if (!$scheduleTable.length) return;

            // Add row
            $('.add-schedule-row').on('click', function(e) {
                e.preventDefault();
                var $tbody = $scheduleTable.find('tbody');
                var $template = $tbody.find('tr.template').clone();
                $template.removeClass('template').show();
                $tbody.append($template);
                WCFP_Admin.initDatepicker();
                WCFP_Admin.updateInstallmentNumbers();
            });

            // Remove row
            $scheduleTable.on('click', '.remove-row', function(e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                WCFP_Admin.updateInstallmentNumbers();
            });

            // Auto-calculate amounts
            $('#wcfp_total_price').on('change', function() {
                var total = parseFloat($(this).val()) || 0;
                var $rows = $scheduleTable.find('tbody tr:not(.template)');
                var count = $rows.length;
                if (count > 0) {
                    var amount = (total / count).toFixed(2);
                    $rows.each(function() {
                        $(this).find('input[name^="wcfp_schedule"][name$="[amount]"]').val(amount);
                    });
                }
            });

            // Sort rows by date
            $scheduleTable.on('change', 'input[name^="wcfp_schedule"][name$="[due_date]"]', function() {
                var $tbody = $scheduleTable.find('tbody');
                var $rows = $tbody.find('tr:not(.template)').get();
                $rows.sort(function(a, b) {
                    var dateA = new Date($(a).find('input[name^="wcfp_schedule"][name$="[due_date]"]').val());
                    var dateB = new Date($(b).find('input[name^="wcfp_schedule"][name$="[due_date]"]').val());
                    return dateA - dateB;
                });
                $.each($rows, function(idx, row) {
                    $tbody.append(row);
                });
                WCFP_Admin.updateInstallmentNumbers();
            });
        },

        // Update installment numbers after sorting/removing
        updateInstallmentNumbers: function() {
            $('.wcfp-schedule-table tbody tr:not(.template)').each(function(idx) {
                $(this).find('input[name^="wcfp_schedule"][name$="[installment_number]"]').val(idx + 1);
                $(this).find('.installment-number').text(idx + 1);
            });
        },

        // Initialize dashboard filters
        initDashboardFilters: function() {
            // Status filter
            $('#wcfp-status-filter').on('change', function() {
                var status = $(this).val();
                if (status) {
                    $('.wcfp-payments-table tbody tr').hide()
                        .filter('[data-status="' + status + '"]').show();
                } else {
                    $('.wcfp-payments-table tbody tr').show();
                }
            });

            // Customer search
            $('#wcfp-search').on('keyup', function() {
                var search = $(this).val().toLowerCase();
                $('.wcfp-payments-table tbody tr').hide()
                    .filter(function() {
                        return $(this).data('customer').indexOf(search) > -1;
                    }).show();
            });

            // Export CSV
            $('#wcfp-export-csv').on('click', function() {
                var rows = [
                    ['Order', 'Customer', 'Amount', 'Due Date', 'Status', 'Payment Method']
                ];

                $('.wcfp-payments-table tbody tr:visible').each(function() {
                    var $row = $(this);
                    rows.push([
                        $row.find('td:eq(0)').text().trim(),
                        $row.find('td:eq(1)').text().trim(),
                        $row.find('td:eq(2)').text().trim(),
                        $row.find('td:eq(3)').text().trim(),
                        $row.find('td:eq(4)').text().trim(),
                        $row.find('td:eq(5)').text().trim()
                    ]);
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
        },

        // Initialize sub-order actions
        initSubOrderActions: function() {
            // Create sub-order
            $('.create-sub-order').on('click', function() {
                var $button = $(this);
                var orderId = $button.data('order-id');
                var nonce = $button.data('nonce');
                
                $button.prop('disabled', true);
                
                $.post(wcfp_admin_params.ajax_url, {
                    action: 'wcfp_create_sub_order',
                    order_id: orderId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(response.data);
                        $button.prop('disabled', false);
                    }
                });
            });
        },

        // Initialize payment actions
        initPaymentActions: function() {
            // Process payment
            $('.process-payment').on('click', function() {
                var $button = $(this);
                var paymentId = $button.data('payment-id');
                var nonce = $button.data('nonce');
                
                if (confirm(wcfp_admin_params.i18n.confirm_process)) {
                    $button.prop('disabled', true);
                    
                    $.post(wcfp_admin_params.ajax_url, {
                        action: 'wcfp_process_payment',
                        payment_id: paymentId,
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                            $button.prop('disabled', false);
                        }
                    });
                }
            });

            // Update schedule
            $('.update-schedule').on('click', function() {
                var $button = $(this);
                var $row = $button.closest('tr');
                var scheduleId = $button.data('schedule-id');
                var nonce = $button.data('nonce');
                var amount = $row.find('input[name="amount"]').val();
                var dueDate = $row.find('input[name="due_date"]').val();
                
                if (confirm(wcfp_admin_params.i18n.confirm_update)) {
                    $button.prop('disabled', true);
                    
                    $.post(wcfp_admin_params.ajax_url, {
                        action: 'wcfp_update_schedule',
                        schedule_id: scheduleId,
                        amount: amount,
                        due_date: dueDate,
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                            $button.prop('disabled', false);
                        }
                    });
                }
            });
        }
    };

    // Initialize on document ready
    WCFP_Admin.init();
});
