jQuery(function($) {
    // 檢查 tiptip 函式庫是否存在
    if ($.fn.tipTip) {
        // 初始化所有 class 為 woocommerce-help-tip 的元素
        $('.woocommerce-help-tip').tipTip({
            attribute: 'data-tip',
            fadeIn: 200,
            fadeOut: 200,
            delay: 200
        });
    } else {
        console.warn('WooCommerce Function Suite: tipTip library was not loaded.');
    }
});