jQuery(function($) {
    if (!$('body').hasClass('woocommerce-checkout') || $('tr.cart-empty').length) {
        return;
    }

    const $checkoutForm = $('form.checkout');
    const $placeOrderBtn = $('#place_order');

    let xhr;
    let debounceTimer;

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

    function updateWeightDisplay(data) {
        $('tr.wfs-cart-weight-info').remove();
        const $insertBefore = $('tr.woocommerce-shipping-totals');
        if (!$insertBefore.length) return;

        // *** 使用 || 0 來提供預設值，防止 NaN ***
        if (data.weights && Object.keys(data.weights).length > 0) {
            $.each(data.weights, function(supplierId, weight) {
                const supplierName = data.names[supplierId] || '未知供應商';
                const weightText = (parseFloat(weight) || 0).toFixed(2) + ' kg'; // <-- 修正點
                const rowHtml = '<tr class="wfs-cart-weight-info"><th>' + supplierName + ' 重量</th><td>' + weightText + '</td></tr>';
                $insertBefore.before(rowHtml);
            });
        } else if (data.hasOwnProperty('weight')) {
            const weightText = (parseFloat(data.weight) || 0).toFixed(2) + ' kg'; // <-- 修正點
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
                        // *** 使用 || 0 來提供預設值，防止 NaN ***
                        const maxWeight = parseFloat(data.limits[methodId]) || 0; // <-- 修正點
                        let isOverweight = false;
                        let overweightMessage = '';

                        if (data.weights) {
                            $.each(data.weights, function(supplierId, weight) {
                                if ((parseFloat(weight) || 0) > maxWeight) { // <-- 修正點
                                    isOverweight = true;
                                    const supplierName = data.names[supplierId] || '未知供應商';
                                    overweightMessage = supplierName + ' 已超過 ' + maxWeight + ' kg 重量限制';
                                    return false;
                                }
                            });
                        } else if (data.hasOwnProperty('weight')) {
                            if ((parseFloat(data.weight) || 0) > maxWeight) { // <-- 修正點
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