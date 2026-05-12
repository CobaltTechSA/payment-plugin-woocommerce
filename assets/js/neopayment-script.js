let jq = null;
(function () {
	jQuery(function ($) {
		jq = $;

		/**
		 * Form selectors, in priority order: order-pay first, then classic checkout forms.
		 *
		 * @const {string}
		 */
		const PAY_FORM_SELECTOR = 'form#order_review, form.checkout, form[name="checkout"]';

		/**
		 * Resolves the active payment form jQuery object.
		 *
		 * @returns {jQuery} First matching form, or empty jQuery set.
		 */
		function getPayForm() {
			return $(PAY_FORM_SELECTOR).first();
		}

		/**
		 * Collects browser environment values expected by the gateway / 3DS2 payload.
		 *
		 * `challengeWindowSize` uses EMV code `3` (≈500×600), not pixel dimensions.
		 *
		 * @returns {Object<string, string|number>} Key-value map aligned with `neopayment_get_3ds_params()` keys.
		 */
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
				challengeWindowSize: 3,
			};
		}

		/**
		 * Ensures hidden inputs exist on the payment form and sets their values from `collectBrowserData()`.
		 *
		 * Targets elements with class `neopayment-standard-gateway-browser` and matching `name` attributes.
		 * If inputs already exist (e.g. rendered by PHP), updates values; otherwise appends new hidden fields.
		 *
		 * @returns {void}
		 */
		function ensureBrowserData() {
			const payForm = getPayForm();
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

		/**
		 * Removes Neopayment browser fingerprint hidden fields from all candidate checkout forms.
		 *
		 * Called when another payment method is selected so unrelated gateways are not polluted.
		 *
		 * @returns {void}
		 */
		function removeBrowserData() {
			$(PAY_FORM_SELECTOR).find('.neopayment-standard-gateway-browser').remove();
		}

		/**
		 * Handles payment method radio changes.
		 *
		 * @param {string} paymentMethod WooCommerce `payment_method` value (e.g. `neopayment_standard_gateway`).
		 * @returns {void}
		 */
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

			// Classic checkout (AJAX): Woo triggers these on `form.checkout` before serializing the request.
			$(document.body).on(
				'checkout_place_order checkout_place_order_neopayment_standard_gateway',
				'form.checkout, form[name="checkout"]',
				function () {
					ensureBrowserData();
					return true;
				}
			);

			// Pay-for-order: native form POST — no `checkout_place_order`; populate fields on submit.
			$(document).on('submit', 'form#order_review', function () {
				ensureBrowserData();
			});

			$(document.body).on('updated_checkout', function () {
				onPaymentMethodChange($('input[type=radio][name=payment_method]:checked').val());
			});

			onPaymentMethodChange($('input[type=radio][name=payment_method]:checked').val());
		});
	});

}());