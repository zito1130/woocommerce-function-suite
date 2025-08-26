jQuery(function($) {
    const params = window.wfs_progress_params;
    if (!params || !params.shipping_methods || params.shipping_methods.length === 0) {
        return;
    }

    const maxWeightOverall = Math.max(...params.shipping_methods.map(method => method.max_weight));
    let xhr;

    // --- 主要的渲染函式 (*** 已更新，改為控制背景位置 ***) ---
    function renderProgressBar(currentWeight) {
        const container = $('#wfs-shipping-progress-bar-container');
        if (!container.length) return;

        const weight = (typeof currentWeight === 'number' && !isNaN(currentWeight)) ? currentWeight : 0;
        container.empty();
        
        const percentage = Math.min((weight / maxWeightOverall) * 100, 100);
        
        let titleText = params.i18n.current_weight;
        const shippingClass = params.cart_shipping_class;
        if (shippingClass) {
            titleText += ` (${shippingClass})`;
        }
        
        let html = '<div class="wfs-progress-bar-label">';
        html += `<span>${titleText}:</span>`;
        html += `<span class="wfs-current-weight">${weight.toFixed(2)} kg</span>`;
        html += '</div>';
        
        let progressBarHtml = '<div class="wfs-progress-bar-track">';
        // 我們不再需要 colorClass，因為顏色由背景位置決定
        progressBarHtml += `<div class="wfs-progress-bar-fill"></div>`; 
        progressBarHtml += '</div>';
        
        let iconsHtml = '<div class="wfs-tier-icons-container">';
        params.shipping_methods.forEach(method => {
            const isOverweight = weight > method.max_weight;
            const overweightClass = isOverweight ? 'is-overweight' : '';
            const tooltipText = isOverweight ? `超過 ${method.max_weight} kg，暫不可用` : `重量上限: ${method.max_weight} kg`;

            iconsHtml += `
                <div class="wfs-tier-icon ${overweightClass}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.582,6.344a1.002,1.002,0,0,0-.862-.488l-3.34-.485-1.494-3.028a1,1,0,0,0-1.772,0L12.62,5.371,9.28,5.856a1,1,0,0,0-.862-.488,1,1,0,0,0,.01,1.013l2.418,2.356-.57,3.328a1,1,0,0,0,.363.95,1,1,0,0,0,1.053.068l2.986-1.57,2.986,1.57a1,1,0,0,0,1.053-.068,1,1,0,0,0,.363-.95l-.57-3.328,2.418-2.356A1,1,0,0,0,21.582,6.344Z"/></svg> 
                    <span>${method.name}</span>
                    <span class="wfs-tier-tooltip">${tooltipText}</span>
                </div>
            `;
        });
        iconsHtml += '</div>';

        container.html(html + progressBarHtml + iconsHtml);

        setTimeout(function() {
            const $fill = container.find('.wfs-progress-bar-fill');
            // *** 關鍵修改點：同時設定寬度和背景位置 ***
            $fill.css({
                'width': percentage + '%',
                // 將進度百分比，同步映射到背景位置的百分比
                // 0%   -> background-position: 0%
                // 100% -> background-position: 100%
                'background-position': percentage + '% 50%'
            });
        }, 10);
    }

    function updateProgressBarViaAjax() {
        if (xhr) { xhr.abort(); }
        xhr = $.ajax({
            type: 'POST',
            url: params.ajax_url,
            data: { action: 'wfs_get_cart_weight_for_progress', nonce: params.nonce },
            success: function(response) {
                if (response.success) {
                    // 更新 JS 中的運送類別資訊
                    if(response.data.shipping_class){
                        params.cart_shipping_class = response.data.shipping_class;
                    }
                    renderProgressBar(response.data.weight);
                }
            }
        });
    }

    if ($('body').hasClass('single-product')) {
        const quantityInput = $('input.qty');
        function updateForProductPage() {
            $.ajax({
                type: 'POST',
                url: params.ajax_url,
                data: { action: 'wfs_get_cart_weight_for_progress', nonce: params.nonce },
                success: function(response) {
                    if(response.success) {
                        const cartWeight = parseFloat(response.data.weight);
                        const quantity = parseInt(quantityInput.val()) || 1;
                        const potentialWeight = cartWeight + (params.product_weight * quantity);
                        if(response.data.shipping_class){
                           params.cart_shipping_class = response.data.shipping_class;
                        }
                        renderProgressBar(potentialWeight);
                    }
                }
            });
        }
        quantityInput.on('change input', updateForProductPage);
        updateForProductPage();
    } else {
        updateProgressBarViaAjax();
        $(document.body).on('updated_cart_totals added_to_cart removed_from_cart', updateProgressBarViaAjax);
    }
});