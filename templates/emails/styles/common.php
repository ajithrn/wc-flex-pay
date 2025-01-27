<?php
/**
 * Common email styles
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style type="text/css">
    /* Brand Colors */
    :root {
        --brand-color: #8d0759;
        --brand-color-light: #a6086a;
        --brand-color-dark: #740648;
        --brand-color-bg: #f9e6f2;
    }

    /* Common Elements */
    .wcfp-heading {
        color: #8d0759;
        font-size: 24px;
        font-weight: bold;
        margin: 0 0 20px;
    }

    .wcfp-summary-box {
        background-color: #f9e6f2;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .wcfp-summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .wcfp-summary-table th,
    .wcfp-summary-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }

    .wcfp-summary-table th {
        color: #8d0759;
        font-weight: 600;
    }

    .wcfp-summary-table td.amount {
        text-align: right;
        font-weight: 600;
    }

    .wcfp-button {
        display: inline-block;
        padding: 12px 24px;
        background-color: #8d0759;
        color: #ffffff !important;
        text-decoration: none !important;
        border-radius: 4px;
        font-weight: 600;
        margin: 20px 0;
    }

    .wcfp-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .wcfp-status.pending {
        background-color: #f8dda7;
        color: #94660c;
    }

    .wcfp-status.completed {
        background-color: #c6e1c6;
        color: #5b841b;
    }

    .wcfp-status.overdue {
        background-color: #eba3a3;
        color: #761919;
    }

    .wcfp-installment-details {
        background-color: #ffffff;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 15px;
        margin-top: 20px;
    }

    .wcfp-installment-details h3 {
        color: #8d0759;
        margin: 0 0 15px;
        font-size: 16px;
    }

    .wcfp-text-small {
        font-size: 12px;
        color: #666666;
    }

    .wcfp-text-center {
        text-align: center;
    }

    .wcfp-divider {
        border-top: 1px solid #e5e5e5;
        margin: 20px 0;
    }
</style>
