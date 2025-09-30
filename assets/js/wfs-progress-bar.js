jQuery(function($) {
    const params = window.wfs_progress_params;
    if (!params || !params.shipping_methods || params.shipping_methods.length === 0) {
        return;
    }

    const maxWeightOverall = Math.max(...params.shipping_methods.map(method => method.max_weight));
    let xhr;
    
    function renderAllProgressBars(supplierWeights, supplierNames, supplierShippingClasses) {
        const container = $('#wfs-shipping-progress-bar-container');
        if (!container.length) return;

        container.empty();

        const weights = supplierWeights || {};
        const names = supplierNames || {};
        const shippingClasses = supplierShippingClasses || {}; // (*** 全新 ***)

        if ($.isEmptyObject(weights)) {
            container.parent('.wfs-progress-bar-wrapper').hide();
            return;
        }
        container.parent('.wfs-progress-bar-wrapper').show();

        $.each(weights, function(supplierId, currentWeight) {
            const supplierName = names[supplierId] || '未知供應商';
            const weight = (parseFloat(currentWeight) || 0);
            const percentage = Math.min((weight / maxWeightOverall) * 100, 100);

            // --- (*** 關鍵修正 ***) ---
            let titleText = supplierName;
            const shippingClass = shippingClasses[supplierId]; // 從物件中查找對應的溫層
            if (shippingClass) {
                titleText += ` (${shippingClass})`;
            }
            // --- (*** 修正完畢 ***) ---

            let html = '<div class="wfs-supplier-progress-bar">';
            html += '<div class="wfs-progress-bar-label">';
            html += `<span>${titleText}:</span>`;
            html += `<span class="wfs-current-weight">${weight.toFixed(2)} kg</span>`;
            html += '</div>';

            let progressBarHtml = '<div class="wfs-progress-bar-track">';
            progressBarHtml += `<div class="wfs-progress-bar-fill"></div>`;
            progressBarHtml += '</div>';

            let iconsHtml = '<div class="wfs-tier-icons-container">';
            params.shipping_methods.forEach(method => {
                const max_weight = (parseFloat(method.max_weight) || 0);
                const isOverweight = weight > max_weight;
                const overweightClass = isOverweight ? 'is-overweight' : '';
                const tooltipText = isOverweight ? `超過 ${max_weight} kg，暫不可用` : `重量上限: ${max_weight} kg`;
                iconsHtml += `
                    <div class="wfs-tier-icon ${overweightClass}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.582,6.344a1.002,1.002,0,0,0-.862-.488l-3.34-.485-1.494-3.028a1,1,0,0,0-1.772,0L12.62,5.371,9.28,5.856a1,1,0,0,0-.862-.488,1,1,0,0,0,.01,1.013l2.418,2.356-.57,3.328a1,1,0,0,0,.363.95,1,1,0,0,0,1.053.068l2.986-1.57,2.986,1.57a1,1,0,0,0,1.053-.068,1,1,0,0,0,.363-.95l-.57-3.328,2.418-2.356A1,1,0,0,0,21.582,6.344Z"/></svg> 
                        <span>${method.name}</span>
                        <span class="wfs-tier-tooltip">${tooltipText}</span>
                    </div>
                `;
            });
            iconsHtml += '</div>';
            
            const $supplierBar = $(html + progressBarHtml + iconsHtml + '</div>');
            container.append($supplierBar);

            setTimeout(function() {
                const $fill = $supplierBar.find('.wfs-progress-bar-fill');
                $fill.css({
                    'width': percentage + '%',
                    'background-position': percentage + '% 50%'
                });
            }, 10);
        });
    }

    function updateProgressBarViaAjax() {
        if (xhr) { xhr.abort(); }
        xhr = $.ajax({
            type: 'POST',
            url: params.ajax_url,
            data: { action: 'wfs_get_cart_weight_for_progress', nonce: params.nonce },
            success: function(response) {
                if (response.success) {
                    // (*** 全新 ***) 更新 JS 中的溫層資料
                    if (response.data.supplier_shipping_classes) {
                        params.supplier_shipping_classes = response.data.supplier_shipping_classes;
                    }
                    renderAllProgressBars(response.data.weights, response.data.supplier_names, response.data.supplier_shipping_classes);
                }
            }
        });
    }

    if ($('body').hasClass('single-product')) {
        const quantityInput = $('input.qty');
        function updateForProductPage() {
            const initialWeights = $.extend(true, {}, params.initial_supplier_weights || {});
            const quantity = parseInt(quantityInput.val()) || 1;
            
            if (params.product_weight > 0 && params.product_supplier_id) {
                const supplierId = params.product_supplier_id;
                if (!initialWeights.hasOwnProperty(supplierId)) {
                    initialWeights[supplierId] = 0;
                }
                initialWeights[supplierId] += (parseFloat(params.product_weight) || 0) * quantity;
            }
            renderAllProgressBars(initialWeights, params.supplier_names, params.supplier_shipping_classes);
        }
        quantityInput.on('change input', updateForProductPage);
        updateForProductPage();
    } else {
        renderAllProgressBars(params.initial_supplier_weights, params.supplier_names, params.supplier_shipping_classes);
        $(document.body).on('updated_cart_totals added_to_cart removed_from_cart', updateProgressBarViaAjax);
    }
});