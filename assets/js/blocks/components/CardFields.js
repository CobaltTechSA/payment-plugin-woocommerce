import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

import {
  formatExpiry,
  formatCvc,
  validateCard,
} from '../../includes/validators';

const CardFields = ({ onChange }) => {
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvc, setCardCvc] = useState('');
  const [cardHolder, setCardHolder] = useState('');

  const [errors, setErrors] = useState({
    number: false,
    expiry: false,
    cvc: false,
    holder: false,
  });

  const [touched, setTouched] = useState({
    number: false,
    expiry: false,
    cvc: false,
    holder: false,
  });

  useEffect(() => {
    const result = validateCard({
      number: cardNumber,
      expiry: cardExpiry,
      cvc: cardCvc,
      holder: cardHolder,
    });

    setErrors({
      number: !result.number,
      expiry: !result.expiry,
      cvc: !result.cvc,
      holder: !result.holder,
    });

    onChange({
      cardNumber,
      cardExpiry,
      cardCvc,
      cardHolder,
      isValid: Object.values(result).every(v => v),
    });
  }, [cardNumber, cardExpiry, cardCvc, cardHolder, onChange]);

  return (
    <div className="cbo-card-fields">
      {/* Titular */}
      <div className="cbo-card-fields__group">
        <label>
          {__('Card holder', 'cbo-payment-gateway')}<span class="required">*</span>
        </label>
        <input
          id="cardHolder"
          type="text"
          placeholder={__('Full name', 'cbo-payment-gateway')}
          maxLength="50"
          value={cardHolder}
          onChange={e => setCardHolder(e.target.value)}
          aria-invalid={errors.holder}
          required
        />
        {errors.holder && (
          <small className="cbo-card-fields__error">
            {__('You must enter the holder name', 'cbo-payment-gateway')}
          </small>
        )}
      </div>
      {/* Número de tarjeta */}
      <div className="cbo-card-fields__group">
        <label>
          {__('Card number', 'cbo-payment-gateway')}<span class="required">*</span>
        </label>
        <input
          id="cardNumber"
          type="text"
          placeholder="1234 1234 1234 1234"
          maxLength="19"
          value={cardNumber}
          onBlur={() => setTouched(t => ({ ...t, number: true }))}
          onChange={e => {
            const digits = e.target.value.replace(/\D/g, '').slice(0, 16);
            setCardNumber(digits.match(/.{1,4}/g)?.join(' ') || digits);
          }}
          aria-invalid={errors.number}
          required
        />
        {errors.number && touched.number && (
          <small className="cbo-card-fields__error">
            {__('Invalid card number', 'cbo-payment-gateway')}
          </small>
        )}
      </div>

      { }
      <div className="cbo-card-fields__row">
        <div className="cbo-card-fields__group">
          <label>
            {__('Expiration date', 'cbo-payment-gateway')}<span class="required">*</span>
          </label>
          <input
            id="cardExpiry"
            type="text"
            placeholder={__('MM/YY', 'cbo-payment-gateway')}
            value={cardExpiry}
            onBlur={() => setTouched(t => ({ ...t, expiry: true }))}
            onChange={e => {
              const formatted = formatExpiry(e.target.value);
              setCardExpiry(formatted);
              if (formatted.length === 5) {
                setTouched(t => ({ ...t, expiry: true }));
              }
            }}
            aria-invalid={errors.expiry}
            required
          />
          {errors.expiry && touched.expiry && (
            <small className="cbo-card-fields__error">
              {__('Invalid date', 'cbo-payment-gateway')}
            </small>
          )}
        </div>

        <div className="cbo-card-fields__group">
          <label>
            {__('CVC', 'cbo-payment-gateway')}<span class="required">*</span>
          </label>
          <input
            id="cardCvc"
            type="password"
            placeholder="123"
            value={cardCvc}
            onBlur={() => setTouched(t => ({ ...t, cvc: true }))}
            onChange={e => setCardCvc(formatCvc(e.target.value))}
            aria-invalid={errors.cvc}
            required
          />
          {errors.cvc && touched.cvc && (
            <small className="cbo-card-fields__error">
              {__('Invalid CVC', 'cbo-payment-gateway')}
            </small>
          )}
        </div>
      </div>
    </div>
  );
};

export default CardFields;