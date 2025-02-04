<?php
/**
 * Product payment schedule template
 *
 * @package WC_Flex_Pay\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($payments) || empty($payments['installments'])) {
    return;
}

// Calculate initial payment (includes any overdue payments)
$today = current_time('timestamp');
$initial_payment = 0;
$future_installments = array();
$overdue_exists = true;

foreach ($payments['installments'] as $installment) {
    $due_date = strtotime($installment['due_date']);
    if ($due_date <= $today) {
        $initial_payment += $installment['amount'];
        $overdue_exists = true;
    } else {
        $future_installments[] = $installment;
    }
}

if (empty($initial_payment)) {
    $initial_payment = $payments['installments'][0]['amount'];
    array_shift($future_installments);
}

// Check if there is one ore more installment in the initial payment. 
$first_installment = !empty($payments['installments'][0]) ? $payments['installments'][0]['amount'] : 0;
if ($first_installment == $initial_payment) { 
  $overdue_exists = false;
}
// Output the form start
echo isset($form_start) ? $form_start : '';
?>

<div class="wcfp-payment-type">
    <div class="wcfp-payment-options">
        <div class="wcfp-payment-option">
            <label>
                <input type="radio" name="wcfp_payment_type" class="wcfp-payment-radio" value="full" checked>
                <span class="wcfp-option-label"><?php esc_html_e('Full Payment', 'wc-flex-pay'); ?></span>
                <span class="wcfp-option-amount"><?php echo wc_price($payments['summary']['total_amount']); ?></span>
            </label>
        </div>

        <div class="wcfp-payment-option">
            <label>
                <input type="radio" name="wcfp_payment_type" class="wcfp-payment-radio" value="installment">
                <span class="wcfp-option-label"><?php esc_html_e('Flexible Payment', 'wc-flex-pay'); ?></span>
                <span class="wcfp-option-amount">
                    <?php echo wc_price($initial_payment); ?>
                    <small>
                        <?php 
                        printf(
                            /* translators: %d: number of installments */
                            esc_html__('due today', 'wc-flex-pay'),
                        );
                        ?>
                    </small>
                </span>
            </label>
        </div>
    </div>
</div>

<div class="wcfp-payment-schedule" style="display: none;">
    <?php if ($overdue_exists) : ?>
        <div class="wcfp-notice wcfp-notice-warning mb-4">
            <?php esc_html_e('Past due payments are included in your initial payment.', 'wc-flex-pay'); ?>
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
                <h4><?php esc_html_e('Total Amount', 'wc-flex-pay'); ?></h4>
                <div class="wcfp-amount"><?php echo wc_price($payments['summary']['total_amount']); ?></div>
                <small>
                    <?php 
                    printf(
                        /* translators: %s: remaining amount */
                        esc_html__('Remaining: %s', 'wc-flex-pay'),
                        wc_price($payments['summary']['total_amount'] - $initial_payment)
                    ); 
                    ?>
                </small>
            </div>
        </div>
    </div>

    <div class="wcfp-deposit-notice">
        <?php         
        
        if ($overdue_exists) {            
          printf(
            /* translators: 1: first installment amount, 2: initial payment amount, 3: policy page URL */
            wp_kses(
                __('The first installment of %1$s is a nonrefundable deposit to secure your spot. Any additional amounts in your initial payment of %2$s are partially refundable. This is not the full price of the tour. After completing this payment, you will receive an email with your payment plan details and everything you need to prepare for your journey. Please refer to our <a href="%3$s" target="_blank">cancellation and refund policy</a> for complete details.', 'wc-flex-pay'),
                array('a' => array('href' => array(), 'target' => array()))
            ),
            wc_price($first_installment),
            wc_price($initial_payment),
            esc_url(get_privacy_policy_url())
        );
        } else {
          printf(
                /* translators: 1: first installment amount, 2: policy page URL */
                wp_kses(
                    __('The first installment of %1$s is a nonrefundable deposit to secure your spot. This is not the full price of the tour. After completing this payment, you will receive an email with your payment plan details and everything you need to prepare for your journey. Please refer to our <a href="%2$s" target="_blank">cancellation and refund policy</a> for complete details.', 'wc-flex-pay'),
                    array('a' => array('href' => array(), 'target' => array()))
                ),
                wc_price($first_installment),
                esc_url(get_privacy_policy_url())
            );
        }
        ?>
    </div>

    <?php if (!empty($future_installments)) : ?>
        <div class="wcfp-future-payments">
            <h4><?php esc_html_e('Payment Schedule', 'wc-flex-pay'); ?></h4>
            <div class="wcfp-timeline">
                <?php foreach ($future_installments as $installment) : 
                    $due_date = strtotime($installment['due_date']);
                    $days_until = ceil(($due_date - $today) / DAY_IN_SECONDS);
                    ?>
                    <div class="wcfp-timeline-item">
                        <div class="wcfp-timeline-marker"></div>
                        <div class="wcfp-timeline-content">
                            <div class="wcfp-timeline-date">
                                <span class="wcfp-date">
                                    <?php 
                                    printf(
                                        /* translators: %1$d: installment number, %2$s: formatted date */
                                        esc_html__('Installment %1$d - %2$s', 'wc-flex-pay'),
                                        $installment['number'],
                                        date_i18n(get_option('date_format'), $due_date)
                                    );
                                    ?>
                                </span>
                                <span class="wcfp-days">
                                    <?php 
                                    printf(
                                        /* translators: %d: number of days */
                                        esc_html(_n('in %d day', 'in %d days', $days_until, 'wc-flex-pay')),
                                        $days_until
                                    ); 
                                    ?>
                                </span>
                            </div>
                            <div class="wcfp-timeline-amount">
                                <?php echo wc_price($installment['amount']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="wcfp-notice-box">
        <h4><?php esc_html_e('How Flex Pay Works', 'wc-flex-pay'); ?></h4>
        <ul>
            <li>
                <span class="wcfp-icon">✓</span>
                <?php esc_html_e('Make your initial payment today', 'wc-flex-pay'); ?>
            </li>
            <li>
                <span class="wcfp-icon">📧</span>
                <?php esc_html_e('Receive payment links for future installments', 'wc-flex-pay'); ?>
            </li>
            <li>
                <span class="wcfp-icon">📅</span>
                <?php esc_html_e('Pay each installment by its due date', 'wc-flex-pay'); ?>
            </li>
            <li>
                <span class="wcfp-icon">✨</span>
                <?php esc_html_e('No hidden fees or interest charges', 'wc-flex-pay'); ?>
            </li>
        </ul>
    </div>
</div>

<?php
// Output the form end (including the add to cart button)
echo isset($form_end) ? $form_end : '';
?>

<script>
jQuery(function($) {
    // Handle radio button changes
    $('input[name="wcfp_payment_type"]').on('change', function() {
        var $schedule = $('.wcfp-payment-schedule');
        var $option = $(this).closest('.wcfp-payment-option');
        
        $('.wcfp-payment-option').removeClass('selected');
        
        if ($(this).val() === 'installment') {
            $schedule.slideDown(300);
            $option.addClass('selected');
        } else {
            $schedule.slideUp(300);
        }
    });
});
</script>
