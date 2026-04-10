const __ = (window.wp && window.wp.i18n && window.wp.i18n.__)
	? window.wp.i18n.__
	: (s) => s;

jQuery(
	($) => {
		const POPUP_WIDTH = 400;
		const POPUP_HEIGHT = 600;
		const openedOrders = new Set();
		function testPopupEnabled() {
			const w = window.open('', '_blank', 'width=100,height=100');
			if (!w) {
				alert(__('Habilite las ventanas emergentes en su navegador y vuelva a intentarlo.', 'neopayment-payment-gateway'));
				return false;
			}
			w.close();
			return true;
		}
		function handlePopupEvents() {
			$(document).on(
				'click',
				'button[name="woocommerce_checkout_place_order"]',
				function (e) {
					$('.woocommerce-notices-wrapper').empty();
					if (!testPopupEnabled()) {
						e.preventDefault();
						return false;
					}
				}
			);

			$(document).on(
				'click',
				'button[name="woocommerce_checkout_place_order"]',
				function (e) {
					if (!testPopupEnabled()) {
						e.preventDefault();
						return false;
					}
				}
			);

			$(document).on(
				'click',
				'.wc-block-components-checkout__button button',
				function (e) {
					if (!testPopupEnabled()) {
						e.preventDefault();
						return false;
					}
				}
			);

			// AJAX for classic checkout.
			$(document).ajaxComplete(
				(e, xhr, settings) => {
					if (settings.url.includes('wc-ajax=checkout')) {
						try {
							const response = JSON.parse(xhr.responseText);
							handleCheckoutResponse(response);
						} catch (error) {
							console.error('[NEOPAYMENT-3DS] Error parsing AJAX response:', error);
						}
					}
				}
			);

			// Intercept fetch (checkout by blocks).
			if (window.fetch) {
				const originalFetch = window.fetch;
				window.fetch = async function (input, init) {
					const response = await originalFetch(input, init);
					const url = typeof input === 'string' ? input : input.url;

					if (url.includes('/wc/store/v1/checkout')) {
						const contentType = response.headers.get('content-type') || '';
						if (contentType.includes('application/json')) {
							try {
								const json = await response.clone().json();
								handleCheckoutResponse(json);
							} catch (error) {
								console.error('[NEOPAYMENT-3DS] Error parsing fetch response:', error);
							}
						}
					}

					return response;
				};
			}
		}
		function handleCheckoutResponse(response) {
			const orderId = response.order_id ?? response.payment_result?.order_id ?? null;
			if (orderId && openedOrders.has(orderId)) {
				console.warn('[NEOPAYMENT-3DS] This order_id has already been processed:', orderId);
				return;
			}
			if (orderId) {
				openedOrders.add(orderId);
			}

			// Classic checkout.
			if (response.requires_challenge && response.challenge_url) {
				open3DSPopup(response.challenge_url);
				return;
			}

			// Checkout blocks.
			if (response.payment_result?.payment_details) {
				const details = response.payment_result.payment_details.reduce(
					(acc, { key, value }) => {
						acc[key] = value;
						return acc;
					},
					{}
				);

				if (details.requires_challenge === '1' && details.challenge_url) {
					open3DSPopup(details.challenge_url);
					return;
				}

				if (details.redirect) {
					window.location.href = details.redirect;
					return;
				}
			}

			if (response.result === 'success' && response.redirect) {
				window.location.href = response.redirect;
			}
		}
		function open3DSPopup(url) {
			const popup = window.open(url, '_blank', `width = ${POPUP_WIDTH},height = ${POPUP_HEIGHT}`);
			if (!popup) {
				console.warn('[NEOPAYMENT-3DS] The browser blocked the popup.');
				showPopupWarning(
					__('Error', 'neopayment-payment-gateway'),
					__('No se pudo abrir la ventana emergente. Active las ventanas emergentes en su navegador y vuelva a intentarlo.', 'neopayment-payment-gateway')
				);
			}
		}
		function showPopupWarning(title, text) {
			console.warn(`[NEOPAYMENT - 3DS] ${title}: ${text}`);
			if (typeof swal === 'function') {
				window.swal({ title, text, icon: 'warning', button: __('Entendido', 'neopayment-payment-gateway') }).then(
					() => {
						location.reload();
					}
				);
			} else {
				alert(`${title}\n\n${text}`);
				location.reload();
			}
		}
		function initMessageHandler() {
			window.addEventListener(
				'message',
				(event) => {
					if (!event.data?.neopayment3ds) {
						return;
					}
					if (event.data.neopayment3ds === 'success') {
						window.location.href = event.data.redirect_to || window.location.href;
					} else {
						console.warn('[NEOPAYMENT-3DS] Authentication failed.');
						showPopupWarning(
							__('Error de autenticación', 'neopayment-payment-gateway'),
							__('Inténtelo nuevamente y mantenga la ventana emergente activa.', 'neopayment-payment-gateway')
						);
					}
				}
			);
		}
		$(document).ready(
			() => {
				handlePopupEvents();
				initMessageHandler();
			}
		);
	}
);