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

        add_action('woocommerce_order_status_processing', [$this, 'send_new_order_notification'], 20, 1);
        add_action('woocommerce_low_stock', [$this, 'send_low_stock_notification'], 10, 1);
    }

    /**
     * 發送新訂單的通知。(*** 已修改：支援多筆訂單 ***)
     */
    public function send_new_order_notification($order_id) {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        // --- (*** 關鍵修正 ***) ---
        // 1. 如果這是一張子訂單，就直接退出，避免重複通知
        if ( $order->get_parent_id() > 0 ) {
            return;
        }
        
        // 2. 檢查是否已發送過 (現在檢查父訂單)
        if ($order->get_meta('_discord_notification_sent')) return;
        
        // 3. 獲取所有相關訂單
        $all_orders = [];
        if ( $order->get_meta('_cm_order_split_parent') ) {
            $child_orders = wc_get_orders(['parent' => $order_id, 'limit' => -1]);
            $all_orders = array_merge([$order], $child_orders);
        } else {
            $all_orders = [$order];
        }
        // --- (*** 修正完畢 ***) ---

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

        // --- (*** 關鍵修正：計算真實總金額 ***) ---
        $grand_total = 0;
        foreach ($all_orders as $sub_order) {
            $grand_total += $sub_order->get_total();
        }
        // 因為 cart-manager 會複製運費，所以我們需要減去多餘的運費
        if (count($all_orders) > 1) {
             $grand_total -= ($order->get_shipping_total() * (count($all_orders) - 1));
        }
        $order_total_clean = html_entity_decode(strip_tags(wc_price($grand_total, ['currency' => $order->get_currency()])));
        // --- (*** 修正完畢 ***) ---

        $fields[] = ['name' => '訂單總額', 'value' => $order_total_clean, 'inline' => true];
        $fields[] = ['name' => '付款方式', 'value' => $order->get_payment_method_title(), 'inline' => true];
        
        // (*** 關鍵修改：組合訂單編號 ***)
        $order_numbers = [];
        foreach($all_orders as $sub_order) {
            $order_numbers[] = "#" . $sub_order->get_order_number();
        }
        $description = "訂單編號：" . implode(', ', $order_numbers);


        $embed = [
            'title' => '🎉 新訂單成立！',
            'description' => $description, // 使用新的描述
            'color' => hexdec('58B957'),
            'fields' => $fields,
            'footer' => ['text' => get_bloginfo('name') . ' - ' . wp_date('Y-m-d H:i:s')],
        ];

        $response = $this->send_to_discord(['embeds' => [$embed]]);

        if (!is_wp_error($response)) {
            // 在父訂單上標記已發送
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