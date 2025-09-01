jQuery(function($){
    // 只在結帳頁面執行
    if (!$('body').hasClass('woocommerce-checkout')) {
        return;
    }

    // --- 欄位選擇器 ---
    const shippingPhoneField = '#shipping_phone';
    const billingPhoneField = '#billing_phone';
    const shippingLastNameField = '#shipping_last_name';
    const shippingFirstNameField = '#shipping_first_name';

    // --- 共用電話驗證函式 ---
    function validatePhone(field) {
        let value = $(field).val();
        if (typeof value !== 'string') return false;

        // 全形轉換與過濾非數字字元
        const halfWidthValue = value.replace(/[０-９]/g, s =>
            String.fromCharCode(s.charCodeAt(0) - 0xFEE0)
        );
        const filteredValue = halfWidthValue.replace(/\D/g, '');

        // 更新輸入框的值
        if (value !== filteredValue) {
            $(field).val(filteredValue);
        }

        // 清除舊的錯誤提示
        $(field).closest('.form-row').find('.error-message').remove();

        // 格式驗證
        const isValid = /^09\d{8}$/.test(filteredValue);
        if (filteredValue.length > 0 && !isValid) {
            const errorMsg = filteredValue.length === 10 ?
                '手機格式錯誤 (需為 09 開頭)' :
                '手機號碼長度不正確';
            $(field).after(`<span class="error-message" style="color:red; display:block; margin-top:5px;">${errorMsg}</span>`);
        }
        return isValid;
    }

    // --- 姓名驗證函式 ---
    function validateNameFields() {
        const lastName = $(shippingLastNameField).val().match(/[\u4e00-\u9fa5]/g) || [];
        const firstName = $(shippingFirstNameField).val().match(/[\u4e00-\u9fa5]/g) || [];
        return (lastName.length + firstName.length) <= 5;
    }

    // --- 事件監聽 ---

    // 監聽收件電話
    $(document.body).on('input', shippingPhoneField, function() {
        validatePhone(this);
        // 觸發帳單電話的比對檢查
        $(billingPhoneField).trigger('input.wfs.compare');
    });

    // 監聽帳單電話
    $(document.body).on('input', billingPhoneField, function() {
        validatePhone(this);
         // 觸發收件電話的比對檢查
        $(shippingPhoneField).trigger('input.wfs.compare');
    });

    // 電話號碼比對的專用事件
    $(document.body).on('input.wfs.compare', shippingPhoneField + ', ' + billingPhoneField, function() {
        const billingPhone = $(billingPhoneField).val().trim();
        const shippingPhone = $(shippingPhoneField).val().trim();
        const $shippingRow = $(shippingPhoneField).closest('.form-row');

        $shippingRow.find('.phone-warning').remove(); // 先移除舊警告

        if (billingPhone && shippingPhone && billingPhone !== shippingPhone) {
            if (/^09\d{8}$/.test(billingPhone) && /^09\d{8}$/.test(shippingPhone)) {
                 $(shippingPhoneField).after('<span class="phone-warning error-message" style="color:#FFA500; display:block; margin-top:5px;">⚠️ 收件手機與訂購手機不同</span>');
            }
        }
    });


    // 監聽姓名欄位
    $(document.body).on('input', shippingLastNameField + ', ' + shippingFirstNameField, function() {
        const $row = $(shippingLastNameField).closest('.form-row');
        $row.find('.chinese-error, .length-error').remove(); // 先清除所有相關錯誤

        const lastName = $(shippingLastNameField).val();
        const firstName = $(shippingFirstNameField).val();
        const combinedName = lastName + firstName;

        // 1. 優先檢查是否包含非中文字元
        const hasNonChinese = combinedName.match(/[^\u4e00-\u9fa5]/);
        if (hasNonChinese) {
            $row.append('<span class="chinese-error error-message" style="color:red; display:block; margin-top:5px;">請輸入中文「姓名」</span>');
            return; // 顯示此錯誤後，暫不檢查長度
        }

        // 2. 檢查總長度
        if (!validateNameFields()) {
            $row.append('<span class="length-error error-message" style="color:red; display:block; margin-top:5px;">「姓名」總長不可超過 5 個字</span>');
        }
    });

    // --- 提交時的最終驗證 ---
    $('form.checkout').on('checkout_place_order', function(e) {
        // 清除所有既有的提示訊息
        $('.error-message').remove();
        $('.woocommerce-error').remove();

        let errorMessages = [];

        // 驗證電話
        if ($(billingPhoneField).val() && !validatePhone(billingPhoneField)) {
            errorMessages.push('「訂購電話」格式不正確 (需為 09 開頭的 10 位數字)');
        }
        if ($(shippingPhoneField).val() && !validatePhone(shippingPhoneField)) {
            errorMessages.push('「收件電話」格式不正確 (需為 09 開頭的 10 位數字)');
        }

        const lastName = $(shippingLastNameField).val();
        const firstName = $(shippingFirstNameField).val();
        const combinedName = lastName + firstName;

        // 驗證姓名是否為中文
        const hasNonChinese = combinedName.match(/[^\u4e00-\u9fa5]/);
        if (hasNonChinese) {
            errorMessages.push('「收件人姓名」請填寫中文');
        }

        // 驗證姓名長度
        if (!validateNameFields()) {
            errorMessages.push('「收件人姓名」總長不可超過 5 個字');
        }

        if (errorMessages.length > 0) {
            e.preventDefault();

            const errorHtml = `
                <ul class="woocommerce-error" role="alert">
                    ${errorMessages.map(msg => `<li>${msg}</li>`).join('')}
                </ul>
            `;

            $('form.checkout').prepend(errorHtml);
            $('html, body').animate({
                scrollTop: $('.woocommerce-error').offset().top - 100
            }, 500);

            return false;
        }
    });
});