let jq = null;
(function () {
	jQuery(function ($) {
		jq = $;

		function collectBrowserData() {
			const javaEnabled = (typeof navigator.javaEnabled === 'function' && navigator.javaEnabled()) ? 1 : 0;
			return {
				browserJavaEnabled: javaEnabled,
				browserJavascriptEnabled: 1,
				browserLanguage: navigator.language || '',
				browserColorDepth: screen.colorDepth || '',
				browserScreenWidth: window.screen && window.screen.width ? window.screen.width : '',
				browserScreenHeight: window.screen && window.screen.height ? window.screen.height : '',
				browserTZ: new Date().getTimezoneOffset(),
				browserUserAgent: navigator.userAgent || '',
				challengeWindowSize: 400,
			};
		}

		function ensureBrowserData() {
			const payForm = $('form.checkout, form[name="checkout"]').first();
			if (!payForm.length) {
				return;
			}

			const navParams = collectBrowserData();
			Object.keys(navParams).forEach((key) => {
				const selector = `.neopayment-standard-gateway-browser[name="${key}"]`;
				const existing = payForm.find(selector);
				const value = String(navParams[key]);
				if (existing.length) {
					existing.val(value);
				} else {
					payForm.append(`<input class="neopayment-standard-gateway-browser" type="hidden" name="${key}" value="${value}" />`);
				}
			});
		}

		function removeBrowserData() {
			$('form.checkout, form[name="checkout"]').find('.neopayment-standard-gateway-browser').remove();
		}

		function onPaymentMethodChange(paymentMethod) {
			if (paymentMethod === 'neopayment_standard_gateway') {
				ensureBrowserData();
			} else {
				removeBrowserData();
			}
		}

		$(document).ready(function () {
			$(document).on('change', 'input[type=radio][name=payment_method]', function () {
				onPaymentMethodChange($(this).val());
			});

			// Ensure real browser data right before classic checkout submit.
			$('form.checkout, form[name="checkout"]').on('checkout_place_order_neopayment_standard_gateway', function () {
				ensureBrowserData();
				return true;
			});
			$('form.checkout, form[name="checkout"]').on('checkout_place_order', function () {
				ensureBrowserData();
				return true;
			});

			// Woo refreshes checkout fragments; re-inject hidden fields after refresh.
			$(document.body).on('updated_checkout', function () {
				onPaymentMethodChange($('input[type=radio][name=payment_method]:checked').val());
			});

			onPaymentMethodChange($('input[type=radio][name=payment_method]:checked').val());
		});
	});

}());