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
    // 一個更有效率的寫法，檢查所有你的外掛頁面
    if (strpos($hook, 'wfs-') === false) {
        return;
    }

    // 載入 WooCommerce 的後台 CSS
    wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

    // 載入 tiptip 腳本
    wp_enqueue_script(
        'jquery-tiptip',
        WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.js',
        array('jquery', 'dompurify'), // <-- 修正就在這裡！我們新增了 'dompurify'
        WC_VERSION,
        true
    );

    // 載入你自己寫的後台腳本
    wp_enqueue_script(
        'wfs-admin-script',
        WFS_PLUGIN_URL . 'assets/js/wfs-admin.js',
        array('jquery', 'jquery-tiptip'), // 你的腳本正確地依賴 tipTip
        '1.0.1',
        true
    );
});