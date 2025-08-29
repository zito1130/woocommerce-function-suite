<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class WFS_Line_Notify
 * 透過強制顧客在感謝頁面點擊傳送 LINE 來完成訂購流程。
 */
class WFS_Line_Notify {

    private $line_oa_id = '';

    public function __construct() {
        $this->line_oa_id = get_option('wfs_line_notify_oa_id');

        if (empty($this->line_oa_id)) {
            return;
        }

        add_action('woocommerce_thankyou', [$this, 'output_line_popup_and_scripts'], 9, 1);
        add_action('wp_ajax_wfs_confirm_order_via_line', [$this, 'ajax_confirm_order']);
        add_action('wp_ajax_nopriv_wfs_confirm_order_via_line', [$this, 'ajax_confirm_order']);
        add_action('wp_ajax_wfs_cancel_order_from_popup', [$this, 'ajax_cancel_order']);
        add_action('wp_ajax_nopriv_wfs_cancel_order_from_popup', [$this, 'ajax_cancel_order']);
    }

    public function output_line_popup_and_scripts($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // 【*** 最終強化版：開始 ***】
        // --- 步驟 1：定義一個清理函式，專門移除可能造成問題的字元 ---
        if (!function_exists('wfs_clean_line_string')) {
            function wfs_clean_line_string($string) {
                // 先將 HTML entities 解碼 (例如 &amp; -> &)
                $decoded_string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
                // 移除所有 HTML 和 PHP 標籤
                $no_tags_string = strip_tags($decoded_string);
                // 將 Windows 的換行符 (\r\n) 和 Mac 的舊式換行符 (\r) 全部統一為標準的 \n
                $normalized_newlines = str_replace(["\r\n", "\r"], "\n", $no_tags_string);
                // 移除頭尾的空白和換行
                return trim($normalized_newlines);
            }
        }

        // --- 步驟 2：準備所有原始資料 ---
        $store_name = $order->get_meta('_shipping_cvs_store_name');
        $raw_shipping_address = !empty($store_name) ? $store_name : $order->get_formatted_shipping_address();
        
        // 為了處理地址中的 <br>，我們先將其替換為一個獨特的標記
        $address_with_marker = str_replace(['<br/>', '<br />', '<br>'], '---WFS-LINEBREAK---', $raw_shipping_address);

        // --- 步驟 3：使用我們的清理函式，處理所有要放入訊息的變數 ---
        $shipping_address_clean = str_replace('---WFS-LINEBREAK---', "\n", wfs_clean_line_string($address_with_marker));
        
        $shipping_phone = wfs_clean_line_string($order->get_shipping_phone() ?: $order->get_billing_phone());
        $customer_note = $order->get_customer_note() ? wfs_clean_line_string($order->get_customer_note()) : '無';
        
        $line_name_raw = wfs_clean_line_string($order->get_meta('billing_line_id'));
        $line_id_text = !empty($line_name_raw) ? ' (LINE: ' . $line_name_raw . ')' : '';

        $shipping_total_text = wfs_clean_line_string($order->get_shipping_total('raw') . ' ' . get_woocommerce_currency());
        $total_text = wfs_clean_line_string($order->get_formatted_order_total());
        
        $shipping_full_name = wfs_clean_line_string($order->get_formatted_shipping_full_name());
        $billing_full_name = wfs_clean_line_string($order->get_formatted_billing_full_name());
        $billing_phone = wfs_clean_line_string($order->get_billing_phone());

        // --- 步驟 4：建立最終訊息字串 ---
        $newline_placeholder = '||BR||';

        // 步驟 2：建立訊息字串，但所有換行都使用替身符號
        $line_message  = "【訂單資訊】" . $newline_placeholder;
        $line_message .= "訂單編號: #" . $order->get_order_number() . $newline_placeholder;
        $line_message .= "------------------" . $newline_placeholder;
        $line_message .= "[運送資訊]" . $newline_placeholder;
        $line_message .= "收件人: " . $shipping_full_name . $newline_placeholder;
        $line_message .= "收件電話: " . $shipping_phone . $newline_placeholder;
        
        // 處理可能包含多行地址的情況
        $address_with_placeholder = str_replace("\n", $newline_placeholder, $shipping_address_clean);
        $line_message .= "收件資訊: " . $address_with_placeholder . $newline_placeholder;
        
        $line_message .= "------------------" . $newline_placeholder;
        $line_message .= "[顧客資訊]" . $newline_placeholder;
        $line_message .= "訂購人: " . $billing_full_name . $line_id_text . $newline_placeholder;
        $line_message .= "聯絡電話: " . $billing_phone . $newline_placeholder;
        $line_message .= "------------------" . $newline_placeholder;
        $line_message .= "[金額]" . $newline_placeholder;
        $line_message .= "運費: " . strip_tags(wc_price($order->get_shipping_total())) . $newline_placeholder;
        $line_message .= "總額: " . $total_text . $newline_placeholder;
        $line_message .= "------------------" . $newline_placeholder;
        $line_message .= "[顧客備註]" . $newline_placeholder;
        $line_message .= $customer_note;

        // 步驟 3：先對整個包含「替身」的字串進行 URL 編碼
        $encoded_message = rawurlencode($line_message);

        // 步驟 4：將已被編碼的「替身」，手動替換成換行符的編碼 "%0A"
        $final_message = str_replace(rawurlencode($newline_placeholder), '%0A', $encoded_message);
        
        // 步驟 5：建立最終的 URL
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
            <a href="<?php echo esc_url($line_url); ?>" class="button wfs-resend-line-button" id="wfsResendButton" target="_blank">點此手動傳送訂單資訊到 LINE</a>
        </div>

        <div class="wfs-modal-overlay" id="wfsOrderModal">
             <div class="wfs-modal-content">
                <h2>訂單資訊確認</h2>
                <div class="order-details">
                    <strong>訂單編號: #<?php echo esc_html($order->get_order_number()); ?></strong><br>
                    收件人: <?php echo esc_html($order->get_formatted_shipping_full_name()); ?><br>
                    收件資訊: <?php echo nl2br(esc_html($shipping_address_clean)); ?><br>
                    總額: <?php echo $order->get_formatted_order_total(); ?>
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
                var messageToCopy = <?php echo json_encode($line_message); ?>;
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
                    textArea.style.position = "fixed"; 
                    textArea.style.top = "-9999px"; 
                    textArea.style.left = "-9999px";
                    document.body.appendChild(textArea);
                    textArea.focus(); 
                    textArea.select();
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
                    if (activeElement !== sendButton && activeElement !== resendButton) {
                        activeElement = sendButton;
                    }
                    
                    activeElement.disabled = true;
                    activeElement.classList.add('wfs-button-disabled');
                    activeElement.textContent = '處理中...';

                    copyToClipboard(function(success) {
                        if (success) {
                            activeElement.textContent = '✓ 已複製資訊！';
                        } else {
                            activeElement.textContent = '正在前往 LINE...';
                        }
                        
                        setTimeout(confirmOrderAjax, 1000); 
                    });
                }

