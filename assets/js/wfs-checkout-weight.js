jQuery(function($) {
    if (!$('body').hasClass('woocommerce-checkout') || $('tr.cart-empty').length) {
        return;
    }

    const $checkoutForm = $('form.checkout');
    const $placeOrderBtn = $('#place_order');

    let xhr;
    let debounceTimer;

    // --- (*** 已修正：變數名稱 $selected_method -> $selectedMethod ***) ---
    function controlPlaceOrderButton() {
        const $selectedMethod = $('input.shipping_method:checked');
        const isMethodSelected = $selectedMethod.length > 0;
        const isSelectedMethodOverweight = isMethodSelected ? $selectedMethod.closest('li').hasClass('wfs-shipping-method--overweight') : true;

        if (isMethodSelected && !isSelectedMethodOverweight) {
            $placeOrderBtn.prop('disabled', false).fadeTo(200, 1);
        } else {
            $placeOrderBtn.prop('disabled', true).fadeTo(200, 0.5);
        }
    }

    // --- (*** 已修正：改用更可靠的資料判斷 ***) ---
    function updateWeightDisplay(data) {
        $('tr.wfs-cart-weight-info').remove();
        const $insertBefore = $('tr.woocommerce-shipping-totals');
        if (!$insertBefore.length) return;

        // *** 關鍵修正：直接檢查 data.weights 物件是否存在 ***
        if (data.weights && Object.keys(data.weights).length > 0) {
            // --- 新格式：依供應商顯示 ---
            $.each(data.weights, function(supplierId, weight) {
                const supplierName = data.names[supplierId] || '未知供應商';
                const weightText = parseFloat(weight).toFixed(2) + ' kg';
                const rowHtml = '<tr class="wfs-cart-weight-info"><th>' + supplierName + ' 重量</th><td>' + weightText + '</td></tr>';
                $insertBefore.before(rowHtml);
            });
        } else if (data.hasOwnProperty('weight')) {
            // --- 舊格式：顯示總重量 (Fallback) ---
            const weightText = parseFloat(data.weight).toFixed(2) + ' kg';
            const rowHtml = '<tr class="wfs-cart-weight-info"><th>訂單總重量</th><td>' + weightText + '</td></tr>';
            $insertBefore.before(rowHtml);
        }
    }

    function updateShippingMethods() {
        if (xhr) { xhr.abort(); }

        $('tr.wfs-cart-weight-info').remove();
        $('tr.woocommerce-shipping-totals').before('<tr class="wfs-cart-weight-info is-loading"><th>重量計算中...</th><td></td></tr>');

        xhr = $.ajax({
            type: 'POST',
            url: wfs_weight_params.ajax_url,
            data: { action: 'wfs_get_cart_weight', nonce: wfs_weight_params.nonce },
            success: function(response) {
                if (!response.success) {
                    $('tr.wfs-cart-weight-info.is-loading').remove();
                    return;
                }
                
                const data = response.data;
                updateWeightDisplay(data);

                $('ul#shipping_method li').each(function() {
                    const $methodLi = $(this);
                    const methodId = $methodLi.find('input.shipping_method').val();
                    
                    $methodLi.find('.wfs-weight-limit-notice').remove();
                    $methodLi.removeClass('wfs-shipping-method--overweight');

                    if (data.limits && data.limits[methodId]) {
                        const maxWeight = parseFloat(data.limits[methodId]);
                        let isOverweight = false;
                        let overweightMessage = '';

                        // *** 關鍵修正：直接檢查 data.weights 物件是否存在 ***
                        if (data.weights) {
                            // --- 新格式：檢查 *任何* 供應商是否超重 ---
                            $.each(data.weights, function(supplierId, weight) {
                                if (parseFloat(weight) > maxWeight) {
                                    isOverweight = true;
                                    const supplierName = data.names[supplierId] || '未知供應商';
                                    overweightMessage = supplierName + ' 已超過 ' + maxWeight + ' kg 重量限制';
                                    return false; // 中斷迴圈
                                }
                            });
                        } else if (data.hasOwnProperty('weight')) {
                            // --- 舊格式：檢查總重 (Fallback) ---
                            if (parseFloat(data.weight) > maxWeight) {
                                isOverweight = true;
                                overweightMessage = '超過 ' + maxWeight + ' kg 重量限制';
                            }
                        }
                        
                        if (isOverweight) {
                            $methodLi.addClass('wfs-shipping-method--overweight');
                            const notice = '<div class="wfs-weight-limit-notice" style="color: red; font-size: 0.9em; margin-left: 25px;">' + overweightMessage + '</div>';
                            $methodLi.append(notice);
                        }
                    }
                });

                controlPlaceOrderButton();
            },
            complete: function() {
                xhr = null;
            }
        });
    }

    function debouncedUpdate() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updateShippingMethods, 300);
    }

    $placeOrderBtn.prop('disabled', true).fadeTo(0, 0.5);
    $(document.body).on('updated_checkout init_checkout', debouncedUpdate);
    $checkoutForm.on('change', 'input[name^="shipping_method"]', controlPlaceOrderButton);
});