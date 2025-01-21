<?php
/**
 * Product payment schedule template
 *
 * @package WC_Flex_Pay\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($schedules)) {
    return;
}

// Calculate initial payment (includes any overdue payments)
$today = current_time('timestamp');
$initial_payment = 0;
$future_payments = array();
$overdue_exists = false;

foreach ($schedules as $schedule) {
    $due_date = strtotime($schedule['due_date']);
    if ($due_date <= $today) {
        $initial_payment += $schedule['amount'];
        $overdue_exists = true;
    } else {
        $future_payments[] = $schedule;
    }
}

if (empty($initial_payment)) {
    $initial_payment = $schedules[0]['amount'];
    array_shift($schedules);
    $future_payments = $schedules;
}

$total = array_sum(array_column($schedules, 'amount'));

// Output the form start
echo isset($form_start) ? $form_start : '';
?>

<div class="wcfp-payment-type">
    <div class="wcfp-payment-options">
        <div class="wcfp-payment-option">
            <label>
                <input type="radio" name="wcfp_payment_type" class="wcfp-payment-radio" value="full" checked>
                <span class="wcfp-option-label"><?php esc_html_e('Full Payment', 'wc-flex-pay'); ?></span>
            </label>
        </div>

        <div class="wcfp-payment-option">
            <label>
                <input type="radio" name="wcfp_payment_type" class="wcfp-payment-radio" value="installment">
                <span class="wcfp-option-label"><?php esc_html_e('Flexible Payment', 'wc-flex-pay'); ?></span>
            </label>
        </div>
    </div>
</div>

<div class="wcfp-payment-schedule" style="display: none;">
    <?php if ($overdue_exists) : ?>
        <div class="wcfp-notice wcfp-notice-warning mb-3">
            <?php esc_html_e('Past due payments is included in your initial payment.', 'wc-flex-pay'); ?>
        </div>
    <?php endif; ?>
    
    <div class="wcfp-payment-summary">
        <div class="wcfp-summary-box wcfp-initial-box">
            <div class="wcfp-summary-content">
                <h4><?php esc_html_e('Initial Payment Today', 'wc-flex-pay'); ?></h4>
                <div class="wcfp-amount"><?php echo wc_price($initial_payment); ?></div>
                <small><?php esc_html_e('Due at checkout', 'wc-flex-pay'); ?></small>
            </div>
        </div>

        <div class="wcfp-summary-box wcfp-total-box">
            <div class="wcfp-summary-content">
                <h4><?php esc_html_e('Pending Payments', 'wc-flex-pay'); ?></h4>
                <div class="wcfp-amount"><?php 
                    $pending_total = array_sum(array_column($future_payments, 'amount'));
                    echo wc_price($pending_total); 
                ?></div>
                <small><?php 
                    printf(
                        /* translators: %d: number of payments */
                        esc_html(_n('%d remaining payment', '%d remaining payments', count($future_payments), 'wc-flex-pay')),
                        count($future_payments)
                    ); 
                ?></small>
            </div>
        </div>
    </div>

    <?php if (!empty($future_payments)) : ?>
        <div class="wcfp-future-payments">
            <h4><?php esc_html_e('Future Payment Schedule', 'wc-flex-pay'); ?></h4>
            <div class="wcfp-timeline">
                <?php foreach ($future_payments as $schedule) : 
                    $due_date = strtotime($schedule['due_date']);
                    $days_until = ceil(($due_date - $today) / DAY_IN_SECONDS);
                    ?>
                    <div class="wcfp-timeline-item">
                        <div class="wcfp-timeline-marker"></div>
                        <div class="wcfp-timeline-content">
                            <div class="wcfp-timeline-date">
                                <span class="wcfp-date"><?php echo esc_html(date_i18n(get_option('date_format'), $due_date)); ?></span>
                                <small class="wcfp-days">
                                    <?php 
                                    printf(
                                        /* translators: %d: number of days */
                                        esc_html(_n('in %d day', 'in %d days', $days_until, 'wc-flex-pay')),
                                        $days_until
                                    ); 
                                    ?>
                                </small>
                            </div>
                            <div class="wcfp-timeline-amount">
                                <?php echo wc_price($schedule['amount']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="wcfp-schedule-notice">
        <div class="wcfp-notice-box">
            <h4><?php esc_html_e('How Flex Pay Works', 'wc-flex-pay'); ?></h4>
            <ul>
                <li>
                    <span class="wcfp-icon">✓</span>
                    <?php esc_html_e('Make your initial payment today', 'wc-flex-pay'); ?>
                </li>
                <li>
                    <span class="wcfp-icon">💳</span>
                    <?php esc_html_e('Future payments will be automatically charged to your payment method', 'wc-flex-pay'); ?>
                </li>
                <li>
                    <span class="wcfp-icon">✨</span>
                    <?php esc_html_e('No hidden fees or interest charges', 'wc-flex-pay'); ?>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
// Output the form end (including the add to cart button)
echo isset($form_end) ? $form_end : '';
?>

<script>
jQuery(function($) {
    // Initialize payment schedule visibility
    var $selectedRadio = $('input[name="wcfp_payment_type"]:checked');
    if ($selectedRadio.val() === 'installment') {
        $('.wcfp-payment-schedule').show();
    }

    // Handle radio button changes
    $('input[name="wcfp_payment_type"]').on('change', function() {
        var $schedule = $('.wcfp-payment-schedule');
        if ($(this).val() === 'installment') {
            $schedule.slideDown(300);
        } else {
            $schedule.slideUp(300);
        }
    });
});
</script>
