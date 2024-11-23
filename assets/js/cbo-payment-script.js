let jq = null;
(function () {
    jQuery( function( $ ) {
        //jq = $;
        function addBrowserData() {
            console.log('Adding browser data');
            let navParams = {
                browserJavaEnabled: navigator.javaEnabled(),
                browserJavascriptEnabled: true,
                browserLanguage: navigator.language,
                browserColorDepth: screen.colorDepth,
                browserScreenWidth: screen.width,
                browserScreenHeight: screen.height,
                browserTZ: new Date().getTimezoneOffset(),
                browserUserAgent: navigator.userAgent,
                challengeWindowSize: screen.width,
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
                let paymentMethod = $('input[type=radio][name=payment_method]').change(function () {
                    if (this.value === 'cbo_standard_gateway') {
                        addBrowserData();
                    } else {
                        removeBrowserData();
                    }
                });

                console.log(paymentMethod);
            }, 1000)

        });
    })

}());