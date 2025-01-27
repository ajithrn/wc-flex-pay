<?php
/**
 * Action buttons partial template
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Variables available in this template:
 * 
 * @var array $actions Array of actions with 'url' and 'text' keys
 * @var string $primary_action Key of the primary action
 */

// Ensure required variables are available
if (!isset($actions) || !is_array($actions) || empty($actions)) {
    return;
}

if (!isset($primary_action) || !isset($actions[$primary_action])) {
    $primary_action = key($actions); // Use first action as primary if not specified
}
?>

<div class="wcfp-text-center">
    <?php foreach ($actions as $key => $action) : 
        if (!isset($action['url']) || !isset($action['text'])) {
            continue;
        }
    ?>
        <a href="<?php echo esc_url($action['url']); ?>" 
           class="wcfp-button<?php echo ($key === $primary_action) ? '' : ' wcfp-button-secondary'; ?>"
           style="<?php echo ($key === $primary_action) ? '' : 'background-color: #f0f0f0; color: #515151 !important;'; ?>">
            <?php echo esc_html($action['text']); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php 
// Show URL as text only if primary action exists and has URL
if (isset($actions[$primary_action]) && !empty($actions[$primary_action]['url'])) : ?>
    <div class="wcfp-text-center wcfp-text-small" style="margin-top: 10px;">
        <?php esc_html_e('Or copy and paste this link in your browser:', 'wc-flex-pay'); ?><br>
        <span style="color: #444; word-break: break-all;"><?php echo esc_url($actions[$primary_action]['url']); ?></span>
    </div>
<?php endif; ?>
