<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Discord_Notify
 *
 * 負責發送 WooCommerce 事件通知到 Discord。
 */
class WFS_Discord_Notify {

    private $webhook_url = '';
    private $low_stock_cats = [];

    public function __construct() {
        $this->webhook_url = get_option('wfs_discord_webhook');
        $this->low_stock_cats = (array) get_option('wfs_discord_low_stock_cats', []);

        if (empty($this->webhook_url)) {
            return;
        }

        add_action('woocommerce_new_order', [$this, 'send_new_order_notification'], 10, 1);
        add_action('woocommerce_low_stock', [$this, 'send_low_stock_notification'], 10, 1);
    }

    /**
     * 發送新訂單的通知。
     */
    public function send_new_order_notification($order_id) {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        if ($order->get_meta('_discord_notification_sent')) return;

        $fields = [];
        
        $fields[] = ['name' => '訂購人', 'value' => $order->get_formatted_billing_full_name(), 'inline' => true];
        $fields[] = ['name' => '訂購電話', 'value' => $order->get_billing_phone(), 'inline' => true];
        $fields[] = ['name' => '電子郵件', 'value' => $order->get_billing_email(), 'inline' => false];

        if ($order->get_shipping_first_name() || $order->get_shipping_last_name()) {
            $shipping_phone = $order->get_meta('_shipping_phone') ?: $order->get_billing_phone();
            $fields[] = ['name' => '收件人', 'value' => $order->get_formatted_shipping_full_name(), 'inline' => true];
            $fields[] = ['name' => '收件電話', 'value' => $shipping_phone, 'inline' => true];
        }
        
        $fields[] = ['name' => '---', 'value' => "\u{200B}", 'inline' => false];

        $order_total_raw = $order->get_formatted_order_total();
        $order_total_clean = html_entity_decode(strip_tags($order_total_raw));

        $fields[] = ['name' => '訂單總額', 'value' => $order_total_clean, 'inline' => true];
        $fields[] = ['name' => '付款方式', 'value' => $order->get_payment_method_title(), 'inline' => true];

        $embed = [
            'title' => '🎉 新訂單成立！',
            'description' => "訂單編號： **#{$order->get_order_number()}**",
            'color' => hexdec('58B957'),
            'fields' => $fields,
            'footer' => ['text' => get_bloginfo('name') . ' - ' . wp_date('Y-m-d H:i:s')],
        ];

        $response = $this->send_to_discord(['embeds' => [$embed]]);

        if (!is_wp_error($response)) {
            $order->update_meta_data('_discord_notification_sent', 'true');
            $order->save();
        }
    }

    /**
     * 發送庫存短缺的通知。(*** 已移除編輯商品連結 ***)
     */
    public function send_low_stock_notification($product) {
        if (!$product instanceof WC_Product) return;

        if (!empty($this->low_stock_cats)) {
            $product_cats = wc_get_product_term_ids($product->get_id(), 'product_cat');
            $intersection = array_intersect($product_cats, $this->low_stock_cats);
            if (empty($intersection)) {
                return;
            }
        }

        $stock_quantity = $product->get_stock_quantity();

        $embed = [
            'title' => '⚠️ 商品庫存短缺警告！',
            'description' => "商品 **{$product->get_name()}** 的庫存量已不足！",
            'color' => hexdec('F0AD4E'),
            'fields' => [
                ['name' => 'SKU', 'value' => $product->get_sku() ?: 'N/A', 'inline' => true],
                ['name' => '剩餘庫存', 'value' => $stock_quantity, 'inline' => true],
            ],
            'footer' => ['text' => get_bloginfo('name') . ' - ' . wp_date('Y-m-d H:i:s')],
        ];

        $this->send_to_discord(['embeds' => [$embed]]);
    }

    /**
     * 核心發送函式。
     */
    private function send_to_discord($message_body) {
        $response = wp_remote_post($this->webhook_url, [
            'headers'   => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'      => json_encode($message_body),
            'method'    => 'POST',
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            error_log('Discord Webhook Error: ' . $response->get_error_message());
        }

        return $response;
    }
}