import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import CardFields from './components/CardFields';

const settings = {
    name: 'cbo_telered_gateway',
    ariaLabel: __( 'Pasarela CBO Telered', 'cbo-payment-gateway' ),
    label: __( 'Tarjeta Clave', 'cbo-payment-gateway' ),
    canMakePayment: () => true,
    content: <CardFields />,
    edit: <CardFields />,
    
    supports: { features: [ 'products' ] },
} 
registerPaymentMethod(settings);