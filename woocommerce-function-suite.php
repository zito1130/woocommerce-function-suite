<?php
/**
 * Plugin Name: WooCommerce Function Suite
 * Description: 整合 WooCommerce 各項功能模組的後台控制面板
 * Version: 1.0.1
 * Author: zito
 */

if (!defined('ABSPATH')) exit;

define('WFS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WFS_PLUGIN_URL', plugin_dir_url(__FILE__));

// 載入後台設定頁
require_once WFS_PLUGIN_PATH . 'includes/settings-page.php';

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'wfs-') === false) {
        return;
    }

    wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

    // 載入 tiptip 腳本
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
        '1.0.1',
        true
    );
});