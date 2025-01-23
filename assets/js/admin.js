// Add error handler for script loading
window.onerror = function(msg, url, line, col, error) {
    if (url.includes('admin.js')) {
        console.error('WCFP Admin Error:', {
            message: msg,
            url: url,
            line: line,
            col: col,
            error: error
        });
    }
    return false;
};

jQuery(function($) {
    'use strict';

    // Check if jQuery is properly loaded
    if (typeof $ !== 'function') {
        console.error('WCFP Admin Error: jQuery not loaded');
        return;
    }

    // Check if required params are available
    if (typeof wcfp_admin_params === 'undefined') {
        console.error('WCFP Admin Error: wcfp_admin_params not defined');
        return;
    }

    var WCFP_Admin = {
        init: function() {
            try {
                console.log('Initializing WCFP Admin with params:', wcfp_admin_params);
                
                // Check for required dependencies
                if (!$.fn.datepicker) {
                    console.error('WCFP Admin Error: jQuery UI Datepicker not loaded');
                    return;
                }

                if (typeof ClipboardJS !== 'function') {
                    console.error('WCFP Admin Error: ClipboardJS not loaded');
                    return;
                }

                this.initDatepicker();
                this.initPaymentSchedule();
                this.initDashboardFilters();
                this.initPaymentActions();
                this.initSubOrderActions();
                
                console.log('WCFP Admin initialized successfully');
            } catch (error) {
                console.error('WCFP Admin Error during initialization:', error);
            }
        },

        // Initialize datepicker for due dates
        initDatepicker: function() {
            try {
                $('.wcfp-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0
                });
                console.log('Datepicker initialized');
            } catch (error) {
                console.error('WCFP Admin Error initializing datepicker:', error);
            }
        },

        // Initialize payment schedule table
        initPaymentSchedule: function() {
            try {
                var $scheduleTable = $('.wcfp-schedule-table');
                if (!$scheduleTable.length) {
                    console.log('No schedule table found, skipping initialization');
                    return;
                }

                console.log('Initializing payment schedule table');

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

                console.log('Payment schedule table initialized');
            } catch (error) {
                console.error('WCFP Admin Error initializing payment schedule:', error);
            }
        },

        // Update installment numbers after sorting/removing
        updateInstallmentNumbers: function() {
            try {
                $('.wcfp-schedule-table tbody tr:not(.template)').each(function(idx) {
                    $(this).find('input[name^="wcfp_schedule"][name$="[installment_number]"]').val(idx + 1);
                    $(this).find('.installment-number').text(idx + 1);
                });
                console.log('Installment numbers updated');
            } catch (error) {
                console.error('WCFP Admin Error updating installment numbers:', error);
            }
        },

        // Initialize dashboard filters
        initDashboardFilters: function() {
            try {
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

                console.log('Dashboard filters initialized');
            } catch (error) {
                console.error('WCFP Admin Error initializing dashboard filters:', error);
            }
        },

        // Initialize sub-order actions
        initSubOrderActions: function() {
            try {
                // Create sub-order
                $('.create-sub-order').on('click', function() {
                    var $button = $(this);
                    var orderId = $button.data('order-id');
                    
                    $button.prop('disabled', true);
                    
                    console.log('Create sub-order clicked:', {
                        orderId: orderId,
                        nonce: wcfp_admin_params.nonce
                    });

                    $.post(wcfp_admin_params.ajax_url, {
                        action: 'wcfp_create_sub_order',
                        order_id: orderId,
                        nonce: wcfp_admin_params.nonce
                    }, function(response) {
                        console.log('Create sub-order response:', response);
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
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Create sub-order error:', {
                            status: textStatus,
                            error: errorThrown,
                            response: jqXHR.responseText
                        });
                        alert(wcfp_admin_params.i18n.error);
                        $button.prop('disabled', false);
                    });
                });

                console.log('Sub-order actions initialized');
            } catch (error) {
                console.error('WCFP Admin Error initializing sub-order actions:', error);
            }
        },

        // Initialize payment actions
        initPaymentActions: function() {
            try {
                // Initialize clipboard.js
                new ClipboardJS('.copy-link').on('success', function(e) {
                    var button = $(e.trigger);
                    var originalText = button.text();
                    button.text(wcfp_admin_params.i18n.copied);
                    setTimeout(function() {
                        button.text(originalText);
                    }, 2000);
                });

                // Generate payment link
                $('.generate-link').on('click', function() {
                    var $button = $(this);
                    var orderId = $button.data('order-id');
                    var installment = $button.data('installment');
                    
                    console.log('Generate link clicked:', {
                        orderId: orderId,
                        installment: installment,
                        nonce: wcfp_admin_params.nonce
                    });
                    
                    $button.prop('disabled', true);
                    
                    $.post(wcfp_admin_params.ajax_url, {
                        action: 'wcfp_generate_payment_link',
                        order_id: orderId,
                        installment: installment,
                        nonce: wcfp_admin_params.nonce
                    }, function(response) {
                        console.log('Generate link response:', response);
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                            $button.prop('disabled', false);
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Generate link error:', {
                            status: textStatus,
                            error: errorThrown,
                            response: jqXHR.responseText
                        });
                        alert(wcfp_admin_params.i18n.error);
                        $button.prop('disabled', false);
                    });
                });

                // Send payment link email
                $('.send-link').on('click', function() {
                    var $button = $(this);
                    var orderId = $button.data('order-id');
                    var installment = $button.data('installment');
                    
                    $button.prop('disabled', true);
                    
                    console.log('Send payment link clicked:', {
                        orderId: orderId,
                        installment: installment,
                        nonce: wcfp_admin_params.nonce
                    });

                    $.post(wcfp_admin_params.ajax_url, {
                        action: 'wcfp_send_payment_link',
                        order_id: orderId,
                        installment: installment,
                        nonce: wcfp_admin_params.nonce
                    }, function(response) {
                        console.log('Send payment link response:', response);
                        if (response.success) {
                            alert(wcfp_admin_params.i18n.email_sent);
                        } else {
                            alert(response.data);
                        }
                        $button.prop('disabled', false);
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Send payment link error:', {
                            status: textStatus,
                            error: errorThrown,
                            response: jqXHR.responseText
                        });
                        alert(wcfp_admin_params.i18n.error);
                        $button.prop('disabled', false);
                    });
                });

                // Process payment
                $('.process-payment').on('click', function() {
                    var $button = $(this);
                    var paymentId = $button.data('payment-id');
                    
                    console.log('Process payment clicked:', {
                        paymentId: paymentId,
                        nonce: wcfp_admin_params.nonce
                    });
                    
                    if (confirm(wcfp_admin_params.i18n.confirm_process)) {
                        $button.prop('disabled', true);
                        
                        $.post(wcfp_admin_params.ajax_url, {
                            action: 'wcfp_process_payment',
                            payment_id: paymentId,
                            nonce: wcfp_admin_params.nonce
                        }, function(response) {
                            console.log('Process payment response:', response);
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data);
                                $button.prop('disabled', false);
                            }
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('Process payment error:', {
                                status: textStatus,
                                error: errorThrown,
                                response: jqXHR.responseText
                            });
                            alert(wcfp_admin_params.i18n.error);
                            $button.prop('disabled', false);
                        });
                    }
                });

                // Update schedule
                $('.update-schedule').on('click', function() {
                    var $button = $(this);
                    var $row = $button.closest('tr');
                    var scheduleId = $button.data('schedule-id');
                    var amount = $row.find('input[name="amount"]').val();
                    var dueDate = $row.find('input[name="due_date"]').val();
                    
                    if (confirm(wcfp_admin_params.i18n.confirm_update)) {
                        $button.prop('disabled', true);
                        
                        console.log('Update schedule clicked:', {
                            scheduleId: scheduleId,
                            amount: amount,
                            dueDate: dueDate,
                            nonce: wcfp_admin_params.nonce
                        });

                        $.post(wcfp_admin_params.ajax_url, {
                            action: 'wcfp_update_schedule',
                            schedule_id: scheduleId,
                            amount: amount,
                            due_date: dueDate,
                            nonce: wcfp_admin_params.nonce
                        }, function(response) {
                            console.log('Update schedule response:', response);
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data);
                                $button.prop('disabled', false);
                            }
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('Update schedule error:', {
                                status: textStatus,
                                error: errorThrown,
                                response: jqXHR.responseText
                            });
                            alert(wcfp_admin_params.i18n.error);
                            $button.prop('disabled', false);
                        });
                    }
                });

                console.log('Payment actions initialized');
            } catch (error) {
                console.error('WCFP Admin Error initializing payment actions:', error);
            }
        }
    };

    // Initialize on document ready
    try {
        WCFP_Admin.init();
    } catch (error) {
        console.error('WCFP Admin Error during main initialization:', error);
    }
});
