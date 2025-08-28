<?php

add_action('admin_menu', function() {
    add_menu_page(
        'Woo功能整合設定',
        'Woo功能整合',
        'manage_options',
        'wfs-settings',
        'wfs_render_main_page',
        'dashicons-admin-generic',
        56
    );

    // 子頁：重量控制
    add_submenu_page(
        'wfs-settings',
        '重量控制',
        '重量控制',
        'manage_options',
        'wfs-weight-control',
        'wfs_render_weight_control_page'
    );

    // 子頁：Discord 通知
    add_submenu_page(
        'wfs-settings',
        'Discord 通知',
        'Discord 通知',
        'manage_options',
        'wfs-discord-notify',
        'wfs_render_discord_page'
    );
});

// 註冊所有設定
add_action('admin_init', function() {
    // 主設定頁
    register_setting('wfs_settings_group', 'wfs_enable_shipping_control');
    register_setting('wfs_settings_group', 'wfs_enable_admin_fields');
    register_setting('wfs_settings_group', 'wfs_enable_minimum_order');
    register_setting('wfs_settings_group', 'wfs_minimum_order_amount');

    // 重量控制子頁
    register_setting('wfs_weight_group', 'wfs_enable_weight_control');
    register_setting('wfs_weight_group', 'wfs_enable_progress_bar');
    register_setting('wfs_weight_group', 'wfs_shipping_weight_limits', ['type' => 'array', 'default' => []]);

    // Discord 通知子頁
    register_setting('wfs_discord_group', 'wfs_enable_discord_notify');
    register_setting('wfs_discord_group', 'wfs_discord_webhook');
    register_setting('wfs_discord_group', 'wfs_discord_low_stock_cats', ['type' => 'array', 'default' => []]);
});

// --- 以下為所有頁面的完整渲染函式 ---

// 主頁面渲染函式 (*** 已恢復完整程式碼 ***)
function wfs_render_main_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce 功能整合設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="wfs_enable_shipping_control">運費控制</label>
                        <?php echo wc_help_tip('勾選啟用運費控制，只要商品有任何折扣或為精選商品，將不計算在免運費門檻中。'); ?>
                    </th>
                    <td class="forminp forminp-checkbox">
                        <fieldset>
                            <input type="checkbox" name="wfs_enable_shipping_control" value="yes" <?php checked(get_option('wfs_enable_shipping_control'), 'yes'); ?>>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="wfs_enable_admin_fields">後台訂單欄位新增</label>
                        <?php echo wc_help_tip('勾選啟用後，後台將顯示 LINE 名稱、訂單備註、重覆 IP 等自訂欄位。'); ?>
                    </th>
                    <td class="forminp forminp-checkbox">
                        <fieldset>
                            <input type="checkbox" name="wfs_enable_admin_fields" value="yes" <?php checked(get_option('wfs_enable_admin_fields'), 'yes'); ?>>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="wfs_minimum_order_amount">最小訂購金額</label>
                        <?php echo wc_help_tip('啟用後，顧客需達到指定金額才能結帳。'); ?>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <input 
                                name="wfs_enable_minimum_order" 
                                id="wfs_enable_minimum_order" 
                                type="checkbox" 
                                value="yes" 
                                <?php checked(get_option('wfs_enable_minimum_order'), 'yes'); ?>>
                            
                            <label for="wfs_minimum_order_amount" style="margin: 0 5px;">訂單需滿</label>
                            <input 
                                name="wfs_minimum_order_amount" 
                                id="wfs_minimum_order_amount" 
                                type="number" 
                                step="1" 
                                min="0"
                                value="<?php echo esc_attr(get_option('wfs_minimum_order_amount', '')); ?>" 
                                class="small-text"
                                placeholder="金額">
                            <span>元</span>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 重量控制子頁面渲染函式 (*** 已更新為更緊湊的排版 ***)
