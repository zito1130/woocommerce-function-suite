<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 確保我們可以使用 PhpSpreadsheet 函式庫
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Class WFS_Order_Export
 *
 * 處理 WooCommerce 訂單匯出為多種 Excel 格式的功能。
 * 整合了 7-11, 全家, 新竹貨運的匯出邏輯。
 */
class WFS_Order_Export {

    public function __construct() {
        // 宣告與 HPOS 的兼容性
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);

        // 1. 新增後台批次操作選項
        add_filter('bulk_actions-edit-shop_order', [$this, 'add_export_bulk_actions']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_export_bulk_actions']);

        // 2. 處理批次操作的邏輯
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_export_bulk_action'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_export_bulk_action'], 10, 3);

        // 3. 在訂單列表新增「最近匯出日期」欄位標題
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_last_exported_column']);
        add_filter('manage_edit-shop_order_columns', [$this, 'add_last_exported_column']);

        // 4. 填入「最近匯出日期」欄位的內容
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_last_exported_column'], 10, 2);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_last_exported_column'], 10, 2);
    }

    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function add_export_bulk_actions($actions) {
        $actions['export_orders_711'] = __('匯出 Excel (7-11)', 'woocommerce-function-suite');
        $actions['export_orders_familymart'] = __('匯出 Excel (全家)', 'woocommerce-function-suite');
        $actions['export_orders_hct_remit'] = __('匯出 Excel (新竹貨運-匯款)', 'woocommerce-function-suite');
        $actions['export_orders_hct_cash'] = __('匯出 Excel (新竹貨運-到付)', 'woocommerce-function-suite');
        return $actions;
    }

    public function handle_export_bulk_action($redirect_to, $action, $post_ids) {
        if (strpos($action, 'export_orders_') !== 0) {
            return $redirect_to;
        }

        try {
            $spreadsheet = null;
            $sheet = null;
            $start_row = 1;

            // --- *** 關鍵修正點：根據 action 分開處理檔案的建立方式 *** ---
            switch ($action) {
                case 'export_orders_711':
                case 'export_orders_familymart':
                    $template_file = ($action === 'export_orders_711') ? '711-export-example.xlsm' : 'fami-export-example.xls';
                    $template_path = WFS_PLUGIN_PATH . 'assets/templates/' . $template_file;
                    if (!file_exists($template_path)) {
                        wp_die("範本檔案遺失: {$template_file}");
                    }
                    $spreadsheet = IOFactory::load($template_path);
                    $sheet = $spreadsheet->getActiveSheet();
                    $start_row = ($action === 'export_orders_711') ? 7 : 21;
                    break;

                case 'export_orders_hct_remit':
                case 'export_orders_hct_cash':
                    // 對於新竹物流，我們恢復成從零建立檔案，以確保最高相容性
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $headers = ['序號', '訂單號', '收件人姓名', '收件人地址', '收件人電話', '託運備住', '商品別編號', '商品數量', '材積', '代收貨款', '指定配送日期', '指定配送時間'];
                    $sheet->fromArray($headers, null, 'A1');
                    $start_row = 2; // 資料從第二行開始
                    break;

                default:
                    return $redirect_to;
            }

            if (!$spreadsheet || !$sheet) {
                wp_die('無法初始化 Excel 檔案。');
            }

            $products_list = ["特產", "餅乾", "泡麵", "果凍", "飲料"];
            $current_row = $start_row;

            foreach ($post_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;

                $method_name = match ($action) {
                    'export_orders_711' => '7-11',
                    'export_orders_familymart' => '全家',
                    'export_orders_hct_remit' => '新竹貨運(匯款)',
                    'export_orders_hct_cash' => '新竹貨運(到付)',
                    default => '未知格式'
                };
                $order->add_order_note('已匯出為 ' . $method_name . ' 格式 - ' . current_time('mysql'));
                $order->update_meta_data('_last_exported_date', current_time('mysql'));
                $order->save();

                $recipient_name = $order->get_shipping_last_name() . $order->get_shipping_first_name();
                $recipient_phone = $order->get_shipping_phone() ?: $order->get_billing_phone();
                $store_id = $order->get_meta('_shipping_cvs_store_ID');
                $address = $order->get_shipping_state() . $order->get_shipping_city() . $order->get_shipping_address_1() . $order->get_shipping_address_2();
                $product_mock = $products_list[array_rand($products_list)];
                $temperatureMeta = $order->get_meta('temperature-layer');
                $temperatureList = is_array($temperatureMeta) ? $temperatureMeta : [$temperatureMeta];
                $temperature = in_array('frozen', $temperatureList) ? '冷凍' : '常溫';
                $total_price = $order->get_total() - $order->get_total_refunded();
                $shipping_fee = $order->get_shipping_total();
                $item_price = $total_price - $shipping_fee;

                $rowData = match ($action) {
                    'export_orders_711' => [$recipient_name, $recipient_phone, $store_id, $temperature, $product_mock, $item_price, $shipping_fee],
                    'export_orders_familymart' => [$recipient_name, $recipient_phone, $store_id, $item_price, $shipping_fee, $product_mock, $temperature],
                    'export_orders_hct_remit' => ["", "SS{$order_id}", $recipient_name, $address, $recipient_phone, $product_mock, "", "1", "3", "0", "", "2"],
                    'export_orders_hct_cash' => ["", "SS{$order_id}", $recipient_name, $address, $recipient_phone, $product_mock, "", "1", "3", $total_price, "", "2"],
                    default => [],
                };

                if (!empty($rowData)) {
                    $sheet->fromArray($rowData, null, "A{$current_row}");
                    $current_row++;
                }
            }
            
            $extension = ($action === 'export_orders_711') ? 'xlsm' : 'xls';
            $content_type = ($extension === 'xlsm') ? 'application/vnd.ms-excel.sheet.macroEnabled.12' : 'application/vnd.ms-excel';
            $writer = ($extension === 'xlsm') ? new Xlsx($spreadsheet) : new Xls($spreadsheet);

            $filename = sprintf("%s_訂單匯出_%s.%s", $method_name, date('Ymd-His'), $extension);

            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            wp_die('建立 Excel 檔案時發生錯誤: ' . $e->getMessage());
        }
        
        return $redirect_to;
    }

    public function add_last_exported_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_date' === $key) {
                $new_columns['last_exported'] = '最近匯出日期';
            }
        }
        return $new_columns;
    }

    public function render_last_exported_column($column, $order_id) {
        if ($column === 'last_exported') {
            $order = wc_get_order($order_id);
            if ($order) {
                $exported_date = $order->get_meta('_last_exported_date', true);
                echo $exported_date ? esc_html($exported_date) : '尚未匯出';
            }
        }
    }
}