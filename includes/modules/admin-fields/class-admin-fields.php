<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Admin_Fields
 *
 * 在 WooCommerce 訂單列表頁新增自訂欄位。
 * 包含：顧客備註/商家備註、重覆 IP 訂單、LINE 名稱。
 */
class WFS_Admin_Fields {

    /**
     * 建構子：註冊所有需要的 WordPress hooks。
     */
    public function __construct() {
        // --- 掛載點 Hooks ---

        // 1. 新增欄位標題
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_custom_column_headers'], 20); // HPOS
        add_filter('manage_edit-shop_order_columns', [$this, 'add_custom_column_headers'], 20);

        // 2. 填入欄位內容
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'display_custom_column_content'], 10, 2); // HPOS
        add_action('manage_shop_order_posts_custom_column', [$this, 'display_custom_column_content'], 10, 2);

        // 3. 為備註欄位新增 CSS 圖示樣式
        add_action('admin_head', [$this, 'add_styling_for_notes_column']);
    }

    /**
     * 將所有需要的新欄位標題，一次性地加入到訂單列表中。
     */
    public function add_custom_column_headers($columns) {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            // 在「訂單日期」後方，依序插入我們的自訂欄位
            if ('order_date' === $key) {
                $new_columns['order_notes'] = '備註';
                $new_columns['order_ip'] = '重覆 IP 訂單';
                $new_columns['billing_line_id'] = 'LINE 名稱';
            }
        }
        return $new_columns;
    }

    /**
     * 根據不同的欄位，顯示對應的內容。
     */
    public function display_custom_column_content($column, $order) {
        // 確保我們有一個有效的 WC_Order 物件
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        // 使用 switch 結構來處理不同的欄位內容
        switch ($column) {
            case 'billing_line_id':
                // 從訂單的 meta data 中獲取 LINE 名稱並顯示
                $billing_line_id = $order->get_meta('billing_line_id');
                echo esc_html($billing_line_id);
                break;

            case 'order_ip':
                $ip_address = $order->get_customer_ip_address();
                if ($ip_address) {
                    $duplicate_order_ids = $order->get_meta('duplicate_order_ids');
                    $color = $this->generate_color_from_ip($ip_address);
                    $compressed_ip = $this->compress_ip($ip_address);

                    if (!empty($duplicate_order_ids)) {
                        $order_ids = implode(', ', array_map('esc_html', explode(',', $duplicate_order_ids)));
                        echo '<span style="color: ' . esc_attr($color) . ';">IP: ' . $order_ids . ' (' . esc_html($compressed_ip) . ')</span>';
                    } else {
                        echo '<span style="color: ' . esc_attr($color) . ';">(' . esc_html($compressed_ip) . ')</span>';
                    }
                } else {
                    echo '無 IP 資料';
                }
                break;
                
            case 'order_notes':
                if ($customer_note = $order->get_customer_note()) {
                    echo '<span class="note-on customer tips" data-tip="' . wc_sanitize_tooltip($customer_note) . '">' . __('Yes', 'woocommerce') . '</span>';
                }

                $order_notes = wc_get_order_notes(['order_id' => $order->get_id()]);
                if (!empty($order_notes)) {
                    $latest_note = current($order_notes);
                    $note_count = count($order_notes);
                    $tooltip_text = ($note_count === 1) ? $latest_note->content : $latest_note->content . '<br/><small style="display:block">' . sprintf(_n('Plus %d other note', 'Plus %d other notes', ($note_count - 1), 'woocommerce'), $note_count - 1) . '</small>';
                    echo '<span class="note-on tips" data-tip="' . wc_sanitize_tooltip($tooltip_text) . '">' . __('Yes', 'woocommerce') . '</span>';
                }
                break;
        }
    }

    /**
     * 在 admin header 中注入備註圖示所需的 CSS。
     */
    public function add_styling_for_notes_column() {
        $screen = get_current_screen();
        if ($screen && in_array($screen->id, ['edit-shop_order', 'woocommerce_page_wc-orders'])) {
            echo '<style>
                td.order_notes > .note-on { display: inline-block !important; }
                span.note-on.customer { margin-right: 4px !important; }
                span.note-on.customer::after { font-family: woocommerce !important; content: "\e026" !important; }
            </style>';
        }
    }

    /**
     * 輔助函式：壓縮 IP 位址為短代碼。
     */
    private function compress_ip($ip_address) {
        return substr(md5($ip_address), -8);
    }

    /**
     * 輔助函式：根據 IP 位址生成顏色。
     */
    private function generate_color_from_ip($ip_address) {
        $hash = md5($ip_address);
        return '#' . substr($hash, 0, 6);
    }
}