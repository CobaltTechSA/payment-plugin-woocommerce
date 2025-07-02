import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import claveUrl from '../../images/clave.svg';
import ProcessPaymentHandler from './components/ProcessPaymentHandler';

const Label = ({ label }) => (
  <div className="cbo-payment-label">
    <span>{__('Clave Card', 'cbo-payment-gateway')}</span>
     <div className="cbo-payment-label__icons">
      <img src={claveUrl} alt="Visa" className="cbo-payment-label__icon" />
    </div>
  </div>
);

const settings = {
  name: 'cbo_telered_gateway',
  ariaLabel: __('CBO Telered Gateway', 'cbo-payment-gateway'),
  label: <Label />,
  canMakePayment: () => true,
  content: <ProcessPaymentHandler />,
  edit: <ProcessPaymentHandler />,
  paymentMethodId: 'cbo_telered_gateway',
  supports: { features: ['products'] },
  placeOrderButtonLabel: __('Pay with Clave', 'cbo-payment-gateway'),
}
registerPaymentMethod(settings);