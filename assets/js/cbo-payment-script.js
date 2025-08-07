let jq = null;
(function () {
    jQuery( function( $ ) {
        jq = $;
        function addBrowserData() {
            console.log('Adding browser data');
            const POPUP_WIDTH  = 400;
            const POPUP_HEIGHT = 600;
            let navParams = {
                browserJavaEnabled: 0,
                browserJavascriptEnabled: 1,
                browserLanguage: navigator.language,
                browserColorDepth: screen.colorDepth,
                browserScreenWidth: POPUP_WIDTH,
                browserScreenHeight: POPUP_HEIGHT,
                browserTZ: new Date().getTimezoneOffset(),
                browserUserAgent: navigator.userAgent,
                challengeWindowSize:POPUP_WIDTH,
            }
            let payForm = $('form[name="checkout"]');

            for (let p in navParams) {
                let el = `<input class="cbo-standard-gateway-browser" type="hidden" name="${p}" value="${navParams[p]}" />`;
                console.log(el);
                payForm.append(el);
            }
        }

        function removeBrowserData() {
            console.log('Removing browser data');
            $('.cbo-standard-gateway-browser').remove();
        }

        $(document).ready( function() {
            setTimeout(_ => {
                let onPaymentMethodChange = function (paymentMethod) {
                    console.log(paymentMethod);
                    if (paymentMethod === 'cbopaga_standard_gateway') {
                        addBrowserData();
                    } else {
                        removeBrowserData();
                    }
                }

                $('input[type=radio][name=payment_method]').change(function () {
                    onPaymentMethodChange($(this).val());
                });

                onPaymentMethodChange($('input[type=radio][name=payment_method]:checked').val())
            }, 1000)

        });
    })

}());