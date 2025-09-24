<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Line_Notify
 * (*** 已升級：支援 Cart Manager 多筆訂單 ***)
 */
class WFS_Line_Notify {

    private $line_oa_id = '';

    public function __construct() {
        $this->line_oa_id = get_option('wfs_line_notify_oa_id');

        if (empty($this->line_oa_id)) {
            return;
        }

        // 鉤子保持不變
        add_action('woocommerce_thankyou', [$this, 'output_line_popup_and_scripts'], 9, 1);
        add_action('wp_ajax_wfs_confirm_order_via_line', [$this, 'ajax_confirm_order']);
        add_action('wp_ajax_nopriv_wfs_confirm_order_via_line', [$this, 'ajax_confirm_order']);
        add_action('wp_ajax_wfs_cancel_order_from_popup', [$this, 'ajax_cancel_order']);
        add_action('wp_ajax_nopriv_wfs_cancel_order_from_popup', [$this, 'ajax_cancel_order']);
    }

    /**
     * (*** 已修改：彈出視窗移除總額 ***)
     * 輸出彈窗和腳本
     */
    public function output_line_popup_and_scripts($order_id) {
        $parent_order = wc_get_order($order_id);
        if (!$parent_order) return;

        // --- 步驟 1：獲取所有相關訂單 (不變) ---
        $all_orders = [];
        if ( $parent_order->get_meta('_cm_order_split_parent') ) {
            $child_orders = wc_get_orders([
                'parent'  => $order_id, 'limit'   => -1, 'orderby' => 'ID', 'order'   => 'ASC'
            ]);
            $all_orders = array_merge([$parent_order], $child_orders);
        } else {
            $all_orders = [$parent_order];
        }

        // --- 步驟 2：準備「父訂單」的原始資料 (不變) ---
        $store_name = $parent_order->get_meta('_shipping_cvs_store_name');
        $raw_shipping_address = !empty($store_name) ? $store_name : $parent_order->get_formatted_shipping_address();
        $address_with_marker = str_replace(['<br/>', '<br />', '<br>'], '---WFS-LINEBREAK---', $raw_shipping_address);
        $shipping_address_clean = str_replace('---WFS-LINEBREAK---', "\n", $address_with_marker);
        $shipping_phone = $parent_order->get_shipping_phone() ?: $parent_order->get_billing_phone();
        $customer_note = $parent_order->get_customer_note() ? $parent_order->get_customer_note() : '無';
        $line_name_raw = $parent_order->get_meta('billing_line_id');
        $line_id_text = !empty($line_name_raw) ? ' (LINE: ' . $line_name_raw . ')' : '';
        $raw_symbol = get_woocommerce_currency_symbol();
        $currency_symbol = html_entity_decode($raw_symbol);
        $shipping_full_name = $parent_order->get_formatted_shipping_full_name();
        $billing_full_name = $parent_order->get_formatted_billing_full_name();
        $billing_phone = $parent_order->get_billing_phone();

        // --- 步驟 3：(不變) 計算金額 ---
        $total_shipping_raw = $parent_order->get_shipping_total();
        
        // (純文字) - 用於 LINE
        $shipping_total_text_plain = $currency_symbol . wc_format_decimal($total_shipping_raw);
        
        // (HTML) - 用於彈出視窗
        $shipping_total_html = wc_price($total_shipping_raw, ['currency' => $parent_order->get_currency()]);

        // (*** 我們不再需要總金額的 HTML，所以 $total_text_html 已被移除 ***)
        
        // --- 步驟 4：建立最終訊息字串 (不變) ---
        $newline_placeholder = '|';
        $line_message  = "【訂單資訊】" . $newline_placeholder;
        $line_message .= "訂購人: " . $billing_full_name . $line_id_text . $newline_placeholder;
        $line_message .= "聯絡電話: " . $billing_phone . $newline_placeholder;
        $line_message .= "收件人: " . $shipping_full_name . $newline_placeholder;
        $line_message .= "收件電話: " . $shipping_phone . $newline_placeholder;
        $address_with_placeholder = str_replace("\n", $newline_placeholder, $shipping_address_clean);
        $line_message .= "收件資訊: " . $address_with_placeholder . $newline_placeholder;
        $line_message .= "------------------" . $newline_placeholder;
        $line_message .= "【訂單列表】" . $newline_placeholder;
        foreach ($all_orders as $order) {
            $line_message .= "編號: #" . $order->get_order_number() . $newline_placeholder;
            $individual_total_plain = $currency_symbol . wc_format_decimal($order->get_total());
            $line_message .= "總額: " . $individual_total_plain . $newline_placeholder;
            $line_message .= "---" . $newline_placeholder;
        }
        $line_message .= "運費: " . $shipping_total_text_plain . "/筆" . $newline_placeholder;
        $line_message .= "------------------" . $newline_placeholder;
        $line_message .= "[顧客備註]" . $newline_placeholder;
        $line_message .= $customer_note;

        // 步驟 3-5：編碼 (保持不變)
        $encoded_message = rawurlencode($line_message);
        $final_message = str_replace(rawurlencode($newline_placeholder), '%0A', $encoded_message);
        $message_for_clipboard = str_replace($newline_placeholder, "\n", $line_message);
        $line_url = "https://line.me/R/oaMessage/" . esc_attr($this->line_oa_id) . "/" . $final_message;
        
        ?>
        <style>
            .wfs-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); display: none; justify-content: center; align-items: center; z-index: 10001; }
            .wfs-modal-content { background: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 480px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
            .wfs-modal-content h2 { margin-top: 0; }
            .wfs-modal-content .order-details { text-align: left; background: #f9f9f9; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto; margin-bottom: 15px; }
            .wfs-modal-content .important-note { color: #d9534f; font-weight: bold; }
            .wfs-modal-buttons { margin-top: 25px; display: flex; justify-content: space-between; gap: 15px; }
            .wfs-modal-button { flex: 1; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; transition: all 0.3s; }
            #wfsSendOrderInfo { background-color: #00B900; color: white; }
            #wfsCancelOrder { background-color: #f0f0f0; color: #333; }
            .wfs-resend-line-wrapper { margin: 0 0 2em 0; padding: 1.5em; border: 2px dashed #00B900; text-align: center; background: #f7fff7; }
            .wfs-resend-line-wrapper p { margin-top: 0; margin-bottom: 1em; font-size: 1.1em; }
            .wfs-resend-line-button { font-size: 1.1em !important; }
            .wfs-button-disabled { opacity: 0.7; cursor: not-allowed !important; }
            .wfs-hidden { display: none !important; }
        </style>
        
        <div class="wfs-resend-line-wrapper" id="wfsResendWrapper">
            <p>沒有看到傳送視窗嗎？或是不小心關掉了？</p>
            <a href="<?php echo $line_url; ?>" class="button wfs-resend-line-button" id="wfsResendButton" target="_blank">點此手動傳送訂單資訊到 LINE</a>
        </div>

        <div class="wfs-modal-overlay" id="wfsOrderModal">
             <div class="wfs-modal-content">
                <h2>訂單資訊確認</h2>
                <div class="order-details">
                    <strong>訂單編號:</strong><br>
                    <?php 
                    foreach ($all_orders as $order) {
                        echo esc_html("#" . $order->get_order_number()) . ' (' . $order->get_formatted_order_total() . ')<br>';
                    }
                    ?>
                    <hr style="margin: 5px 0;">
                    收件人: <?php echo esc_html($shipping_full_name); ?><br>
                    收件資訊: <?php echo nl2br(esc_html($shipping_address_clean)); ?><br>
                    <hr style="margin: 5px 0;">
                    
                    <strong>運費: <?php echo $shipping_total_html; ?>/筆</strong>
                    </div>
                <p>請確認您的訂購資訊是否正確？</p>
                <p class="important-note">注意：需點擊「傳送訂單資訊」才算完成訂購，否則訂單將會被取消。</p>
                <div class="wfs-modal-buttons">
                    <button class="wfs-modal-button" id="wfsCancelOrder">取消訂單</button>
                    <button class="wfs-modal-button" id="wfsSendOrderInfo">傳送訂單資訊</button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            // ... (此處的 JS 程式碼與上一版完全相同，保持不變) ...
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('wfsOrderModal');
                var sendButton = document.getElementById('wfsSendOrderInfo');
                var resendButton = document.getElementById('wfsResendButton'); 
                var cancelButton = document.getElementById('wfsCancelOrder');
                var resendWrapper = document.getElementById('wfsResendWrapper');
                var orderId = "<?php echo $order_id; ?>"; 
                var statusKey = "wfs_order_status_" + orderId;
                var timestampKey = "wfs_order_timestamp_" + orderId;
                var nonce = "<?php echo wp_create_nonce('wfs_line_notify_nonce'); ?>";
                var lineURL = <?php echo json_encode($line_url); ?>;
                var messageToCopy = <?php echo json_encode($message_for_clipboard); ?>;
                var thirtyMinutes = 30 * 60 * 1000;
                var isProcessing = false;
                var savedStatus = localStorage.getItem(statusKey);
                var initialTimestamp = localStorage.getItem(timestampKey);
                if (!initialTimestamp) {
                    initialTimestamp = Date.now();
                    localStorage.setItem(timestampKey, initialTimestamp);
                }
                var timeElapsed = Date.now() - parseInt(initialTimestamp, 10);
                if (savedStatus === 'cancelled' || timeElapsed > thirtyMinutes) {
                    modal.classList.add('wfs-hidden');
                    resendWrapper.classList.add('wfs-hidden');
                } else if (savedStatus === 'sent') {
                    modal.classList.add('wfs-hidden');
                } else {
                    modal.classList.remove('wfs-hidden');
                    modal.style.display = 'flex';
                }
                function copyToClipboard(callback) {
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(messageToCopy).then(() => callback(true), () => fallbackCopy(callback));
                    } else {
                        fallbackCopy(callback);
                    }
                }
                function fallbackCopy(callback) {
                    var textArea = document.createElement("textarea");
                    textArea.value = messageToCopy;
                    textArea.style.position = "fixed"; textArea.style.top = "-9999px"; textArea.style.left = "-9999px";
                    document.body.appendChild(textArea);
                    textArea.focus(); textArea.select();
                    try {
                        var successful = document.execCommand('copy');
                        callback(successful);
                    } catch (err) {
                        callback(false);
                    }
                    document.body.removeChild(textArea);
                }
                function handleSendAction() {
                    if (isProcessing) return;
                    isProcessing = true;
                    var activeElement = document.activeElement;
                    if (activeElement !== sendButton && activeElement !== resendButton) { activeElement = sendButton; }
                    activeElement.disabled = true; activeElement.classList.add('wfs-button-disabled');
                    activeElement.textContent = '處理中...';
                    copyToClipboard(function(success) {
                        if (success) { activeElement.textContent = '✓ 已複製資訊！'; } else { activeElement.textContent = '正在前往 LINE...'; }
                        setTimeout(confirmOrderAjax, 1000); 
                    });
                }
                function confirmOrderAjax() {
                    window.open(lineURL, "", "width=600,height=400");
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            'action': 'wfs_confirm_order_via_line',
                            'order_id': orderId,
                            '_ajax_nonce': nonce
                        })
                    }).then(function() {
                        localStorage.setItem(statusKey, 'sent');
                        modal.classList.add('wfs-hidden');
                        isProcessing = false; 
                        sendButton.disabled = false; sendButton.classList.remove('wfs-button-disabled'); sendButton.textContent = '傳送訂單資訊';
                        resendButton.classList.remove('wfs-button-disabled');
                    });
                }
                sendButton.addEventListener('click', handleSendAction);
                resendButton.addEventListener('click', function(e) {
                    e.preventDefault(); handleSendAction();
                });
                cancelButton.addEventListener('click', function() {
                    if (isProcessing || this.disabled) return;
                    if (confirm("您確定要取消這筆訂單嗎？此操作無法復原。")) {
                        this.disabled = true; this.classList.add('wfs-button-disabled');
                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                'action': 'wfs_cancel_order_from_popup',
                                'order_id': orderId,
                                '_ajax_nonce': nonce
                            })
                        }).then(function() {
                            localStorage.setItem(statusKey, 'cancelled');
                            modal.classList.add('wfs-hidden');
                            resendWrapper.classList.add('wfs-hidden');
                        });
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * (*** 已修改：更新所有子訂單 ***)
     */
    public function ajax_confirm_order() {
        if (!check_ajax_referer('wfs_line_notify_nonce', '_ajax_nonce', false)) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        // (*** 全新 ***) 獲取所有相關訂單 (父+子)
        $all_orders = $this->get_all_related_orders($order_id);

        foreach ($all_orders as $order) {
            if (!$order->get_meta('_wfs_line_action_taken')) {
                $order->add_order_note('顧客已在感謝頁面點擊「傳送訂單資訊」，完成訂購流程。');
                $order->update_meta_data('_wfs_line_action_taken', true);
                $order->save();
            }
        }
        wp_send_json_success();
    }

    /**
     * (*** 已修改：取消所有子訂單 ***)
     */
    public function ajax_cancel_order() {
        if (!check_ajax_referer('wfs_line_notify_nonce', '_ajax_nonce', false)) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        // (*** 全新 ***) 獲取所有相關訂單 (父+子)
        $all_orders = $this->get_all_related_orders($order_id);
        
        foreach ($all_orders as $order) {
            // 僅在訂單尚未取消時才更新，避免重複觸發
            if ($order->get_status() !== 'cancelled') {
                $order->update_status('cancelled', '顧客在感謝頁面點擊「取消訂單」。');
            }
        }
        wp_send_json_success();
    }
    
    /**
     * (*** 全新 ***) 輔助函數
     * 根據傳入的父訂單 ID，返回包含父訂單和所有子訂單的陣列
     */
    private function get_all_related_orders($parent_order_id) {
        $parent_order = wc_get_order($parent_order_id);
        if (!$parent_order) {
            return [];
        }

        $all_orders = [$parent_order];
        
        // 檢查是否為 cart-manager 的父訂單
        if ( $parent_order->get_meta('_cm_order_split_parent') ) {
            $child_orders = wc_get_orders([
                'parent'  => $parent_order_id,
                'limit'   => -1,
            ]);
            $all_orders = array_merge($all_orders, $child_orders);
        }

        return $all_orders;
    }
}