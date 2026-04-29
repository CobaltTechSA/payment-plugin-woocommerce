const __ = (window.wp && window.wp.i18n && window.wp.i18n.__)
	? window.wp.i18n.__
	: (s) => s;

jQuery(
	($) => {
		const MODAL_WIDTH = 560;
		const MODAL_HEIGHT = 760;
		const openedChallenges = new Set();
		let modalElements = null;
		let iframeLoadTimer = null;
		let fallbackCountdownTimer = null;
		let processingHintTimer = null;
		let activeChallengeUrl = null;
		let frameLoadCount = 0;
		let callbackHandled = false;

		function ensure3DSModal() {
			if (modalElements) {
				return modalElements;
			}
			if (!document.getElementById('neopayment-3ds-inline-styles')) {
				const style = document.createElement('style');
				style.id = 'neopayment-3ds-inline-styles';
				style.textContent = '@keyframes neopayment3dsspin { to { transform: rotate(360deg); } }';
				document.head.appendChild(style);
			}

			const overlay = document.createElement('div');
			overlay.className = 'neopayment-3ds-modal-overlay';
			overlay.style.position = 'fixed';
			overlay.style.inset = '0';
			overlay.style.display = 'none';
			overlay.style.alignItems = 'center';
			overlay.style.justifyContent = 'center';
			overlay.style.background = 'rgba(0,0,0,.65)';
			overlay.style.zIndex = '99999';
			overlay.style.padding = '16px';

			const container = document.createElement('div');
			container.className = 'neopayment-3ds-modal';
			container.setAttribute('role', 'dialog');
			container.setAttribute('aria-modal', 'true');
			container.style.width = `${MODAL_WIDTH}px`;
			container.style.maxWidth = '95vw';
			container.style.position = 'relative';
			container.style.background = '#fff';
			container.style.borderRadius = '10px';
			container.style.boxShadow = '0 16px 50px rgba(0,0,0,.35)';
			container.style.overflow = 'hidden';

			const header = document.createElement('div');
			header.className = 'neopayment-3ds-modal__header';
			header.style.padding = '14px 16px';
			header.style.fontSize = '16px';
			header.style.fontWeight = '600';
			header.style.color = '#22324a';
			header.style.borderBottom = '1px solid #e7ebf0';
			header.style.background = '#f8fafc';
			header.style.display = 'flex';
			header.style.alignItems = 'center';
			header.style.justifyContent = 'space-between';

			const title = document.createElement('span');
			title.textContent = __('Verificación segura 3DS', 'neopayment-payment-gateway');

			const iframe = document.createElement('iframe');
			iframe.className = 'neopayment-3ds-modal__frame';
			iframe.setAttribute('title', __('Autenticación 3DS', 'neopayment-payment-gateway'));
			iframe.setAttribute('allow', 'payment');
			iframe.style.height = `${MODAL_HEIGHT}px`;
			iframe.style.maxHeight = '80vh';
			iframe.style.display = 'block';
			iframe.style.width = '100%';
			iframe.style.border = '0';
			iframe.style.background = '#fff';

			const processingLayer = document.createElement('div');
			processingLayer.className = 'neopayment-3ds-modal__processing';
			processingLayer.style.display = 'none';
			processingLayer.style.position = 'absolute';
			processingLayer.style.inset = '0';
			processingLayer.style.background = 'rgba(255,255,255,.95)';
			processingLayer.style.alignItems = 'center';
			processingLayer.style.justifyContent = 'center';
			processingLayer.style.flexDirection = 'column';
			processingLayer.style.gap = '10px';
			processingLayer.style.zIndex = '2';

			const processingSpinner = document.createElement('div');
			processingSpinner.style.width = '36px';
			processingSpinner.style.height = '36px';
			processingSpinner.style.border = '4px solid #d7deea';
			processingSpinner.style.borderTopColor = '#2f6fb3';
			processingSpinner.style.borderRadius = '50%';
			processingSpinner.style.animation = 'neopayment3dsspin 0.9s linear infinite';

			const processingText = document.createElement('p');
			processingText.textContent = __('Procesando autenticación 3DS. Por favor espere...', 'neopayment-payment-gateway');
			processingText.style.margin = '0';
			processingText.style.fontSize = '14px';
			processingText.style.color = '#334155';
			processingText.style.textAlign = 'center';
			processingText.style.padding = '0 18px';

			processingLayer.appendChild(processingSpinner);
			processingLayer.appendChild(processingText);

			const status = document.createElement('div');
			status.className = 'neopayment-3ds-modal__status';
			status.style.display = 'none';
			status.style.padding = '10px 14px';
			status.style.fontSize = '13px';
			status.style.color = '#49566d';
			status.style.borderTop = '1px solid #e7ebf0';
			status.style.background = '#f8fafc';

			const footer = document.createElement('div');
			footer.className = 'neopayment-3ds-modal__footer';
			footer.style.display = 'flex';
			footer.style.justifyContent = 'space-between';
			footer.style.gap = '10px';
			footer.style.alignItems = 'center';
			footer.style.padding = '10px 14px 14px';
			footer.style.background = '#f8fafc';

			const openWindowBtn = document.createElement('button');
			openWindowBtn.type = 'button';
			openWindowBtn.className = 'neopayment-3ds-modal__open-window';
			openWindowBtn.textContent = __('Abrir en nueva ventana', 'neopayment-payment-gateway');
			openWindowBtn.style.border = '1px solid #ccd5e2';
			openWindowBtn.style.background = '#fff';
			openWindowBtn.style.color = '#22324a';
			openWindowBtn.style.padding = '8px 12px';
			openWindowBtn.style.borderRadius = '6px';
			openWindowBtn.style.fontSize = '13px';
			openWindowBtn.style.cursor = 'pointer';
			openWindowBtn.style.display = 'none';

			const cancelBtn = document.createElement('button');
			cancelBtn.type = 'button';
			cancelBtn.className = 'neopayment-3ds-modal__cancel';
			cancelBtn.textContent = __('Cancelar verificación', 'neopayment-payment-gateway');
			cancelBtn.style.border = '1px solid #ccd5e2';
			cancelBtn.style.background = '#fff';
			cancelBtn.style.color = '#22324a';
			cancelBtn.style.padding = '8px 12px';
			cancelBtn.style.borderRadius = '6px';
			cancelBtn.style.fontSize = '13px';
			cancelBtn.style.cursor = 'pointer';

			footer.appendChild(openWindowBtn);
			footer.appendChild(cancelBtn);
			header.appendChild(title);
			container.appendChild(header);
			container.appendChild(iframe);
			container.appendChild(processingLayer);
			container.appendChild(status);
			container.appendChild(footer);
			overlay.appendChild(container);
			document.body.appendChild(overlay);

			cancelBtn.addEventListener('click', () => {
				// Allow retry for the same challenge URL after a manual close.
				if (activeChallengeUrl) {
					openedChallenges.delete(activeChallengeUrl);
				}
				close3DSModal();
				window.location.reload();
			});
			openWindowBtn.addEventListener('click', () => {
				if (!activeChallengeUrl) {
					return;
				}
				window.open(activeChallengeUrl, '_blank', `width=${MODAL_WIDTH},height=${MODAL_HEIGHT}`);
			});

			modalElements = { overlay, iframe, status, processingLayer, openWindowBtn };
			return modalElements;
		}

		function showProcessingLayer(show) {
			if (!modalElements) {
				return;
			}
			modalElements.processingLayer.style.display = show ? 'flex' : 'none';
		}

		function toggleOpenWindowButton(show) {
			if (!modalElements) {
				return;
			}
			modalElements.openWindowBtn.style.display = show ? 'inline-block' : 'none';
		}

		function setModalStatus(text, show = true) {
			if (!modalElements) {
				return;
			}
			modalElements.status.textContent = text || '';
			modalElements.status.style.display = show ? 'block' : 'none';
		}

		function clearFallbackCountdown() {
			if (fallbackCountdownTimer) {
				clearInterval(fallbackCountdownTimer);
				fallbackCountdownTimer = null;
			}
		}

		function clearProcessingHint() {
			if (processingHintTimer) {
				clearTimeout(processingHintTimer);
				processingHintTimer = null;
			}
		}

		function startFallbackCountdown(seconds) {
			let remaining = seconds;
			setModalStatus(
				__(
					'Cargando verificación 3DS... Si tarda demasiado, podrás abrirla en nueva ventana en',
					'neopayment-payment-gateway'
				) + ` ${remaining}s`
			);
			clearFallbackCountdown();
			fallbackCountdownTimer = setInterval(() => {
				remaining -= 1;
				if (remaining <= 0) {
					clearFallbackCountdown();
					return;
				}
				setModalStatus(
					__(
						'Cargando verificación 3DS... Si tarda demasiado, podrás abrirla en nueva ventana en',
						'neopayment-payment-gateway'
					) + ` ${remaining}s`
				);
			}, 1000);
		}

		function open3DSModal(url) {
			const { overlay, iframe } = ensure3DSModal();
			activeChallengeUrl = url;
			frameLoadCount = 0;
			callbackHandled = false;
			showProcessingLayer(false);
			toggleOpenWindowButton(false);
			iframe.src = url;
			overlay.classList.add('is-open');
			overlay.style.display = 'flex';
			document.body.classList.add('neopayment-3ds-modal-open');
			startFallbackCountdown(7);

			if (iframeLoadTimer) {
				clearTimeout(iframeLoadTimer);
			}
			iframeLoadTimer = setTimeout(() => {
				console.warn('[NEOPAYMENT-3DS] Iframe 3DS no cargó a tiempo.');
				clearFallbackCountdown();
				showProcessingLayer(false);
				toggleOpenWindowButton(true);
				setModalStatus(__('La verificación está tardando. Si lo prefieres, ábrela en una nueva ventana.', 'neopayment-payment-gateway'));
			}, 7000);

			iframe.onload = () => {
				frameLoadCount += 1;
				if (iframeLoadTimer) {
					clearTimeout(iframeLoadTimer);
					iframeLoadTimer = null;
				}
				clearFallbackCountdown();
				// First load is normally the challenge form. Next loads are usually post-submit redirects.
				if (frameLoadCount <= 1) {
					showProcessingLayer(false);
					toggleOpenWindowButton(false);
					setModalStatus('', false);
				} else if (!callbackHandled) {
					// Usually happens after OTP submit redirects; keep user informed while callback arrives.
					showProcessingLayer(true);
					toggleOpenWindowButton(false);
					setModalStatus(__('Estamos finalizando tu autenticación. Por favor espera...', 'neopayment-payment-gateway'));
				}
			};

			clearProcessingHint();
		}

		function close3DSModal() {
			if (!modalElements) {
				return;
			}
			modalElements.overlay.classList.remove('is-open');
			modalElements.overlay.style.display = 'none';
			modalElements.iframe.src = 'about:blank';
			activeChallengeUrl = null;
			frameLoadCount = 0;
			callbackHandled = false;
			showProcessingLayer(false);
			toggleOpenWindowButton(false);
			clearFallbackCountdown();
			clearProcessingHint();
			setModalStatus('', false);
			document.body.classList.remove('neopayment-3ds-modal-open');
			if (iframeLoadTimer) {
				clearTimeout(iframeLoadTimer);
				iframeLoadTimer = null;
			}
		}

		function handlePopupEvents() {
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
			// Classic checkout.
			if (response.requires_challenge && response.challenge_url) {
				if (openedChallenges.has(response.challenge_url)) {
					return;
				}
				openedChallenges.add(response.challenge_url);
				open3DSModal(response.challenge_url);
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

				const challengeOn =
					details.requires_challenge === '1' ||
					details.requires_challenge === 1 ||
					details.requires_challenge === true ||
					details.requires_challenge === 'true';
				if (challengeOn && details.challenge_url) {
					if (openedChallenges.has(details.challenge_url)) {
						return;
					}
					openedChallenges.add(details.challenge_url);
					open3DSModal(details.challenge_url);
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
		function showUserMessage(title, text, reloadOnAcknowledge = false) {
			console.warn(`[NEOPAYMENT - 3DS] ${title}: ${text}`);
			if (typeof window.swal === 'function') {
				window
					.swal({ title, text, icon: 'warning', button: __('Entendido', 'neopayment-payment-gateway') })
					.then(() => {
						if (reloadOnAcknowledge) {
							window.location.reload();
						}
					});
			} else if (window.Swal && typeof window.Swal.fire === 'function') {
				window.Swal.fire({
					title,
					text,
					icon: 'warning',
					confirmButtonText: __('Entendido', 'neopayment-payment-gateway'),
				}).then(() => {
					if (reloadOnAcknowledge) {
						window.location.reload();
					}
				});
			} else {
				alert(`${title}\n\n${text}`);
				if (reloadOnAcknowledge) {
					window.location.reload();
				}
			}
		}
		function initMessageHandler() {
			window.addEventListener(
				'message',
				(event) => {
					if (!event.data?.neopayment3ds) {
						return;
					}
					callbackHandled = true;
					close3DSModal();
					if (event.data.neopayment3ds === 'success') {
						window.location.href = event.data.redirect_to || window.location.href;
					} else {
						console.warn('[NEOPAYMENT-3DS] Authentication failed.');
						showUserMessage(
							__('Error de autenticación', 'neopayment-payment-gateway'),
							__('Inténtalo nuevamente. Si el problema persiste, verifica con tu banco.', 'neopayment-payment-gateway'),
							true
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