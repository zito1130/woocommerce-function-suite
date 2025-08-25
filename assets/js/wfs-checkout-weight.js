jQuery(function($) {
    if (!$('body').hasClass('woocommerce-checkout') || $('tr.cart-empty').length) {
        return;
    }

    const $checkoutForm = $('form.checkout');
    const $placeOrderBtn = $('#place_order');

    let xhr;
    let debounceTimer;

    // *** 新增：控制下單按鈕狀態的函式 ***
    function controlPlaceOrderButton() {
        const $selectedMethod = $('input.shipping_method:checked');
        const isMethodSelected = $selectedMethod.length > 0;
        const isSelectedMethodOverweight = isMethodSelected ? $selectedMethod.closest('li').hasClass('wfs-shipping-method--overweight') : true;

        // 只有在「有選擇」且「選擇的選項沒有超重」時，才解鎖按鈕
        if (isMethodSelected && !isSelectedMethodOverweight) {
            $placeOrderBtn.prop('disabled', false).fadeTo(200, 1);
        } else {
            $placeOrderBtn.prop('disabled', true).fadeTo(200, 0.5);
        }
    }

    function updateWeightDisplay(weight) {
        const weightText = weight.toFixed(2) + ' kg';
        const $weightRow = $('tr.wfs-cart-weight-info');
        
        if ($weightRow.length) {
            $weightRow.find('.wfs-cart-weight-value').text(weightText).removeClass('loading');
        } else {
            const rowHtml = '<tr class="wfs-cart-weight-info"><th>訂單總重量</th><td><span class="wfs-cart-weight-value">' + weightText + '</span></td></tr>';
            $('tr.woocommerce-shipping-totals').before(rowHtml);
        }
    }

    function updateShippingMethods() {
        if (xhr) {
            xhr.abort();
        }

        if (!$('tr.wfs-cart-weight-info').length) {
             $('tr.woocommerce-shipping-totals').before('<tr class="wfs-cart-weight-info"><th>訂單總重量</th><td><span class="wfs-cart-weight-value loading">計算中...</span></td></tr>');
        } else {
            $('.wfs-cart-weight-value').text('計算中...').addClass('loading');
        }

        xhr = $.ajax({
            type: 'POST',
            url: wfs_weight_params.ajax_url,
            data: { action: 'wfs_get_cart_weight', nonce: wfs_weight_params.nonce },
            success: function(response) {
                if (!response.success) return;

                const cartWeight = parseFloat(response.data.weight);
                updateWeightDisplay(cartWeight);

                $('ul#shipping_method li').each(function() {
                    const $methodLi = $(this);
                    const methodId = $methodLi.find('input.shipping_method').val();
                    
                    $methodLi.find('.wfs-weight-limit-notice').remove();

                    if (response.data.limits && response.data.limits[methodId]) {
                        const maxWeight = parseFloat(response.data.limits[methodId]);
                        
                        if (cartWeight > maxWeight) {
                            $methodLi.addClass('wfs-shipping-method--overweight');
                            const notice = '<div class="wfs-weight-limit-notice" style="color: red; font-size: 0.9em; margin-left: 25px;">超過 ' + maxWeight + ' kg 重量限制</div>';
                            $methodLi.append(notice);
                        } else {
                            $methodLi.removeClass('wfs-shipping-method--overweight');
                        }
                    }
                });

                // *** AJAX 完成後，立刻重新評估按鈕狀態 ***
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

    // *** 初始化：頁面一載入就立刻鎖定按鈕 ***
    $placeOrderBtn.prop('disabled', true).fadeTo(0, 0.5);

    // 監聽 WooCommerce 的更新事件
    $(document.body).on('updated_checkout init_checkout', debouncedUpdate);

    // *** 新增：監聽運送方式的變更，即時更新按鈕狀態 ***
    $checkoutForm.on('change', 'input[name^="shipping_method"]', controlPlaceOrderButton);
});