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
    if (strpos($hook, 'wfs') !== false) {
        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

        wp_enqueue_script(
            'tiptip',
            WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js',
            ['jquery'],
            WC_VERSION,
            true
        );

        wp_enqueue_script(
            'woocommerce_admin',
            WC()->plugin_url() . '/assets/js/admin/woocommerce_admin.min.js',
            ['jquery', 'tiptip'],
            WC_VERSION,
            true
        );
    }
});

add_action('admin_footer', function () {
    if (isset($_GET['page']) && strpos($_GET['page'], 'wfs') !== false) {
        echo "<script>
            jQuery(function($){
                // 如果 tipTip 已經被載入，就觸發初始化
                if ($.fn.tipTip) {
                    $('.woocommerce-help-tip').tipTip({
                        attribute: 'data-tip',
                        fadeIn: 200,
                        fadeOut: 200,
                        delay: 200
                    });
                } else {
                    console.warn('tipTip 未被載入');
                }
            });
        </script>";
    }
});
