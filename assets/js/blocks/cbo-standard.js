import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import CardFields from './components/CardFields';
import visaUrl from '../../images/visa.svg';
import mcUrl from '../../images/mastercard.svg';
import {
  validateLuhn,
  validateExpiry,
  validateCvc
} from '../includes/validators';

const Label = ({ label }) => (
  <div className="cbo-payment-label">
    <span>{ __( 'Card (Visa/Mastercard)', 'cbo-payment-gateway' ) }</span>
    <div className="cbo-payment-label__icons">
      <img
        src={ visaUrl }
        alt="Visa"
        className="cbo-payment-label__icon"
      />
      <img
        src={ mcUrl }
        alt="Mastercard"
        className="cbo-payment-label__icon"
      />
    </div>
  </div>
);

const getBrowserData = () => ({
  browserJavaEnabled: navigator.javaEnabled ? '1' : '0',
  browserJavascriptEnabled: '1',
  browserLanguage: navigator.language,
  browserColorDepth: window.screen.colorDepth.toString(),
  browserScreenWidth: window.screen.width.toString(),
  browserScreenHeight: window.screen.height.toString(),
  browserTZ: new Date().getTimezoneOffset().toString(),
  browserUserAgent: navigator.userAgent,
  challengeWindowSize: window.screen.width.toString(),
});

function PaymentMethod({
  eventRegistration,
  emitResponse,
  billingAddress = {},
  shippingAddress = {},
}) {
  const [cardData, setCardData] = useState({
    cardNumber: '', cardExpiry: '', cardCvc: '', cardHolder: ''
  });

  useEffect(() => {
    const unsubscribe = eventRegistration.onPaymentSetup(() => {

      const rawNumber = cardData.cardNumber;
      const cleanNumber = rawNumber.replace(/\s+/g, '');
      const { cardNumber, cardExpiry, cardCvc, cardHolder } = cardData;

      // validate card data
      if (!validateLuhn(cardNumber)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Invalid card number', 'cbo-payment-gateway'),
        };
      }
      if (!validateExpiry(cardExpiry)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Invalid date', 'cbo-payment-gateway'),
        };
      }
      if (!validateCvc(cardCvc)) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Invalid CVC', 'cbo-payment-gateway'),
        };
      }
      if (!cardHolder) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: __('Holder name is required', 'cbo-payment-gateway'),
        };
      }
      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: {
          paymentMethodData: {
            cardNumber: cleanNumber,
            cardExpiry,
            cardCvc,
            cardHolder,
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
  name: 'cbo_standard_gateway',
  label: <Label />,
  ariaLabel: __('CBO Standard Gateway', 'cbo-payment-gateway'),
  canMakePayment: () => true,
  content: <PaymentMethod />,
  edit: <PaymentMethod />,
  supports: { features: ['products'] },
});
