import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import CardFields from './components/neopayment-payment-gateway-card-fields';
import visaUrl from '../../images/visa.svg';
import mcUrl from '../../images/mastercard.svg';
import {
  validateLuhn,
  validateExpiry,
  validateCvc
} from '../includes/neopayment-payment-gateway-validators';

const Label = ({ label }) => (
  <div className="neopayment-payment-label">
    <span>{ __( 'Card (Visa/Mastercard)', 'neopayment-payment-gateway' ) }</span>
    <div className="neopayment-payment-label__icons">
      <img
        src={ visaUrl }
        alt="Visa"
        className="neopayment-payment-label__icon"
      />
      <img
        src={ mcUrl }
        alt="Mastercard"
        className="neopayment-payment-label__icon"
      />
    </div>
  </div>
);

const POPUP_WIDTH  = 400;
const POPUP_HEIGHT = 600;

const getBrowserData = () => ({
  browserJavaEnabled: navigator.javaEnabled ? '1' : '0',
  browserJavascriptEnabled: '1',
  browserLanguage: navigator.language,
  browserColorDepth: window.screen.colorDepth.toString(),
  browserScreenWidth: POPUP_WIDTH.toString(),
  browserScreenHeight: POPUP_HEIGHT.toString(),
  browserTZ: new Date().getTimezoneOffset().toString(),
  browserUserAgent: navigator.userAgent,
  challengeWindowSize: POPUP_WIDTH.toString(),
});

function PaymentMethod({
  eventRegistration,
  emitResponse,
  billingAddress = {},
  shippingAddress = {},
}) {
  const [cardData, setCardData] = useState({
    card_number: '', card_expiry: '', card_cvc: '', card_holder: ''
  });

  useEffect(() => {
    const unsubscribe = eventRegistration.onPaymentSetup(() => {

      const rawNumber = cardData.card_number;
      const cleanNumber = rawNumber.replace(/\s+/g, '');
      const { card_number, card_expiry, card_cvc, card_holder } = cardData;

      // validate card data
      if (!validateLuhn(cleanNumber)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Invalid card number', 'neopayment-payment-gateway'),
        };
      }
      if (!validateExpiry(card_expiry)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Invalid date', 'neopayment-payment-gateway'),
        };
      }
      if (!validateCvc(card_cvc)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Invalid CVC', 'neopayment-payment-gateway'),
        };
      }
      if (!card_holder) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Holder name is required', 'neopayment-payment-gateway'),
        };
      }
      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {
            card_number: cleanNumber,
            card_expiry,
            card_cvc,
            card_holder,
            ...getBrowserData(),
          },
        },
      };
    });
    return () => unsubscribe();
  }, [eventRegistration, emitResponse, billingAddress, shippingAddress, cardData]);
  return <CardFields onChange={setCardData} />;
};

registerPaymentMethod({
  name: 'neopayment_payment_gateway_standard_gateway',
  label: <Label />,
  ariaLabel: __('NEOPAYMENT Standard Gateway', 'neopayment-payment-gateway'),
  canMakePayment: () => true,
  content: <PaymentMethod />,
  edit: <PaymentMethod />,
  supports: { features: ['products'] },
});
