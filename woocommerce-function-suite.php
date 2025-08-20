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
    // 只在我們自己的外掛頁面載入相關資源
    if (strpos($hook, 'wfs-settings') === false && strpos($hook, 'wfs-weight-control') === false && strpos($hook, 'wfs-discord-notify') === false) {
        return;
    }

    // 載入 WooCommerce 預設的 admin CSS
    wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

    // 載入 tiptip 函式庫 (這是 WordPress 的標準做法)
    wp_enqueue_script('jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.js', array('jquery'), WC_VERSION, true);

    // 載入我們自己的 wfs-admin.js 檔案
    wp_enqueue_script(
        'wfs-admin-script', // 給我們的腳本一個獨一無二的名稱
        WFS_PLUGIN_URL . 'assets/js/wfs-admin.js', // 檔案路徑
        array('jquery', 'jquery-tiptip'), // **關鍵：** 告訴 WordPress 這個腳本依賴 jQuery 和 tiptip
        '1.0.1', // 版本號
        true // true 代表放在頁面底部載入
    );
});
