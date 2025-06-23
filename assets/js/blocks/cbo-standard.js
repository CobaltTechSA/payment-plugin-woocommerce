import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import CardFields from './components/CardFields';

const settings = {
    name: 'cbo_standard_gateway',
    label: __('Tarjeta (Visa/Mastercard)', 'cbo-payment-gateway'),
    ariaLabel: __('Pasarela CBO Standard', 'cbo-payment-gateway'),
    canMakePayment: () => true,
    content: <CardFields />,
    edit: <CardFields />,
    supports: {
        features: ['products'],
    },
};

registerPaymentMethod(settings);
