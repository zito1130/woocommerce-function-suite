<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Weight_Control
 *
 * 處理運送重量限制的核心邏輯。
 */
class WFS_Weight_Control {

    private $limits = [];
    private $max_allowed_weight = null;

    public function __construct() {
        $this->limits = array_filter((array) get_option('wfs_shipping_weight_limits', []));
        
        if (empty($this->limits)) {
            return;
        }

        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_cart_weight_on_add'], 10, 3);
        add_filter('woocommerce_update_cart_validation', [$this, 'validate_cart_weight_on_update'], 10, 4);
        add_action('wp_ajax_wfs_get_cart_weight', [$this, 'ajax_get_cart_weight']);
        add_action('wp_ajax_nopriv_wfs_get_cart_weight', [$this, 'ajax_get_cart_weight']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_script']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout_weight'], 999, 2);
        add_action('wp_head', [$this, 'add_checkout_styling']);
    }

    /**
     * *** 新功能：在商品加入購物車前，驗證總重量是否會超過上限 ***
     */
    public function validate_cart_weight_on_add($passed, $product_id, $quantity) {
        // 1. 取得全站最高的重量限制
        $max_weight = $this->get_maximum_allowed_weight();
        
        // 如果沒有任何運送方式設定重量限制，則不進行檢查
        if ($max_weight === 0) return $passed;

        // 2. 取得產品資訊和重量
        $product = wc_get_product($product_id);
        if (!$product || !$product->has_weight()) return $passed; // 如果商品沒有重量，則不檢查

        $product_weight = (float) $product->get_weight();
        // 3. 計算當前購物車重量 + 即將加入的商品重量
        $current_cart_weight = WC()->cart->get_cart_contents_weight();
        $potential_weight = $current_cart_weight + ($product_weight * $quantity);
        
        // 4. 進行比較和驗證
        if ($potential_weight > $max_weight) {
            // 如果潛在重量超過上限，阻止商品加入購物車，並顯示錯誤訊息
            wc_add_notice(sprintf(
                '抱歉，將此商品加入後，您的訂單總重量 (%.2f kg) 將會超過本店的運送上限 (%.2f kg)，無法加入購物車。',
                $potential_weight,
                $max_weight
            ), 'error');
            return false; // 返回 false 來阻止操作
        }

        return $passed; // 如果未超重，返回 true
    }

    /**
     * *** 新功能：在購物車頁面更新商品數量時，進行重量驗證 ***
     */
    public function validate_cart_weight_on_update($passed, $cart_item_key, $values, $quantity) {
        $max_weight = $this->get_maximum_allowed_weight();
        if ($max_weight === 0 || $quantity == 0) return $passed;
        
        // 模擬計算更新後的購物車總重量
        $potential_weight = 0;
        foreach (WC()->cart->get_cart() as $key => $item) {
            $product_weight = (float) $item['data']->get_weight();
            
            if ($key === $cart_item_key) {
                // 對於正在被修改的項目，使用新的數量來計算
                $potential_weight += $product_weight * $quantity;
            } else {
                // 其他項目則使用它們目前的數量
                $potential_weight += $product_weight * $item['quantity'];
            }
        }

        if ($potential_weight > $max_weight) {
            // 如果潛在重量超過上限，阻止更新，並顯示錯誤訊息
            wc_add_notice(sprintf(
                '抱歉，更新數量後，您的訂單總重量 (%.2f kg) 將會超過本店的運送上限 (%.2f kg)，無法更新。',
                $potential_weight,
                $max_weight
            ), 'error');
            return false; // 返回 false 來阻止更新
        }

        return $passed; // 如果未超重，返回 true
    }
    
    /**
     * *** 新輔助函式：計算並快取全站最高的運送重量限制 ***
     */
    private function get_maximum_allowed_weight() {
        // 使用快取，避免重複計算
        if ($this->max_allowed_weight !== null) {
            return $this->max_allowed_weight;
        }

        $max_weight = 0;
        foreach ($this->limits as $limit) {
            $limit_val = (float) $limit;
            if ($limit_val > $max_weight) {
                $max_weight = $limit_val;
            }
        }

        $this->max_allowed_weight = $max_weight;
        return $this->max_allowed_weight;
    }

    /**
     * 在頁面 <head> 中注入 CSS 樣式
     */
    public function add_checkout_styling() {
        if (is_checkout()) {
            echo '<style>
                .wfs-shipping-method--overweight {
                    opacity: 0.5;
                    pointer-events: none;
                }
                .wfs-shipping-method--overweight .wfs-weight-limit-notice {
                    pointer-events: auto;
                }
            </style>';
        }
    }

    /**
     * 在結帳頁面載入我們的 JavaScript 檔案。
     */
    public function enqueue_checkout_script() {
        if (!is_checkout()) {
            return;
        }
        wp_enqueue_script(
            'wfs-checkout-weight-script',
            WFS_PLUGIN_URL . 'assets/js/wfs-checkout-weight.js',
            ['jquery', 'wc-checkout'],
            '1.3.2', // 建議更新版本號
            true
        );
        wp_localize_script('wfs-checkout-weight-script', 'wfs_weight_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wfs-weight-nonce'),
        ]);
    }

    /**
     * AJAX 處理函式：計算並回傳購物車總重量及所有限制。
     */
    public function ajax_get_cart_weight() {
        if (!check_ajax_referer('wfs-weight-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        $cart_weight = WC()->cart->get_cart_contents_weight();
        wp_send_json_success([
            'weight' => $cart_weight,
            'limits' => $this->limits,
        ]);
    }

    /**
     * 在提交訂單時進行最終的後端驗證。
     */
    public function validate_checkout_weight($data, $errors) {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (empty($chosen_shipping_methods[0])) {
            return;
        }
        $chosen_method_id = $chosen_shipping_methods[0];
        if (isset($this->limits[$chosen_method_id]) && $this->limits[$chosen_method_id] !== '') {
            $max_weight = (float) $this->limits[$chosen_method_id];
            $cart_weight = WC()->cart->get_cart_contents_weight();
            if ($cart_weight > $max_weight) {
                $errors->add('validation', sprintf(
                    '抱歉，您的訂單總重量 (%.2f kg) 已超過所選運送方式的上限 (%.2f kg)，請重新選擇運送方式或調整購物車內容。',
                    $cart_weight,
                    $max_weight
                ));
            }
        }
    }
}