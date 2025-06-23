import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import ProcessPaymentHandler from './components/ProcessPaymentHandler';

const settings = {
    name: 'cbo_telered_gateway',
    ariaLabel: __( 'Pasarela CBO Telered', 'cbo-payment-gateway' ),
    label: __( 'Tarjeta Clave', 'cbo-payment-gateway' ),
    canMakePayment: () => true,
    content:<ProcessPaymentHandler />,
    edit: <ProcessPaymentHandler />,
    paymentMethodId:    'cbo_telered_gateway',
  supports: { features: [ 'products' ] },
  placeOrderButtonLabel: __( 'Pagar con Clave', 'cbo-payment-gateway' ),
} 
registerPaymentMethod(settings);