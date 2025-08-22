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

    // 重量控制子頁
    register_setting('wfs_weight_group', 'wfs_enable_weight_control');
    register_setting('wfs_weight_group', 'wfs_weight_limit');

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
                    <th scope="row">運費控制</th>
                    <td>
                        <?php echo wc_help_tip('勾選啟用運費控制，只要商品有任何折扣或為精選商品，將不計算在免運費門檻中。'); ?>
                        <input type="checkbox" name="wfs_enable_shipping_control" value="yes" <?php checked(get_option('wfs_enable_shipping_control'), 'yes'); ?>>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">後台訂單欄位新增</th>
                    <td>
                        <?php echo wc_help_tip('勾選啟用後，後台將顯示 LINE 名稱、訂單備註、重覆 IP 等自訂欄位。'); ?>
                        <input type="checkbox" name="wfs_enable_admin_fields" value="yes" <?php checked(get_option('wfs_enable_admin_fields'), 'yes'); ?>>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 重量控制子頁面渲染函式 (*** 已恢復完整程式碼 ***)
function wfs_render_weight_control_page() {
    ?>
    <div class="wrap">
        <h1>重量控制設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_weight_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">啟用此功能</th>
                    <td>
                        <input type="checkbox" name="wfs_enable_weight_control" value="yes" <?php checked(get_option('wfs_enable_weight_control'), 'yes'); ?>>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">最大重量限制 (kg)</th>
                    <td>
                        <input type="number" name="wfs_weight_limit" value="<?php echo esc_attr(get_option('wfs_weight_limit', 30)); ?>" step="0.1">
                    </td>
                </tr>
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