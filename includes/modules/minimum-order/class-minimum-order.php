<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Minimum_Order
 *
 * 處理最小訂購金額限制的核心邏輯。
 */
class WFS_Minimum_Order {

    private $minimum_amount = 0;

    /**
     * 建構子：從後台設定讀取金額，並掛載所有需要的 hooks。
     */
    public function __construct() {
        // 從 WordPress 選項中讀取管理員設定的最小金額
        $this->minimum_amount = (float) get_option('wfs_minimum_order_amount', 0);

        // 如果管理員沒有設定最小金額，或設為 0，則不執行任何動作
        if ($this->minimum_amount <= 0) {
            return;
        }

        // 掛載到結帳流程與購物車頁面，執行金額檢查
        add_action('woocommerce_checkout_process', [$this, 'check_minimum_order_amount']);
        add_action('woocommerce_before_cart', [$this, 'check_minimum_order_amount']);
    }

    /**
     * 檢查購物車總額是否滿足最小訂購金額。
     * 這個方法整合了您原本的邏輯，並使其更有效率。
     */
    public function check_minimum_order_amount() {
        // 如果購物車是空的，也不需要檢查
        if (WC()->cart->is_empty()) {
            return;
        }

        // 使用 WC()->cart->get_total('edit') 取得未格式化的原始總金額以進行精確比較
        $cart_total = WC()->cart->get_total('edit');

        // 比較購物車總額與設定的最小金額
        if ($cart_total < $this->minimum_amount) {
            
            // 準備統一的錯誤訊息
            $message = sprintf(
                '您目前訂單總金額為：%s — 最低訂單總金額至少需滿 %s 才可結帳。',
                wc_price($cart_total),
                wc_price($this->minimum_amount)
            );

            // WooCommerce 會自動根據頁面 (購物車/結帳) 使用最適合的 notice 函式
            // 我們只需要呼叫 wc_add_notice 即可
            wc_add_notice($message, 'error');
        }
    }
}