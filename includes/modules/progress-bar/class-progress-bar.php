<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Progress_Bar
 *
 * 在商品頁、購物車頁、商品目錄頁面，顯示運送重量進度條。
 */
class WFS_Progress_Bar {

    public function __construct() {
        // 掛載我們的 CSS 和 JS 檔案
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // 使用多個、針對各頁面最可靠的 hook
        add_action('woocommerce_before_cart_totals', [$this, 'render_progress_bar_container']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_progress_bar_container']);
        add_action('woocommerce_before_shop_loop', [$this, 'render_progress_bar_container']);

        // 註冊 AJAX action
        add_action('wp_ajax_wfs_get_cart_weight_for_progress', [$this, 'ajax_get_cart_weight']);
        add_action('wp_ajax_nopriv_wfs_get_cart_weight_for_progress', [$this, 'ajax_get_cart_weight']);
    }

    /**
     * AJAX 處理函式 (*** 已修改：回傳所有供應商的重量 ***)
     */
    /**
     * AJAX 處理函式 (*** 已修改：回傳所有供應商的溫層 ***)
     */
    public function ajax_get_cart_weight() {
        check_ajax_referer('wfs-progress-nonce', 'nonce');
        
        if ( ! class_exists('CM_Cart_Display') ) {
            wp_send_json_error('Missing dependencies');
            return;
        }
        
        $supplier_weights = CM_Cart_Display::get_cart_weight_by_supplier();
        $supplier_names = [];
        $supplier_shipping_classes = []; // (*** 全新 ***)

        // 將商品依供應商分組
        $supplier_groups = [];
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $supplier_id = CM_Cart_Display::get_item_supplier_id($cart_item);
            $supplier_groups[$supplier_id][] = $cart_item;
        }

        foreach ($supplier_weights as $supplier_id => $weight) {
            $supplier_names[$supplier_id] = CM_Cart_Display::get_supplier_display_name($supplier_id);
            // (*** 全新 ***) 獲取該供應商的第一個商品的溫層
            $first_item = reset($supplier_groups[$supplier_id]);
            $product = $first_item['data'];
            if ($product) {
                $shipping_class_slug = $product->get_shipping_class();
                if ($shipping_class_slug) {
                    $term = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
                    $supplier_shipping_classes[$supplier_id] = $term ? $term->name : '';
                } else {
                    $supplier_shipping_classes[$supplier_id] = __('常溫', 'cart-manager');
                }
            }
        }
        
        wp_send_json_success([
            'weights'                   => $supplier_weights,
            'supplier_names'            => $supplier_names,
            'supplier_shipping_classes' => $supplier_shipping_classes, // (*** 全新 ***)
        ]);
    }

    /**
     * 載入前端所需的 CSS 與 JavaScript。
     */
    public function enqueue_scripts() {
        if (!is_product() && !is_cart() && !is_shop()) {
            return;
        }
        wp_enqueue_style('wfs-progress-bar-style', WFS_PLUGIN_URL . 'assets/css/wfs-progress-bar.css', [], '1.5.4');
        wp_enqueue_script('wfs-progress-bar-script', WFS_PLUGIN_URL . 'assets/js/wfs-progress-bar.js', ['jquery'], '1.5.4', true);
        wp_localize_script('wfs-progress-bar-script', 'wfs_progress_params', $this->get_js_params());
    }

    /**
     * 在頁面上渲染一個空的 <div> 容器。
     */
    public function render_progress_bar_container() {
        $params = $this->get_js_params();
        // 只有在確定有「有效」的運送方式時，才輸出容器
        if (empty($params['shipping_methods'])) {
            return;
        }
        echo '<div class="wfs-progress-bar-wrapper woocommerce">';
        echo '<div id="wfs-shipping-progress-bar-container"></div>';
        echo '</div>';
    }
    
