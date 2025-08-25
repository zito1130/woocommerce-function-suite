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

        // 掛載 HTML 容器
        add_action('woocommerce_before_cart_totals', [$this, 'render_progress_bar_container']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_progress_bar_container']);
        add_action('woocommerce_before_shop_loop', [$this, 'render_progress_bar_container']);

        // *** 新增：註冊 AJAX action ***
        add_action('wp_ajax_wfs_get_cart_weight_for_progress', [$this, 'ajax_get_cart_weight']);
        add_action('wp_ajax_nopriv_wfs_get_cart_weight_for_progress', [$this, 'ajax_get_cart_weight']);
    }

    /**
     * *** 新增：AJAX 處理函式 ***
     * 當前端 JS 請求時，回傳當前最新的購物車總重量。
     */
    public function ajax_get_cart_weight() {
        // 安全性檢查
        check_ajax_referer('wfs-progress-nonce', 'nonce');
        
        wp_send_json_success([
            'weight' => WC()->cart->get_cart_contents_weight()
        ]);
    }

    /**
     * 載入前端所需的 CSS 與 JavaScript。
     */
    public function enqueue_scripts() {
        if (!is_product() && !is_cart() && !is_shop()) {
            return;
        }

        wp_enqueue_style('wfs-progress-bar-style', WFS_PLUGIN_URL . 'assets/css/wfs-progress-bar.css', [], '1.5.1');
        wp_enqueue_script('wfs-progress-bar-script', WFS_PLUGIN_URL . 'assets/js/wfs-progress-bar.js', ['jquery'], '1.5.1', true);
        wp_localize_script('wfs-progress-bar-script', 'wfs_progress_params', $this->get_js_params());
    }

    /**
     * 在頁面上渲染一個空的 <div> 容器。
     */
    public function render_progress_bar_container() {
        $limits = get_option('wfs_shipping_weight_limits', []);
        if (empty(array_filter($limits))) {
            return;
        }
        echo '<div id="wfs-shipping-progress-bar-container"></div>';
    }
    
    /**
     * 準備所有需要傳遞給 JavaScript 的資料。
     */
    private function get_js_params() {
        $limits = array_filter((array) get_option('wfs_shipping_weight_limits', []));
        $shipping_methods_data = [];
        if (!empty($limits)) {
            asort($limits);
            foreach ($limits as $method_id => $max_weight) {
                $instance_id = substr($method_id, strrpos($method_id, ':') + 1);
                if (empty($instance_id) || !is_numeric($instance_id)) continue;
                $shipping_method = WC_Shipping_Zones::get_shipping_method($instance_id);
                if ($shipping_method) {
                    $shipping_methods_data[] = ['name' => $shipping_method->get_title(), 'max_weight' => (float) $max_weight];
                }
            }
        }
        $product_weight = 0;
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product) {
                $product_weight = (float) $product->get_weight();
            }
        }
        return [
            // 我們不再傳遞 cart_weight，因為 JS 會自己去要
            'shipping_methods' => $shipping_methods_data,
            'i18n'             => ['current_weight' => __('目前總重', 'woocommerce-function-suite')],
            'product_weight'   => $product_weight,
            'nonce'            => wp_create_nonce('wfs-progress-nonce'), // *** 新增：傳遞 Nonce 給 JS ***
            'ajax_url'         => admin_url('admin-ajax.php'),      // *** 新增：傳遞 AJAX URL 給 JS ***
        ];
    }
}