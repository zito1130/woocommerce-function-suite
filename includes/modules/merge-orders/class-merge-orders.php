<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Merge_Orders
 *
 * 處理後台訂單合併功能。
 */
class WFS_Merge_Orders {

    public function __construct() {
        // 宣告與 HPOS 的兼容性
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);

        // 新增後台合併訂單的批次操作選項
        add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_action']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_bulk_action']);

        // 處理合併訂單的邏輯
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_action'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_action'], 10, 3);

        // 顯示合併成功後的提示訊息
        add_action('admin_notices', [$this, 'show_merge_notice']);

        // 在訂單列表新增「合併狀態」欄位
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_merge_status_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'show_merge_status_column'], 10, 2);

        // 注入自訂的 CSS 樣式
        add_action('admin_head', [$this, 'add_merge_order_styles']);
    }

    /**
     * 宣告與 HPOS (High-Performance Order Storage) 的兼容性。
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * 將「合併訂單」選項加入批次操作選單。
     */
    public function add_bulk_action($actions) {
        $actions['merge_orders'] = __('合併訂單', 'woocommerce-function-suite');
        return $actions;
    }

    /**
     * 處理合併訂單的核心邏輯。
     */
    public function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== 'merge_orders') {
            return $redirect_to;
        }
        check_admin_referer('bulk-orders');

        try {
            $orders = array_map('wc_get_order', $post_ids);
            usort($orders, function($a, $b) {
                return $b->get_date_created()->getTimestamp() - $a->get_date_created()->getTimestamp();
            });
            
            $main_order = array_shift($orders);
            $merged_count = 0;

            // 階段一：複製所有商品
            foreach ($orders as $sub_order) {
                foreach ($sub_order->get_items() as $item) {
                    if (!$item instanceof WC_Order_Item_Product) continue;

                    $new_item = new WC_Order_Item_Product();
                    $new_item->set_props([
                        'product'  => $item->get_product(),
                        'quantity' => $item->get_quantity(),
                        'subtotal' => $item->get_subtotal(),
                        'total'    => $item->get_total(),
                        'taxes'    => $item->get_taxes()
                    ]);
                    $new_item->set_variation_id($item->get_variation_id());
                    foreach ($item->get_meta_data() as $meta) {
                        $new_item->add_meta_data($meta->key, $meta->value, true);
                    }
                    $main_order->add_item($new_item);
                }
            }

            // 階段二：更新主訂單
            $main_order->calculate_totals();
            $main_order->save();

            // 階段三：處理次訂單狀態與備註
            $merged_order_ids = [];
            foreach ($orders as $sub_order) {
                $main_order->add_order_note("已合併訂單 #" . $sub_order->get_id());
                $sub_order->add_order_note("已合併至主訂單 #" . $main_order->get_id());
                $sub_order->set_status('cancelled', '', false);
                $sub_order->update_meta_data('_merged_to_order', $main_order->get_id());
                $sub_order->save();
                $merged_order_ids[] = $sub_order->get_id();
                $merged_count++;
            }
            
            // 階段四：在主訂單儲存合併關係
            $main_order->update_meta_data('_merged_orders', $merged_order_ids);
            $main_order->update_meta_data('_is_merged_order', 'parent');
            $main_order->save();

            return add_query_arg([
                'merged_orders' => $merged_count,
                'main_order' => $main_order->get_id()
            ], $redirect_to);

        } catch (Exception $e) {
            wp_die(__('合併錯誤：', 'woocommerce-function-suite') . $e->getMessage());
        }
    }

    /**
     * 顯示合併成功的提示訊息。
     */
    public function show_merge_notice() {
        if (!empty($_REQUEST['merged_orders'])) {
            $count = intval($_REQUEST['merged_orders']);
            echo '<div class="notice notice-success is-dismissible"><p>'
                . sprintf(_n('已成功合併 %d 筆訂單。', '已成功合併 %d 筆訂單。', $count, 'woocommerce-function-suite'), $count)
                . '</p></div>';
        }
    }

    /**
     * 新增「合併狀態」欄位標題。
     */
    public function add_merge_status_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'order_number') {
                $new_columns['merge_status'] = __('合併狀態', 'woocommerce-function-suite');
            }
        }
        return $new_columns;
    }

    /**
     * 顯示「合併狀態」欄位內容。
     */
    public function show_merge_status_column($column, $order) {
        if ($column === 'merge_status') {
            if (!is_a($order, 'WC_Order')) {
                $order = wc_get_order($order->get_id());
            }
            if (!$order) return;
            
            if ($order->get_meta('_is_merged_order') === 'parent') {
                $merged_ids = $order->get_meta('_merged_orders');
                if (!empty($merged_ids)) {
                    $links = array_map(function($id) {
                        return '<a href="'.get_edit_post_link($id).'" class="merged-id">#'.$id.'</a>';
                    }, $merged_ids);
                    echo '<span class="merged-master-badge" title="已合併訂單：' . esc_attr(implode(', ', $merged_ids)) . '">主： ' . implode(', ', $links) . '</span>';
                }
            }
            
            if ($parent_id = $order->get_meta('_merged_to_order')) {
                echo '<span class="merged-child-badge">併入： <a href="'.get_edit_post_link($parent_id).'">#'.$parent_id.'</a></span>';
            }
        }
    }

    /**
     * 注入欄位所需的 CSS 樣式。
     */
    public function add_merge_order_styles() {
        $screen = get_current_screen();
        if ($screen && in_array($screen->id, ['edit-shop_order', 'woocommerce_page_wc-orders'])) {
            echo '<style>
                .column-merge_status { width: 15%; }
                .merged-master-badge, .merged-child-badge {
                    padding: 3px 8px; border-radius: 4px; display: inline-block;
                    max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                }
                .merged-master-badge { background: #e3f2fd; color: #1976d2; border: 1px solid #90caf9; }
                .merged-master-badge:hover { white-space: normal; overflow: visible; }
                .merged-child-badge { background: #fbe9e7; color: #c62828; border: 1px solid #ffab91; }
                .merged-id { color: #1565c0; text-decoration: none; border-bottom: 1px dotted; }
            </style>';
        }
    }
}