jQuery(function($) {
    const params = window.wfs_progress_params;
    if (!params || !params.shipping_methods || params.shipping_methods.length === 0) {
        return;
    }

    const container = $('#wfs-shipping-progress-bar-container');
    if (!container.length) return;

    const maxWeightOverall = Math.max(...params.shipping_methods.map(method => method.max_weight));
    let xhr;

    // --- 主要的渲染函式 (*** 已更新，加入圖示渲染 ***) ---
    function renderProgressBar(currentWeight) {
        const weight = (typeof currentWeight === 'number' && !isNaN(currentWeight)) ? currentWeight : 0;
        container.empty();
        
        // --- Part 1: 渲染進度條 ---
        let progressBarHtml = '<div class="wfs-progress-bar-label">';
        progressBarHtml += `<span>${params.i18n.current_weight}:</span>`;
        progressBarHtml += `<span class="wfs-current-weight">${weight.toFixed(2)} kg</span>`;
        progressBarHtml += '</div>';
        progressBarHtml += '<div class="wfs-progress-bar-track">';
        const percentage = Math.min((weight / maxWeightOverall) * 100, 100);
        progressBarHtml += `<div class="wfs-progress-bar-fill" style="width: ${percentage}%;"></div>`;
        progressBarHtml += '</div>';
        
        // --- Part 2: 渲染下方的圖示 ---
        let iconsHtml = '<div class="wfs-tier-icons-container">';
        params.shipping_methods.forEach(method => {
            // 判斷目前重量是否已超過該運送方式的限制
            const isOverweight = weight > method.max_weight;
            const overweightClass = isOverweight ? 'is-overweight' : '';
            const tooltipText = isOverweight ? `超過 ${method.max_weight} kg，此運送方式暫不可用` : `重量上限: ${method.max_weight} kg`;

            iconsHtml += `
                <div class="wfs-tier-icon ${overweightClass}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.582,6.344a1.002,1.002,0,0,0-.862-.488l-3.34-.485-1.494-3.028a1,1,0,0,0-1.772,0L12.62,5.371,9.28,5.856a1,1,0,0,0-.862.488,1,1,0,0,0,.01,1.013l2.418,2.356- .57,3.328a1,1,0,0,0,.363.95,1,1,0,0,0,1.053.068l2.986-1.57,2.986,1.57a1,1,0,0,0,1.053-.068,1,1,0,0,0,.363-.95l-.57-3.328,2.418-2.356A1,1,0,0,0,21.582,6.344Z"/></svg> 
                    <span>${method.name}</span>
                    <span class="wfs-tier-tooltip">${tooltipText}</span>
                </div>
            `;
        });
        iconsHtml += '</div>';

        // 將組合好的 HTML 放入容器
        container.html(progressBarHtml + iconsHtml);

        // 使用 setTimeout 來觸發動畫
        setTimeout(function() {
            container.find('.wfs-progress-bar-fill').css('width', percentage + '%');
        }, 10);
    }

    // --- AJAX 更新函式 (維持不變) ---
    function updateProgressBarViaAjax() {
        if (xhr) { xhr.abort(); }
        xhr = $.ajax({
            type: 'POST',
            url: params.ajax_url,
            data: { action: 'wfs_get_cart_weight_for_progress', nonce: params.nonce },
            success: function(response) {
                if (response.success) {
                    renderProgressBar(response.data.weight);
                }
            }
        });
    }

    // --- 事件監聽 (維持不變) ---
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