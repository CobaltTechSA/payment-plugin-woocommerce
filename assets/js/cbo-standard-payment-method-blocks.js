( function() {
    const GATEWAY_ID = 'cbo_standard_gateway';
    const { createElement } = window.wp.element;
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { checkoutEventsEmitter, CHECKOUT_EVENTS } = window.wc.blocksCheckoutEvents;
    const settings = window.wc.wcSettings.getPaymentMethodData( GATEWAY_ID );

    function init() {
        const Content = ( props ) => {
            return createElement( 'div', null, settings?.description || '', buildCardForm( props ) );
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
            },

            // 🚀 Este método pasa los datos personalizados al servidor
            getPaymentMethodData: () => {
                return [
                    { key: 'card_number', value: document.getElementById(`${GATEWAY_ID}-card-number`)?.value || '' },
                    { key: 'card_expiry', value: document.getElementById(`${GATEWAY_ID}-card-expiry`)?.value || '' },
                    { key: 'card_cvv', value: document.getElementById(`${GATEWAY_ID}-card-cvc`)?.value || '' },
                    { key: 'card_holder', value: document.getElementById(`${GATEWAY_ID}-card-holder`)?.value || '' },
                ]
            },
        } );

        setUpExtraData();
    }

    function setUpExtraData() {

        jQuery( function( $ ) {
            window.$ = $;

            const { select, subscribe } = window.wp.data;
            const { paymentStore } = window.wc.wcBlocksData;

            let prev = null;
            const unsub = subscribe(() => {
                const active = select(paymentStore).getActivePaymentMethod();
                if (active && active !== prev) {
                    console.log('Método seleccionado:', active);
                    onPaymentMethodChanged(active);
                    prev = active;
                }
            });

            function onPaymentMethodChanged(paymentMethod) {
                console.log(paymentMethod);
                if (paymentMethod === GATEWAY_ID) {
                    addBrowserData();
                } else {
                    removeBrowserData();
                }
            }

            function addBrowserData() {
                console.log('Adding browser data');
                let navParams = {
                    browserJavaEnabled: 0,
                    browserJavascriptEnabled: 1,
                    browserLanguage: navigator.language,
                    browserColorDepth: screen.colorDepth,
                    browserScreenWidth: screen.width,
                    browserScreenHeight: screen.height,
                    browserTZ: new Date().getTimezoneOffset(),
                    browserUserAgent: navigator.userAgent,
                    challengeWindowSize: screen.width,
                }
                let payForm = $('.wc-block-components-form');

                for (let p in navParams) {
                    let el = `<input class="cbo-standard-gateway-data" type="hidden" name="${p}" value="${navParams[p]}" />`;
                    //console.log(el);
                    payForm.append(el);
                }

                console.log('Added all browser data')
            }

            function removeBrowserData() {
                console.log('Removing browser data');
                $('.cbo-standard-gateway-data').remove();
            }

        });
    }

    function buildCardForm( props ) {
        const {useState, useEffect } = window.wp.element;
        const [cardNumber, setCardNumber] = useState('');
        const [expiry, setExpiry] = useState('');
        const [cvc, setCvc] = useState('');
        const [cardHolder, setCardHolder] = useState('');

        //console.log('props', props);

        const { eventRegistration, emitResponse } = props;
        const { onPaymentSetup, onPaymentProcessing } = eventRegistration;

        useEffect( () => {
            const unsubscribe = onPaymentProcessing( async () => {
                // Here we can do any processing we need, and then emit a response.
                // For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.
                const checkoutData = {
                    paymentMethod: GATEWAY_ID,
                }
                checkoutData[`${GATEWAY_ID}-card-number`] = getVal(GATEWAY_ID + '-card-number');
                checkoutData[`${GATEWAY_ID}-card-expiry`] = getVal(GATEWAY_ID + '-card-expiry');
                checkoutData[`${GATEWAY_ID}-card-cvc`] = getVal(GATEWAY_ID + '-card-cvc');
                checkoutData[`${GATEWAY_ID}-card-holder`] = getVal(GATEWAY_ID + '-card-holder');

                //Add browserData ffor 3DSD
                document.querySelectorAll('.cbo-standard-gateway-data').forEach(el => {
                    checkoutData[el.name] = el.value;
                })

                const customDataIsValid = validateCardData(checkoutData);

                if ( customDataIsValid ) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: checkoutData,
                        },
                    };
                }

                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: 'Ha ocurrido un error al procesar la tarjeta. Por favor, revise los datos e intente nuevamente.',
                };
            } );
            // Unsubscribes when this component is unmounted.
            return () => {
                unsubscribe();
            };
        }, [
            emitResponse.responseTypes.ERROR,
            emitResponse.responseTypes.SUCCESS,
            onPaymentProcessing,
        ] );



        const onExpDate = (event) => {
            //console.log('event', event);
            let newValue = event.target.value;
            let oldValue = event.target.oldValue;
            let erasing = oldValue.length > newValue.length;

            if (erasing) {
                if (newValue.endsWith(' / ')) {
                    newValue = newValue.replace(' / ', '');
                }
            } else {
                if (newValue.length === 2) {
                    newValue += ' / '
                }
            }

            setExpiry(newValue);
            event.target.oldValue = newValue;
        }

        return createElement(
            'div',
            {className: 'cbo-card-fields'},

            // Número de tarjeta
            generateInput({
                    type: 'text',
                    id: `${GATEWAY_ID}-card-number`,
                    name: `${GATEWAY_ID}-card-number`,
                    placeholder: 'Número de tarjeta',
                    maxlength: 16,
                    value: cardNumber,
                    onChange: e => setCardNumber(e.target.value),
                    style: {display: 'block', marginBottom: '8px', width: '100%', padding: '1em .5em'},
                }),

            // Expiración
            generateInput({
                type: 'text',
                id: `${GATEWAY_ID}-card-expiry`,
                name: `${GATEWAY_ID}-card-expiry`,
                placeholder: 'MM / AA',
                value: expiry,
                maxlength: 7,
                onFocus: e => e.target.oldValue = e.target.value,
                onChange: e => onExpDate(e),
                style: {display: 'block', marginBottom: '8px', width: '48%', padding: '1em .5em'},
            }),
            // CVC
            generateInput({
                type: 'password',
                id: `${GATEWAY_ID}-card-cvc`,
                name: `${GATEWAY_ID}-card-cvc`,
                placeholder: 'CVV/CVC',
                value: cvc,
                maxlength: 3,
                onChange: e => setCvc(e.target.value),
                style: {display: 'block', width: '48%', padding: '1em .5em'},
            }),

            //Card holder
            generateInput({
                type: 'text',
                id: `${GATEWAY_ID}-card-holder`,
                name: `${GATEWAY_ID}-card-holder`,
                placeholder: 'Nombre en la tarjeta',
                value: cardHolder,
                onChange: e => setCardHolder(e.target.value),
                style: {display: 'block', marginBottom: '8px', width: '100%', padding: '1em .5em'},
            }),
        );
    }

    function getVal (id) {
        return document.getElementById(id)?.value?.trim();
    }
    function generateInput(opts) {
        return createElement('div',
            {
                className: 'wc-block-components-text-input'
            },
            createElement('input', opts)
        )
    }

    function validateCardData(checkoutData) {
        console.log(checkoutData)
        if (checkoutData.paymentMethod !== GATEWAY_ID) {
            return true; // No hacemos nada si no es nuestro método
        }

        let valid = true;
        const errors = [];



        const num = getVal(GATEWAY_ID + '-card-number');
        const exp = getVal(GATEWAY_ID + '-card-expiry');
        const cvv = getVal(GATEWAY_ID + '-card-cvc');
        const name = getVal(GATEWAY_ID + '-card-holder');

        const cardNumberCheck = ( value ) => {
            if ( value.length !== 16 ) {
                return false;
            }
            // Función Luhn
            const luhn = (num) => {
                // accept only digits, dashes or spaces
                if (/[^0-9-\s]+/.test(value)) return false;

                // The Luhn Algorithm. It's so pretty.
                let nCheck = 0;
                let nDigit = 0;
                let bEven = false;
                value = value.replace(/\D/g, "");

                for (let n = value.length - 1; n >= 0; n--) {
                    let cDigit = value.charAt(n);
                    nDigit = parseInt(cDigit, 10);

                    if (bEven) {
                        if ((nDigit *= 2) > 9) nDigit -= 9;
                    }

                    nCheck += nDigit;
                    bEven = !bEven;
                }

                return (nCheck % 10) === 0;
            };
            return luhn(value);

        };

        const validateExpiry = ( value ) => {
            if (!/^(0[1-9]|1[0-2])\s\/\s\d{2}$/.test(value)) {
                return false;
            }
            const [mm, yy] = value.split('/').map(v => parseInt(v,10));
            const now = new Date(), year = now.getFullYear() % 100, month = now.getMonth() + 1;
            return (yy > year || (yy === year && mm >= month));
        };

        const validateCvv = ( value ) => /^\d{3}$/.test(value);

        const validateHolder = ( value ) => value.length >= 3 && value.length <= 21;


        // Validaciones
        if (!cardNumberCheck(num)) {
            valid = false;
            errors.push('Número de tarjeta inválido.');
        }
        if (!validateExpiry(exp)) {
            valid = false;
            errors.push('Fecha de expiración inválida.');
        }
        if (!validateCvv(cvv)) {
            valid = false;
            errors.push('CVV debe tener 3 dígitos.');
        }
        if (!validateHolder(name)) {
            valid = false;
            errors.push('Nombre del titular demasiado largo o vacío.');
        }

        if (!valid) {
            console.log(num, exp, cvv, name);
            console.log('Errors', errors);
            errors.forEach(msg => {
                window.wp.data.dispatch('core/notices').createNotice(
                    'error',
                    msg,
                    { type: 'payment' }
                );
            });
        }

        return valid;
    }

    checkoutEventsEmitter.subscribe(CHECKOUT_EVENTS.CHECKOUT_VALIDATION, (checkoutData) => validateCardData(checkoutData) );
    init();

} )();
