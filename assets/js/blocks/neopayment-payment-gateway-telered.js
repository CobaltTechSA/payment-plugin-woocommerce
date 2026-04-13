import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import claveUrl from '../../images/clave.svg';
import ProcessPaymentHandler from './components/neopayment-payment-gateway-process-payment-handler';

const Label         = ({ label }) => (
	<div className = "neopayment-payment-label">
	<span> {__( 'Clave Card', 'neopayment-payment-gateway' )} </span>
	<div className = "neopayment-payment-label__icons">
		<img src   = {claveUrl} alt = "Clave" className = "neopayment-payment-label__icon"/>
	</div>
	</div>
);

const settings = {
	name: 'neopayment_payment_gateway_telered_gateway',
	ariaLabel: __( 'Neopayment Telered Gateway', 'neopayment-payment-gateway' ),
	label: <Label/>,
	canMakePayment: () => true,
	content: <ProcessPaymentHandler/> ,
	edit: <ProcessPaymentHandler/> ,
	paymentMethodId: 'neopayment_payment_gateway_telered_gateway',
	supports: { features: ['products'] },
	placeOrderButtonLabel: __( 'Pay with Clave', 'neopayment-payment-gateway' ),
}
registerPaymentMethod( settings );