                function confirmOrderAjax() {
                    // ★★★ 關鍵修正點：使用您指定的方式打開視窗 ★★★
                    window.open(lineURL, "", "width=600,height=400,resizable=yes,scrollbars=yes");

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
                        sendButton.disabled = false;
                        sendButton.classList.remove('wfs-button-disabled');
                        sendButton.textContent = '傳送訂單資訊';
                        resendButton.classList.remove('wfs-button-disabled');
                    });
                }

                // ★★★ 關鍵修正點：兩個按鈕都統一呼叫 handleSendAction ★★★
                sendButton.addEventListener('click', handleSendAction);
                resendButton.addEventListener('click', function(e) {
                    e.preventDefault(); // 阻止 <a> 標籤的預設行為
                    handleSendAction();
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

    public function ajax_confirm_order() {
        if (!check_ajax_referer('wfs_line_notify_nonce', '_ajax_nonce', false)) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if ($order_id > 0 && ($order = wc_get_order($order_id))) {
            if (!$order->get_meta('_wfs_line_action_taken')) {
                $order->add_order_note('顧客已在感謝頁面點擊「傳送訂單資訊」，完成訂購流程。');
                $order->update_meta_data('_wfs_line_action_taken', true);
                $order->save();
            }
        }
        wp_send_json_success();
    }

    public function ajax_cancel_order() {
        if (!check_ajax_referer('wfs_line_notify_nonce', '_ajax_nonce', false)) {
            wp_send_json_error('Security check failed.', 403);
            return;
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if ($order_id > 0 && ($order = wc_get_order($order_id))) {
            $order->update_status('cancelled', '顧客在感謝頁面點擊「取消訂單」。');
        }
        wp_send_json_success();
    }
}