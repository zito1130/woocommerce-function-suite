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

// 註冊設定（主頁的總開關）
add_action('admin_init', function() {
    $modules = [
        'min_order',
        'shipping_control',
        'checkout_opt',
        'admin_fields',
        'order_sync'
    ];
    foreach ($modules as $module) {
        register_setting('wfs_settings_group', "wfs_enable_{$module}");
    }
    register_setting('wfs_settings_group', 'wfs_min_order_amount');
    register_setting('wfs_settings_group', 'wfs_checkout_opt_validate_name');
    register_setting('wfs_settings_group', 'wfs_checkout_opt_validate_info');
    // 重量控制子頁（後續可以加上更多設定項）
    register_setting('wfs_weight_group', 'wfs_weight_limit');

    // Discord 通知子頁（後續可以加上更多設定項）
    register_setting('wfs_discord_group', 'wfs_discord_webhook');
});

// 主頁面（功能總覽）
function wfs_render_main_page() {
    $fields = [
        'min_order' => '啟用最小訂購金額',
        'shipping_control' => '啟用運費控制',
        'checkout_opt' => '啟用結帳優化',
        'admin_fields' => '啟用後台訂單欄位新增',
        'order_sync' => '啟用拋單功能',
    ];
    ?>
    <div class="wrap">
        <h1>WooCommerce 功能整合設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_settings_group'); ?>
            <table class="form-table">

                <!-- 運費控制 -->
                <tr>
                    <th>運費控制</th>
                    <td>
                        <?php echo wc_help_tip('勾選啟用運費控制，只要商品有任何折扣，將不計算在免運費門檻中。'); ?>
                        <input type="checkbox"
                               name="wfs_enable_shipping_control"
                               value="yes"
                               <?php checked(get_option('wfs_enable_shipping_control'), 'yes'); ?>>
                    </td>
                </tr>

                <!-- 後台訂單欄位新增 -->
                <tr>
                    <th>後台訂單欄位新增</th>
                    <td>
                        <?php echo wc_help_tip('勾選啟用後，後台將顯示LINE名稱、訂單備註、訂單IP位址。'); ?>
                        <input type="checkbox"
                               name="wfs_enable_admin_fields"
                               value="yes"
                               <?php checked(get_option('wfs_enable_admin_fields'), 'yes'); ?>>
                    </td>
                </tr>

                <!-- 拋單功能 -->
                <tr>
                    <th>拋單功能</th>
                    <td>
                        <?php echo wc_help_tip('勾選啟用後，將拋單功能開啟（711、全家、新竹貨運）。'); ?>
                        <input type="checkbox"
                               name="wfs_enable_order_sync"
                               value="yes"
                               <?php checked(get_option('wfs_enable_order_sync'), 'yes'); ?>>
                    </td>
                </tr>

                <!-- 最小訂購金額 -->
                <tr>
                    <th>最小訂購金額</th>
                    <td>
                        <label>
                            <?php echo wc_help_tip('勾選啟用後，將啟用最小訂購金額功能，當訂單金額小於設定金額時，將無法結帳。'); ?>
                            <input type="checkbox"
                                   name="wfs_enable_min_order"
                                   value="yes"
                                   <?php checked(get_option('wfs_enable_min_order'), 'yes'); ?>>
                        </label>
                        <label>
                            最低金額（NT$）：
                            <input type="number"
                                   name="wfs_min_order_amount"
                                   value="<?php echo esc_attr(get_option('wfs_min_order_amount', 0)); ?>"
                                   step="1" min="0">
                        </label>
                    </td>
                </tr>

                <!-- 結帳優化 -->
                <tr>
                    <th>結帳優化</th>
                    <td>
                        <label>
                            <?php echo wc_help_tip('勾選啟用後，會檢查收件人姓名、電話格式是否正確。'); ?>
                            <input type="checkbox"
                                      name="wfs_checkout_opt_validate_name"
                                      value="yes"
                                      <?php checked(get_option('wfs_checkout_opt_validate_name'), 'yes'); ?>>
                            檢查收件資訊
                        </label><br>
                        <label>
                            <?php echo wc_help_tip('勾選啟用後，會有傳送訂購資訊到LINE小視窗。'); ?>
                            <input type="checkbox"
                                      name="wfs_checkout_opt_validate_info"
                                      value="yes"
                                      <?php checked(get_option('wfs_checkout_opt_validate_info'), 'yes'); ?>>
                        </label>
                        <label>傳送訂購資訊</label>
                        <label><br>
                        官方LINE ID：
                        <input type="text"
                                name="wfs_checkout_opt_line_id"
                                value="<?php echo esc_attr(get_option('wfs_checkout_opt_line_id')); ?>"
                                step="1" min="0">
                        </label>
                    </td>
                </tr>

            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

register_setting('wfs_weight_group', 'wfs_enable_weight_control');
// 重量控制子頁面
function wfs_render_weight_control_page() {
    ?>
    <div class="wrap">
        <h1>重量控制設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_weight_group'); ?>
            <table class="form-table">
                <tr>
                    <th>啟用此功能</th>
                    <td>
                        <input type="checkbox"
                               name="wfs_enable_weight_control"
                               value="yes"
                               <?php checked(get_option('wfs_enable_weight_control'), 'yes'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row">最大重量限制 (kg)</th>
                    <td>
                        <input type="number" name="wfs_weight_limit"
                               value="<?php echo esc_attr(get_option('wfs_weight_limit', 30)); ?>" step="0.1">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

register_setting('wfs_discord_group', 'wfs_enable_discord_notify');
// Discord 通知子頁面
function wfs_render_discord_page() {
    ?>
    <div class="wrap">
        <h1>Discord 通知設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wfs_discord_group'); ?>
            <table class="form-table">
                <tr>
                    <th>啟用此功能</th>
                    <td>
                        <input type="checkbox"
                               name="wfs_enable_discord_notify"
                               value="yes"
                               <?php checked(get_option('wfs_enable_discord_notify'), 'yes'); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook URL</th>
                    <td>
                        <input type="text" name="wfs_discord_webhook"
                               value="<?php echo esc_attr(get_option('wfs_discord_webhook')); ?>" style="width: 100%;">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}