    /**
     * 準備所有需要傳遞給 JavaScript 的資料 (*** 已修改：傳遞所有供應商的溫層 ***)
     */
    private function get_js_params() {
        $limits = array_filter((array) get_option('wfs_shipping_weight_limits', []));
        $shipping_methods_data = [];

        if (!empty($limits)) {
            $shipping_zones = WC_Shipping_Zones::get_zones();
            $shipping_zones[0] = new WC_Shipping_Zone(0);
            foreach ($shipping_zones as $zone_data) {
                $zone = ($zone_data instanceof WC_Shipping_Zone) ? $zone_data : WC_Shipping_Zones::get_zone($zone_data['id']);
                if (!$zone) continue;
                $enabled_methods_in_zone = $zone->get_shipping_methods(true);
                foreach ($enabled_methods_in_zone as $instance_id => $shipping_method) {
                    $method_rate_id = $shipping_method->get_rate_id();
                    if (isset($limits[$method_rate_id])) {
                        $shipping_methods_data[] = [
                            'name'       => $shipping_method->get_title(),
                            'max_weight' => (float) $limits[$method_rate_id]
                        ];
                    }
                }
            }
            uasort($shipping_methods_data, function ($a, $b) {
                return $a['max_weight'] <=> $b['max_weight'];
            });
        }
        
        $product_weight = 0;
        $product_supplier_id = null;
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product && class_exists('CM_Cart_Display')) {
                $product_weight = (float) $product->get_weight();
                $product_supplier_id = CM_Cart_Display::get_item_supplier_id(['data' => $product]);
            }
        }
        
        $initial_supplier_weights = [];
        $supplier_names = [];
        $supplier_shipping_classes = []; // (*** 全新 ***)

        if ( class_exists('CM_Cart_Display') ) {
            $initial_supplier_weights = CM_Cart_Display::get_cart_weight_by_supplier();
            
            $supplier_groups = [];
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $supplier_id = CM_Cart_Display::get_item_supplier_id($cart_item);
                $supplier_groups[$supplier_id][] = $cart_item;
            }

            foreach (array_keys($initial_supplier_weights) as $supplier_id) {
                $supplier_names[$supplier_id] = CM_Cart_Display::get_supplier_display_name($supplier_id);
                
                if(isset($supplier_groups[$supplier_id])) {
                    $first_item = reset($supplier_groups[$supplier_id]);
                    $product = $first_item['data'];
                    if ($product) {
                        $shipping_class_slug = $product->get_shipping_class();
                        if ($shipping_class_slug) {
                            $term = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
                            $supplier_shipping_classes[$supplier_id] = $term ? $term->name : '';
                        } else {
                            $supplier_shipping_classes[$supplier_id] = __('常溫', 'cart-manager');
                        }
                    }
                }
            }
        }

        return [
            'shipping_methods'        => array_values($shipping_methods_data),
            'i18n'                    => ['current_weight' => __('目前總重', 'woocommerce-function-suite')],
            'product_weight'          => $product_weight,
            'product_supplier_id'     => $product_supplier_id, 
            'initial_supplier_weights' => $initial_supplier_weights,
            'supplier_names'           => $supplier_names,
            'supplier_shipping_classes' => $supplier_shipping_classes, // (*** 全新 ***)
            'nonce'                   => wp_create_nonce('wfs-progress-nonce'),
            'ajax_url'                => admin_url('admin-ajax.php'),
        ];
    }

    /**
     * 輔助函式：取得目前購物車的運送類別名稱。
     */
    private function get_cart_shipping_class_name() {
        if (WC()->cart && !WC()->cart->is_empty()) {
            $cart_contents = WC()->cart->get_cart();
            $first_cart_item = reset($cart_contents);
            if ($first_cart_item && isset($first_cart_item['data'])) {
                $shipping_class_id = $first_cart_item['data']->get_shipping_class_id();
                if ($shipping_class_id) {
                    $shipping_class = get_term($shipping_class_id, 'product_shipping_class');
                    if ($shipping_class && !is_wp_error($shipping_class)) {
                        return $shipping_class->name;
                    }
                } else {
                    return '常溫'; 
                }
            }
        }
        return '';
    }
}