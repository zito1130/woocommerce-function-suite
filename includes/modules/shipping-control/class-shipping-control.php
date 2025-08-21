<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Shipping_Control
 *
 * 這個版本是 100% 根據你提供的、可運作的程式碼片段，
 * 進行結構化移植，未修改任何核心運費邏輯。
 */
class WFS_Shipping_Control {

    public function __construct() {
        // 掛載點完全遵照你提供的程式碼
        add_filter('woocommerce_package_rates', [$this, 'adjust_shipping_rates'], 10, 2);
    }

    /**
     * 計算合格金額的函式。
     * 邏輯完全來自你提供的程式碼。
     */
    private function calculate_qualified_amount($cart) {
        $total = 0;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product) continue;

            // 強化版折扣檢測
            $original_price = round($product->get_regular_price(), 2);
            $current_price = round($product->get_price(), 2);
            
            $has_discount = (
                $original_price > $current_price ||                        // 商品本身有折扣
                $cart_item['line_subtotal'] != $cart_item['line_total']  // 購物車層級折扣
            );
            
            $is_featured = $product->is_featured();
            
            if (!$has_discount && !$is_featured) {
                $total += round($cart_item['line_total'], 2);
            }
        }
        
        return $total;
    }

    /**
     * 調整運費的主要函式。
     * 邏輯完全來自你提供的程式碼。
     */
    public function adjust_shipping_rates($rates, $package) {
        // 檢查免運費優惠券
        $has_free_shipping_coupon = false;
        if (!empty(WC()->cart->get_coupons())) {
            foreach (WC()->cart->get_coupons() as $coupon) {
                if ($coupon->get_free_shipping()) {
                    $has_free_shipping_coupon = true;
                    break;
                }
            }
        }
        if ($has_free_shipping_coupon) return $rates;

        // 呼叫 Class 內部的 calculate_qualified_amount 方法
        $qualified_amount = $this->calculate_qualified_amount(WC()->cart);

        foreach ($rates as $rate_id => $rate) {
            // 從 rate ID 中解析出 instance ID
            $instance_id = substr($rate_id, strrpos($rate_id, ':') + 1);
            if (empty($instance_id) || !is_numeric($instance_id)) continue;

            $shipping_method = WC_Shipping_Zones::get_shipping_method($instance_id);
            
            if ($shipping_method) {
                $min_amount = (float) $shipping_method->get_option('min_amount', 0);
                
                // 只處理有設定免運門檻的運送方式
                if ($min_amount > 0) {
                    if ($qualified_amount >= $min_amount) {
                        // 如果達標，直接將此運費設為 0
                        $rate->cost = 0;
                    } else {
                        // 如果未達標，確保運費被重設回原始費用
                        $base_cost = (float) $shipping_method->get_option('cost', 0);
                        $rate->cost = $base_cost;
                    }
                }
            }
        }

        return $rates;
    }
}