<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Shipping_Control
 *
 * 控制運費計算，排除特價品與精選商品來計算免運門檻。
 */
class WFS_Shipping_Control {

    /**
     * 建構子：註冊所有需要的 WordPress hooks。
     */
    public function __construct() {
        // 使用 'woocommerce_package_rates' 這個 hook 來動態調整運費
        add_filter('woocommerce_package_rates', [$this, 'adjust_shipping_rates'], 10, 2);
    }

    /**
     * 計算真正符合免運資格的購物車總金額。
     * 排除所有形式的折扣商品與精選商品。
     *
     * @param WC_Cart $cart 購物車物件。
     * @return float 符合資格的總金額。
     */
    public function calculate_qualified_amount($cart) {
        $total = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];

            // 強化版折扣檢測
            $original_price = (float) $product->get_regular_price();
            $current_price = (float) $product->get_price();

            // 判斷商品是否有任何形式的折扣
            $has_discount = (
                $original_price > $current_price ||               // 1. 商品本身設定了特價
                $cart_item['line_subtotal'] != $cart_item['line_total']  // 2. 購物車層級的折扣 (例如：優惠券)
            );

            // 判斷是否為精選商品
            $is_featured = $product->is_featured();

            // 如果商品「沒有折扣」且「不是精選商品」，才將其金額計入免運門檻
            if (!$has_discount && !$is_featured) {
                $total += (float) $cart_item['line_total'];
            }
        }

        return $total;
    }

    /**
     * 調整運費稅率。
     *
     * @param array $rates 當前的運費選項陣列。
     * @param array $package 包含購物車內容的包裹陣列。
     * @return array 修改後的運費選項陣列。
     */
    public function adjust_shipping_rates($rates, $package) {
        // 首先，檢查是否有「免運費」的優惠券，如果有，則直接回傳，讓優惠券優先。
        $applied_coupons = WC()->cart->get_applied_coupons();
        foreach ($applied_coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_free_shipping()) {
                return $rates; // 有免運券，不做任何修改
            }
        }

        // 計算符合免運資格的總金額
        $qualified_amount = $this->calculate_qualified_amount(WC()->cart);

        // 遍歷所有可用的運送方式
        foreach ($rates as $rate_id => $rate) {
            // 只處理 WooCommerce 內建的 'free_shipping' (免運) 類型的運送方式
            if ('free_shipping' === $rate->get_method_id()) {
                // 檢查訂單金額是否達到免運門檻
                if ($rate->get_meta_data()['min_amount'] > $qualified_amount) {
                    // 如果未達到門檻，就從選項中「移除」這個免運選項
                    unset($rates[$rate_id]);
                }
            }
        }

        return $rates;
    }
}