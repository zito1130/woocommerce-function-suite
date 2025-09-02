<?php
/**
 * Plugin Name: WooCommerce Function Suite
 * Description: 整合 WooCommerce 各項功能模組的後台控制面板
 * Version: 1.8.1
 * Author: zito
 */

if (!defined('ABSPATH')) exit;

define('WFS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WFS_PLUGIN_URL', plugin_dir_url(__FILE__));

// 載入後台設定頁
require_once WFS_PLUGIN_PATH . 'includes/settings-page.php';

// 載入後台專用的 CSS 與 JS
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'wfs-') === false) {
        return;
    }

    wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

    wp_enqueue_script(
        'jquery-tiptip',
        WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.js',
        array('jquery', 'dompurify'),
        WC_VERSION,
        true
    );

    wp_enqueue_script(
        'wfs-admin-script',
        WFS_PLUGIN_URL . 'assets/js/wfs-admin.js',
        array('jquery', 'jquery-tiptip'),
        '1.8.1',
        true
    );

    if (strpos($hook, 'wfs-discord-notify') !== false) {
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('wc-enhanced-select');
    }
});

/**
 * 步驟一：僅載入檔案 (*** 已修正路徑 ***)
 */
function wfs_include_active_modules() {
    if (get_option('wfs_enable_shipping_control') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/shipping-control/class-shipping-control.php';
    }
    if (get_option('wfs_enable_admin_fields') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/admin-fields/class-admin-fields.php';
    }
    if (get_option('wfs_enable_discord_notify') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/discord-notify/class-discord-notify.php';
    }
    if (get_option('wfs_enable_weight_control') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/weight-control/class-weight-control.php';
    }
    if (get_option('wfs_enable_progress_bar') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/progress-bar/class-progress-bar.php';
    }
    if (get_option('wfs_enable_minimum_order') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/minimum-order/class-minimum-order.php';
    }
    if (get_option('wfs_enable_line_notify') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/line-notify/class-line-notify.php';
    }
    if (get_option('wfs_enable_checkout_validation') === 'yes') {
        require_once WFS_PLUGIN_PATH . 'includes/modules/checkout-validation/class-checkout-validation.php';
    }
}
add_action('plugins_loaded', 'wfs_include_active_modules');


/**
 * 步驟二：初始化 Class (*** 已修正大小寫 ***)
 */
function wfs_initialize_active_modules() {
    if (get_option('wfs_enable_shipping_control') === 'yes' && class_exists('WFS_Shipping_Control')) {
        new WFS_Shipping_Control();
    }
    if (get_option('wfs_enable_admin_fields') === 'yes' && class_exists('WFS_Admin_Fields')) {
        new WFS_Admin_Fields();
    }
    if (get_option('wfs_enable_discord_notify') === 'yes' && class_exists('WFS_Discord_Notify')) {
        new WFS_Discord_Notify();
    }
    if (get_option('wfs_enable_weight_control') === 'yes' && class_exists('WFS_Weight_Control')) {
        new WFS_Weight_Control();
    }
    if (get_option('wfs_enable_progress_bar') === 'yes' && class_exists('WFS_Progress_Bar')) {
        new WFS_Progress_Bar();
    }
    if (get_option('wfs_enable_minimum_order') === 'yes' && class_exists('WFS_Minimum_Order')) {
        new WFS_Minimum_Order();
    }
    if (get_option('wfs_enable_line_notify') === 'yes' && class_exists('WFS_Line_Notify')) {
        new WFS_Line_Notify();
    }
    if (get_option('wfs_enable_checkout_validation') === 'yes' && class_exists('WFS_Checkout_Validation')) {
        new WFS_Checkout_Validation();
    }
}
add_action('init', 'wfs_initialize_active_modules');