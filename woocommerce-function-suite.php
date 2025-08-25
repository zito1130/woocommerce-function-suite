<?php
/**
 * Plugin Name: WooCommerce Function Suite
 * Description: 整合 WooCommerce 各項功能模組的後台控制面板
 * Version: 1.4.0
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

    // 載入 tiptip 腳本 (並宣告 dompurify 依賴)
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
        '1.4.0',
        true
    );

    // 在 Discord 通知設定頁載入
    if (strpos($hook, 'wfs-discord-notify') !== false) {
        // 載入 Select2 腳本，這是 WooCommerce 可搜尋選單的核心
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('wc-enhanced-select');
    }
});

/**
 * 步驟一：僅載入檔案
 * 在 'plugins_loaded' 這個較早的時間點，我們只把 Class 檔案載入進來備用。
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
}
add_action('plugins_loaded', 'wfs_include_active_modules');


/**
 * 步驟二：初始化 Class
 * 在 'init' 這個比較晚、比較安全的時間點，我們才去 new Class，
 * 這可以確保 Class 內部的 add_filter 能成功掛載到 WooCommerce 上。
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
}
add_action('init', 'wfs_initialize_active_modules');