<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Checkout_Validation
 *
 * 處理結帳頁面欄位顯示與前端驗證。
 */
class WFS_Checkout_Validation {

    public function __construct() {
        // --- 第一部分：調整結帳欄位顯示 ---

        // 1. 強制顯示收件人表單
        add_filter('woocommerce_ship_to_different_address_checked', '__return_true');

        // 2. 完全跳過勾選框步驟，確保地址顯示
        add_filter('woocommerce_cart_needs_shipping_address', '__return_true');

        // 3. 從結帳欄位中移除 "ship_to_different_address"
        add_filter('woocommerce_checkout_fields', [$this, 'remove_ship_to_different_address_field']);

        // 4. 使用 CSS 徹底隱藏勾選框相關的視覺元素
        add_action('wp_head', [$this, 'hide_shipping_checkbox_css']);


        // --- 第二部分：載入前端驗證腳本 ---
        add_action('wp_enqueue_scripts', [$this, 'enqueue_validation_script']);
    }

    /**
     * 從結帳欄位陣列中移除 "ship_to_different_address"。
     */
    public function remove_ship_to_different_address_field($fields) {
        unset($fields['billing']['ship_to_different_address']);
        return $fields;
    }

    /**
     * 注入 CSS 來隱藏 "運送到不同地址？" 的勾選框。
     */
    public function hide_shipping_checkbox_css() {
        if (is_checkout()) {
            echo '<style>
                #ship-to-different-address { display: none !important; }
            </style>';
        }
    }

    /**
     * 在結帳頁面載入我們的 JavaScript 驗證檔案。
     */
    public function enqueue_validation_script() {
        if (is_checkout()) {
            wp_enqueue_script(
                'wfs-checkout-validation-script',
                WFS_PLUGIN_URL . 'assets/js/wfs-checkout-validation.js',
                ['jquery', 'wc-checkout'],
                '1.8.0', // 建議更新版本號
                true
            );
        }
    }
}