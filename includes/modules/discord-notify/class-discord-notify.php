<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Discord_Notify
 *
 * 負責發送 WooCommerce 事件通知到 Discord。
 * 包含：新訂單通知、庫存短缺通知。
 */
class WFS_Discord_Notify {

    private $webhook_url = '';

    /**
     * 建構子：取得設定並註冊所有需要的 WordPress hooks。
     */
    public function __construct() {
        // 從後台設定中，取得使用者儲存的 Webhook URL
        $this->webhook_url = get_option('wfs_discord_webhook');

        // 如果 Webhook URL 為空，則不執行任何動作
        if (empty($this->webhook_url)) {
            return;
        }

        // 1. 掛載「新訂單」通知 (使用 'woocommerce_new_order' hook 更可靠)
        add_action('woocommerce_new_order', [$this, 'send_new_order_notification'], 10, 1);

        // 2. 掛載「庫存短缺」通知
        add_action('woocommerce_low_stock', [$this, 'send_low_stock_notification'], 10, 1);
    }

    /**
     * 發送新訂單的通知。
     */
    public function send_new_order_notification($order_id) {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        // 若已經發送過通知，則不再重複發送 (防止意外的重複觸發)
        if ($order->get_meta('_discord_notification_sent')) return;

        // 準備要發送的訊息內容 (使用 Discord 的 embed 格式，更美觀)
        $embed = [
            'title' => '🎉 新訂單成立！',
            'description' => "訂單編號： **#{$order->get_order_number()}**",
            'color' => hexdec('58B957'), // 綠色
            'fields' => [
                ['name' => '顧客姓名', 'value' => $order->get_formatted_billing_full_name(), 'inline' => true],
                ['name' => '聯絡電話', 'value' => $order->get_billing_phone(), 'inline' => true],
                ['name' => '電子郵件', 'value' => $order->get_billing_email(), 'inline' => false],
                ['name' => '訂單總額', 'value' => $order->get_formatted_order_total(), 'inline' => true],
                ['name' => '付款方式', 'value' => $order->get_payment_method_title(), 'inline' => true],
            ],
            'footer' => ['text' => get_bloginfo('name') . ' - ' . wp_date('Y-m-d H:i:s')],
        ];

        // 發送通知
        $response = $this->send_to_discord(['embeds' => [$embed]]);

        // 更新訂單的 meta 資料，標記為已發送
        if (!is_wp_error($response)) {
            $order->update_meta_data('_discord_notification_sent', 'true');
            $order->save();
        }
    }

    /**
     * 發送庫存短缺的通知。
     */
    public function send_low_stock_notification($product) {
        if (!$product instanceof WC_Product) return;

        $stock_quantity = $product->get_stock_quantity();
        $product_link = get_edit_post_link($product->get_id());

        // 準備訊息內容
        $embed = [
            'title' => '⚠️ 商品庫存短缺警告！',
            'description' => "商品 **{$product->get_name()}** 的庫存量已不足！",
            'color' => hexdec('F0AD4E'), // 橘黃色
            'fields' => [
                ['name' => 'SKU', 'value' => $product->get_sku() ?: 'N/A', 'inline' => true],
                ['name' => '剩餘庫存', 'value' => $stock_quantity, 'inline' => true],
                ['name' => '編輯商品', 'value' => "[點此前往編輯]({$product_link})", 'inline' => false],
            ],
            'footer' => ['text' => get_bloginfo('name') . ' - ' . wp_date('Y-m-d H:i:s')],
        ];

        // 發送通知
        $this->send_to_discord(['embeds' => [$embed]]);
    }

    /**
     * 核心發送函式：將格式化好的訊息發送到 Discord。
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