function wfs_render_weight_control_page() {
    ?>
    <div class="wrap">
        <h1>重量控制設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_weight_group'); ?>

            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="wfs_enable_weight_control">啟用重量限制</label>
                            <?php echo wc_help_tip('勾選以啟用全站的運送重量限制功能。'); ?>
                        </th>
                        <td class="forminp forminp-checkbox">
                            <fieldset>
                                <input name="wfs_enable_weight_control" id="wfs_enable_weight_control" type="checkbox" value="yes" <?php checked(get_option('wfs_enable_weight_control'), 'yes'); ?>>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="wfs_enable_progress_bar">運送重量進度條</lable>
                            <?php echo wc_help_tip('勾選啟用後，在商品、購物車頁面中，將顯示用戶目前重量進度條。'); ?>
                        </th>
                        <td class="forminp forminp-checkbox">
                            <fieldset>
                                <input type="checkbox" name="wfs_enable_progress_bar" value="yes" <?php checked(get_option('wfs_enable_progress_bar'), 'yes'); ?>>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2 class="title">各運送方式重量限制</h2>
            <p class="description">為您的每個運送方式設定最大可接受的重量(kg)。留空表示該運送方式不受重量限制。</p>

            <table class="form-table">
                <tbody>
                    <?php
                    $saved_limits = get_option('wfs_shipping_weight_limits', []);
                    $all_zone_data = WC_Shipping_Zones::get_zones();
                    $all_zone_data[] = array('id' => 0); 

                    foreach ($all_zone_data as $zone_data) {
                        $zone = WC_Shipping_Zones::get_zone($zone_data['id']);
                        if (!$zone || empty($zone->get_shipping_methods())) continue;

                        // 顯示運送區域的標題
                        ?>
                        <tr valign="top">
                            <td colspan="2" style="padding: 15px 0; font-size: 1.2em;">
                                <strong><?php echo esc_html($zone->get_zone_name()); ?></strong>
                            </td>
                        </tr>
                        <?php
                        
                        foreach ($zone->get_shipping_methods() as $shipping_method) {
                            $method_id = $shipping_method->get_rate_id();
                            $current_limit = isset($saved_limits[$method_id]) ? $saved_limits[$method_id] : '';
                            ?>
                            <tr valign="top">
                                <th scope="row" class="titledesc">
                                    <label for="wfs_shipping_weight_limit_<?php echo esc_attr($method_id); ?>">
                                        <?php echo esc_html($shipping_method->get_title()); ?>
                                    </label>
                                </th>
                                <td class="forminp forminp-text">
                                    <input 
                                        name="wfs_shipping_weight_limits[<?php echo esc_attr($method_id); ?>]" 
                                        id="wfs_shipping_weight_limit_<?php echo esc_attr($method_id); ?>" 
                                        type="number" 
                                        step="0.01" 
                                        style="width: 100px;"
                                        value="<?php echo esc_attr($current_limit); ?>"
                                        placeholder="無限制"> kg
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Discord 通知子頁面渲染函式 (這是您已有的、排版正確的版本)
function wfs_render_discord_page() {
    ?>
    <div class="wrap">
        <h1>Discord 通知設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_discord_group'); ?>
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="wfs_enable_discord_notify">啟用此功能</label>
                        </th>
                        <td class="forminp forminp-checkbox">
                            <fieldset>
                                <input name="wfs_enable_discord_notify" id="wfs_enable_discord_notify" type="checkbox" value="yes" <?php checked(get_option('wfs_enable_discord_notify'), 'yes'); ?>>
                                <p class="description">勾選以啟用 Discord 新訂單及庫存短缺通知。</p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="wfs_discord_webhook">Webhook URL</label>
                        </th>
                        <td class="forminp forminp-text">
                            <input name="wfs_discord_webhook" id="wfs_discord_webhook" type="text" style="width: 350px;" value="<?php echo esc_attr(get_option('wfs_discord_webhook')); ?>">
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="wfs_discord_low_stock_cats">庫存短缺通知分類</label>
                            <?php echo wc_help_tip('只針對選定的商品分類發送庫存短缺通知。如果留空，則所有商品都會通知。'); ?>
                        </th>
                        <td class="forminp forminp-select">
                            <select name="wfs_discord_low_stock_cats[]" id="wfs_discord_low_stock_cats" multiple="multiple" class="wc-enhanced-select" style="width: 350px;" data-placeholder="搜尋商品分類...">
                                <?php
                                $product_categories = get_terms('product_cat', ['hide_empty' => false]);
                                $selected_cats = (array) get_option('wfs_discord_low_stock_cats', []);
                                if (!empty($product_categories) && !is_wp_error($product_categories)) {
                                    foreach ($product_categories as $category) {
                                        echo '<option value="' . esc_attr($category->term_id) . '"' . selected(in_array($category->term_id, $selected_cats), true, false) . '>' . esc_html($category->name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}