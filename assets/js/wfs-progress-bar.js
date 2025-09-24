jQuery(function($) {
    const params = window.wfs_progress_params;
    if (!params || !params.shipping_methods || params.shipping_methods.length === 0) {
        return;
    }

    const maxWeightOverall = Math.max(...params.shipping_methods.map(method => method.max_weight));
    let xhr;
    
    // --- (*** 全新：渲染 *所有* 進度條的主要函式 ***) ---
    function renderAllProgressBars(supplierWeights, supplierNames) {
        const container = $('#wfs-shipping-progress-bar-container');
        if (!container.length) return;

        container.empty(); // 清空容器

        // 如果沒有任何重量資料，就隱藏容器
        if ($.isEmptyObject(supplierWeights)) {
            container.parent('.wfs-progress-bar-wrapper').hide();
            return;
        }
        container.parent('.wfs-progress-bar-wrapper').show();

        // 遍歷所有供應商的重量
        $.each(supplierWeights, function(supplierId, currentWeight) {
            const supplierName = supplierNames[supplierId] || '未知供應商';
            const weight = (typeof currentWeight === 'number' && !isNaN(currentWeight)) ? currentWeight : 0;
            const percentage = Math.min((weight / maxWeightOverall) * 100, 100);

            let titleText = supplierName; // 標題直接使用供應商名稱
            const shippingClass = params.cart_shipping_class;
            if (shippingClass) {
                titleText += ` (${shippingClass})`;
            }

            // --- 為每個供應商建立獨立的 HTML 結構 ---
            let html = '<div class="wfs-supplier-progress-bar">'; // 新增一個 class 方便設定樣式
            html += '<div class="wfs-progress-bar-label">';
            html += `<span>${titleText}:</span>`;
            html += `<span class="wfs-current-weight">${weight.toFixed(2)} kg</span>`;
            html += '</div>';

            let progressBarHtml = '<div class="wfs-progress-bar-track">';
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
            
            // 將這個供應商的進度條加入容器
            const $supplierBar = $(html + progressBarHtml + iconsHtml + '</div>');
            container.append($supplierBar);

            // 觸發動畫
            setTimeout(function() {
                const $fill = $supplierBar.find('.wfs-progress-bar-fill');
                $fill.css({
                    'width': percentage + '%',
                    'background-position': percentage + '% 50%'
                });
            }, 10);
        });
    }

    // --- (*** 已修改：呼叫新的渲染函式 ***) ---
    function updateProgressBarViaAjax() {
        if (xhr) { xhr.abort(); }
        xhr = $.ajax({
            type: 'POST',
            url: params.ajax_url,
            data: { action: 'wfs_get_cart_weight_for_progress', nonce: params.nonce },
            success: function(response) {
                if (response.success) {
                    if (response.data.shipping_class) {
                        params.cart_shipping_class = response.data.shipping_class;
                    }
                    // 將 weights 和 names 傳給新的渲染函式
                    renderAllProgressBars(response.data.weights, response.data.supplier_names);
                }
            }
        });
    }

    // --- (*** 已修改：處理商品頁的潛在重量 ***) ---
    if ($('body').hasClass('single-product')) {
        const quantityInput = $('input.qty');
        
        function updateForProductPage() {
            // 我們不再需要 AJAX，因為所有初始資料都在 params 裡
            const initialWeights = $.extend(true, {}, params.initial_supplier_weights); // 深度複製
            const quantity = parseInt(quantityInput.val()) || 1;
            
            // 如果這個商品有重量和供應商 ID
            if (params.product_weight > 0 && params.product_supplier_id) {
                const supplierId = params.product_supplier_id;
                
                // 如果購物車中還沒有這個供應商，就從 0 開始加
                if (!initialWeights.hasOwnProperty(supplierId)) {
                    initialWeights[supplierId] = 0;
                }
                
                // 計算潛在重量
                initialWeights[supplierId] += params.product_weight * quantity;
            }
            
            renderAllProgressBars(initialWeights, params.supplier_names);
        }
        
        quantityInput.on('change input', updateForProductPage);
        // 初始渲染
        updateForProductPage();

    } else {
        // 購物車和商店頁面的初始渲染
        renderAllProgressBars(params.initial_supplier_weights, params.supplier_names);
        
        // 監聽事件
        $(document.body).on('updated_cart_totals added_to_cart removed_from_cart', updateProgressBarViaAjax);
    }
});