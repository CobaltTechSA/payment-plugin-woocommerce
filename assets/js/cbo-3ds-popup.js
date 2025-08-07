jQuery(($) => {

  const POPUP_WIDTH  = 400;
  const POPUP_HEIGHT = 600;

  function testPopupEnabled() {
    const w = window.open('', '_blank', 'width=100,height=100');
    if (!w) {
      alert('Por favor, habilita las ventanas emergentes en tu navegador e inténtalo de nuevo.');
      return false;
    }
    w.close();
    return true;
  }

  $(document).on('click', 'button[name="woocommerce_checkout_place_order"]', function (e) {
    $('.woocommerce-notices-wrapper').empty();
    if (!testPopupEnabled()) {
      e.preventDefault();
      return false;
    }
  });

  $(document).on('click', 'button[name="woocommerce_checkout_place_order"]', function (e) {
    if (!testPopupEnabled()) {
      e.preventDefault();
      return false;
    }
  });

  $(document).on('click', '.wc-block-components-checkout__button button', function (e) {
    if (!testPopupEnabled()) {
      e.preventDefault();
      return false;
    }
  });

  let popup;
  let popupMonitor;

  // checkout classic
  $(document).ajaxComplete((e, xhr, settings) => {
    if (settings.url.includes('wc-ajax=checkout')) {
      _handleResponse(_parseJSON(xhr.responseText));
    }
  });

  // checkout block
  if (window.fetch) {
    const _origFetch = window.fetch;
    window.fetch = function (input, init) {
      return _origFetch(input, init).then(response => {
        const url = typeof input === 'string' ? input : input.url;
        const isStoreCheckout = url.includes('/wc/store/v1/checkout');
        const contentType = response.headers.get('content-type') || '';
        if (isStoreCheckout && contentType.includes('application/json')) {
          response.clone().json()
            .then(json => _handleResponse(json))
            .catch(() => { });
        }
        return response;
      });
    };
  }

  function _parseJSON(text) {
    try {
      return JSON.parse(text);
    } catch {
      return {};
    }
  }

  function _handleResponse(res) {
    // classic checkout
    if (res.requires_challenge && res.challenge_url) {
      _openPopup(res.challenge_url);
      return;
    }

    // checkout block
    if (res.payment_result && Array.isArray(res.payment_result.payment_details)) {
      const details = res.payment_result.payment_details.reduce((acc, { key, value }) => {
        acc[key] = value;
        return acc;
      }, {});
      if (details.requires_challenge === '1' && details.challenge_url) {
        _openPopup(details.challenge_url);
        return;
      }
      if (details.redirect && details.redirect) {
        window.location.href = details.redirect;
        return;
      }
    }

    // fallback if no challenge 
    if (res.result === 'success' && res.redirect) {
      window.location.href = res.redirect;
    }
  }

  function _openPopup(url) {
    popup = window.open('', '_blank', `width=${POPUP_WIDTH},height=${POPUP_HEIGHT}`);
    popup.location = url;
    let popupClosed = false;

  popupMonitor = setInterval(function () {
  if (popup && popup.closed && !popupClosed) {
    popupClosed = true;
    clearInterval(popupMonitor);
    
    swal({
      title: 'Autenticación Cancelada',
      text: 'Cerraste la ventana emergente antes de completar la autenticación. Por favor inténtalo de nuevo.',
      icon: 'warning',
      button: 'Entendido'
    }).then(() => {
      location.reload();
    });
  }
}, 500);
  }

window.addEventListener('message', function(event) {
  if (event.origin !== window.location.origin) return;
  
  if (!event.data || !event.data.cbo3ds) return;

   if (popupMonitor) {
    clearInterval(popupMonitor);
    popupMonitor = undefined;
  }

  if (event.data.cbo3ds === 'success') {
    window.location.href = event.data.redirect_to || window.location.href;
  } else {
    swal({
      title: 'Autenticación Fallida',
      text: 'Por favor inténtalo de nuevo. Mantén la ventana emergente activa.',
      icon: 'warning',
      button: 'Entendido'
    }).then(() => {
      location.reload();
    });
  }
});
});

