import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import claveUrl from '../../images/clave.svg';
import ProcessPaymentHandler from './components/ProcessPaymentHandler';

const Label         = ({ label }) => (
	< div className = "cobalt-bank-operations-payment-label" >
	< span > {__( 'Clave Card', 'class-cobalt-bank-operations-payment-gateway' )} < / span >
	< div className = "cobalt-bank-operations-payment-label__icons" >
		< img src   = {claveUrl} alt = "Visa" className = "cobalt-bank-operations-payment-label__icon" / >
	< / div >
	< / div >
);

const settings = {
	name: 'cobalt_bank_operations_telered_gateway',
	ariaLabel: __( 'CBO Telered Gateway', 'class-cobalt-bank-operations-payment-gateway' ),
	label: < Label / > ,
	canMakePayment: () => true,
	content: < ProcessPaymentHandler / > ,
	edit: < ProcessPaymentHandler / > ,
	paymentMethodId: 'cobalt_bank_operations_telered_gateway',
	supports: { features: ['products'] },
	placeOrderButtonLabel: __( 'Pay with Clave', 'class-cobalt-bank-operations-payment-gateway' ),
}
registerPaymentMethod( settings );