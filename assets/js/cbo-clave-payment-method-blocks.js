( function() {
    const GATEWAY_ID = 'cbo_telered_gateway';
    const { createElement } = window.wp.element;
    const settings = window.wc.wcSettings.getPaymentMethodData( GATEWAY_ID );
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

    const Content = () => {
        return createElement( 'div', null, settings?.description || '' );
    };

    const Label = ( props ) => {
        const PaymentMethodLabel = props?.components?.PaymentMethodLabel || (() => settings?.title || GATEWAY_ID);
        /*return createElement( PaymentMethodLabel, {
            text: settings?.title || GATEWAY_ID,
        } );*/

        return createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
            // Texto del método
            createElement(PaymentMethodLabel, {
                text: settings.title || GATEWAY_ID,
            }),

            // Mapeamos cada ícono y lo mostramos como <img>
            ...(settings.icons || []).map((icon, index) =>
                createElement('img', {
                    key: index,
                    src: icon.src,
                    alt: icon.alt || '',
                    width: 32,
                    height: 20,
                    style: { display: 'block' }
                })
            ),
        );
    };

    registerPaymentMethod( {
        name: GATEWAY_ID,
        label: createElement( Label, {} ), // ✅ corregido aquí
        ariaLabel: settings?.title || GATEWAY_ID,
        canMakePayment: () => true,
        content: createElement( Content, {} ),
        edit: createElement( Content, {} ),
        supports: {
            showSavedCards: false,
            showSaveOption: false,
            features: settings?.supports ?? [],
        }
    } );
} )();
