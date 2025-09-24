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
     * *** (已修改) 加入購物車時，驗證「該供應商」總重量是否會超過上限 ***
     */
    public function validate_cart_weight_on_add($passed, $product_id, $quantity) {
        // 1. 取得全站最高的重量限制 (邏輯不變)
        $max_weight = $this->get_maximum_allowed_weight();
        if ($max_weight === 0) return $passed;

        // 2. 取得產品資訊和重量 (邏輯不變)
        $product = wc_get_product($product_id);
        if (!$product || !$product->has_weight()) return $passed;
        
        // --- (*** 關鍵修改 ***) ---
        // 3. 獲取新商品的供應商 ID
        // (我們需要 cart-manager 載入)
        if ( ! class_exists('CM_Cart_Display') ) return $passed;
        
        $temp_cart_item = ['data' => $product]; // 建立一個模擬的 cart_item
        $new_item_supplier_id = CM_Cart_Display::get_item_supplier_id($temp_cart_item);
        
        // 4. 獲取當前購物車中 *所有* 供應商的重量
        $supplier_weights = CM_Cart_Display::get_cart_weight_by_supplier();
        
        // 5. 計算「該供應商」的潛在重量
        $current_supplier_weight = $supplier_weights[$new_item_supplier_id] ?? 0;
        $new_item_weight = (float) $product->get_weight() * $quantity;
        $potential_supplier_weight = $current_supplier_weight + $new_item_weight;

        // 6. 進行比較
        if ($potential_supplier_weight > $max_weight) {
            $supplier_name = CM_Cart_Display::get_supplier_display_name($new_item_supplier_id);
            wc_add_notice(sprintf(
                '抱歉，供應商【%s】的商品總重 (%.2f kg) 將會超過本店的運送上限 (%.2f kg)，無法加入購物車。',
                esc_html($supplier_name),
                $potential_supplier_weight,
                $max_weight
            ), 'error');
            return false;
        }
        // --- (*** 修改完畢 ***) ---

        return $passed; 
    }

    /**
     * *** (已修改) 更新購物車時，驗證「所有供應商」的重量 ***
     */
    public function validate_cart_weight_on_update($passed, $cart_item_key, $values, $quantity) {
        $max_weight = $this->get_maximum_allowed_weight();
        if ($max_weight === 0) return $passed;
        
        if ( ! class_exists('CM_Cart_Display') ) return $passed;

        // --- (*** 關鍵修改 ***) ---
        // 1. 模擬計算更新後 *每個供應商* 的總重量
        $potential_supplier_weights = [];
        foreach (WC()->cart->get_cart() as $key => $item) {
            $supplier_id = CM_Cart_Display::get_item_supplier_id($item);
            $product_weight = (float) $item['data']->get_weight();
            
            if ( ! isset( $potential_supplier_weights[ $supplier_id ] ) ) {
                $potential_supplier_weights[ $supplier_id ] = 0;
            }
            
            if ($key === $cart_item_key) {
                // 對於正在被修改的項目，使用新的數量來計算
                $potential_supplier_weights[ $supplier_id ] += ($product_weight * $quantity);
            } else {
                // 其他項目則使用它們目前的數量
                $potential_supplier_weights[ $supplier_id ] += ($product_weight * $item['quantity']);
            }
        }

        // 2. 檢查是否有 *任何* 供應商超重
        foreach ($potential_supplier_weights as $supplier_id => $weight) {
            if ($weight > $max_weight) {
                $supplier_name = CM_Cart_Display::get_supplier_display_name($supplier_id);
                wc_add_notice(sprintf(
                    '抱歉，更新數量後，供應商【%s】的商品總重 (%.2f kg) 將會超過本店的運送上限 (%.2f kg)，無法更新。',
                    esc_html($supplier_name),
                    $weight,
                    $max_weight
                ), 'error');
                return false;
            }
        }
        // --- (*** 修改完畢 ***) ---

        return $passed;
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
     * AJAX 處理函式：(*** 已修改：回傳依供應商分類的重量 ***)
     */
    public function ajax_get_cart_weight() {
        if (!check_ajax_referer('wfs-weight-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // --- (*** 關鍵修正 ***) ---
        // 檢查 cart-manager 的輔助函數是否存在
        if ( ! class_exists('CM_Cart_Display') || ! method_exists('CM_Cart_Display', 'get_cart_weight_by_supplier') ) {
            // 如果不存在，回傳舊格式，避免網站崩潰
            wp_send_json_success([
                'weight' => WC()->cart->get_cart_contents_weight(),
                'limits' => $this->limits,
                'is_split' => false // 新增一個旗標
            ]);
            return;
        }

        // 呼叫 cart-manager 的輔助函數來獲取依供應商分類的重量
        $supplier_weights = CM_Cart_Display::get_cart_weight_by_supplier();
        
        // 獲取供應商名稱
        $supplier_names = [];
        foreach (array_keys($supplier_weights) as $supplier_id) {
            $supplier_names[$supplier_id] = CM_Cart_Display::get_supplier_display_name($supplier_id);
        }

        wp_send_json_success([
            'weights' => $supplier_weights, // 改為複數 weights
            'names'   => $supplier_names,   // 新增供應商名稱
            'limits'  => $this->limits,
            'is_split' => true // 旗標，告訴 JS 這是新格式
        ]);
        // --- (*** 修改完畢 ***) ---
    }

    /**
     * *** (已修改) 結帳時，驗證「所有供應商」的重量 ***
     */
    public function validate_checkout_weight($data, $errors) {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (empty($chosen_shipping_methods[0])) {
            return;
        }
        
        if ( ! class_exists('CM_Cart_Display') ) return;

        $chosen_method_id = $chosen_shipping_methods[0];
        if (isset($this->limits[$chosen_method_id]) && $this->limits[$chosen_method_id] !== '') {
            $max_weight = (float) $this->limits[$chosen_method_id];
            
            // --- (*** 關鍵修改 ***) ---
            // 1. 獲取所有供應商的重量
            $supplier_weights = CM_Cart_Display::get_cart_weight_by_supplier();

            // 2. 檢查是否有 *任何* 供應商超重
            foreach ($supplier_weights as $supplier_id => $weight) {
                if ($weight > $max_weight) {
                    $supplier_name = CM_Cart_Display::get_supplier_display_name($supplier_id);
                    $errors->add('validation', sprintf(
                        '抱歉，供應商【%s】的商品總重 (%.2f kg) 已超過所選運送方式的上限 (%.2f kg)，請調整購物車內容。',
                        esc_html($supplier_name),
                        $weight,
                        $max_weight
                    ));
                    // 即使只有一個出錯，也要停止
                    return; 
                }
            }
            // --- (*** 修改完畢 ***) ---
        }
    }
}