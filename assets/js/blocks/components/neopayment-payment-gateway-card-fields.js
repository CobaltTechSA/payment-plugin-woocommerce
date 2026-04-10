import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

import {
  formatExpiry,
  formatCvc,
  validateCard,
} from '../../includes/neopayment-payment-gateway-validators';

const CardFields = ({ onChange }) => {
  const [card_number, setcard_number] = useState('');
  const [card_expiry, setcard_expiry] = useState('');
  const [card_cvc, setcard_cvc] = useState('');
  const [card_holder, setcard_holder] = useState('');

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
      number: card_number,
      expiry: card_expiry,
      cvc: card_cvc,
      holder: card_holder,
    });

    setErrors({
      number: !result.number,
      expiry: !result.expiry,
      cvc: !result.cvc,
      holder: !result.holder,
    });

    onChange({
      card_number,
      card_expiry,
      card_cvc,
      card_holder,
      isValid: Object.values(result).every(v => v),
    });
  }, [card_number, card_expiry, card_cvc, card_holder, onChange]);

  return (
    <div className="neopayment-payment-gateway-card-fields">
      {/* Holde Name */}
      <div className="neopayment-payment-gateway-card-fields__group">
        <label>
          {__('Card holder', 'neopayment-payment-gateway')}<span class="required">*</span>
        </label>
        <input
          id="card_holder"
          type="text"
          placeholder={__('Full name', 'neopayment-payment-gateway')}
          maxLength="50"
          value={card_holder}
          onChange={e => setcard_holder(e.target.value)}
          aria-invalid={errors.holder}
          required
        />
        {errors.holder && (
          <small className="neopayment-payment-gateway-card-fields__error">
            {__('You must enter the holder name', 'neopayment-payment-gateway')}
          </small>
        )}
      </div>
      {/* Card Number */}
      <div className="neopayment-payment-gateway-card-fields__group">
        <label>
          {__('Card number', 'neopayment-payment-gateway')}<span class="required">*</span>
        </label>
        <input
          id="card_number"
          type="text"
          placeholder="1234 1234 1234 1234"
          maxLength="19"
          value={card_number}
          onBlur={() => setTouched(t => ({ ...t, number: true }))}
          onChange={e => {
            const digits = e.target.value.replace(/\D/g, '').slice(0, 16);
            setcard_number(digits.match(/.{1,4}/g)?.join(' ') || digits);
          }}
          aria-invalid={errors.number}
          required
        />
        {errors.number && touched.number && (
          <small className="neopayment-payment-gateway-card-fields__error">
            {__('Invalid card number', 'neopayment-payment-gateway')}
          </small>
        )}
      </div>

      { }
      <div className="neopayment-payment-gateway-card-fields__row">
        <div className="neopayment-payment-gateway-card-fields__group">
          <label>
            {__('Expiration date', 'neopayment-payment-gateway')}<span class="required">*</span>
          </label>
          <input
            id="card_expiry"
            type="text"
            placeholder={__('MM/YY', 'neopayment-payment-gateway')}
            value={card_expiry}
            onBlur={() => setTouched(t => ({ ...t, expiry: true }))}
            onChange={e => {
              const formatted = formatExpiry(e.target.value);
              setcard_expiry(formatted);
              if (formatted.length === 5) {
                setTouched(t => ({ ...t, expiry: true }));
              }
            }}
            aria-invalid={errors.expiry}
            required
          />
          {errors.expiry && touched.expiry && (
            <small className="neopayment-payment-gateway-card-fields__error">
              {__('Invalid date', 'neopayment-payment-gateway')}
            </small>
          )}
        </div>

        <div className="neopayment-payment-gateway-card-fields__group">
          <label>
            {__('CVC', 'neopayment-payment-gateway')}<span class="required">*</span>
          </label>
          <input
            id="card_cvc"
            type="password"
            placeholder="123"
            value={card_cvc}
            onBlur={() => setTouched(t => ({ ...t, cvc: true }))}
            onChange={e => setcard_cvc(formatCvc(e.target.value))}
            aria-invalid={errors.cvc}
            required
          />
          {errors.cvc && touched.cvc && (
            <small className="neopayment-payment-gateway-card-fields__error">
              {__('Invalid CVC', 'neopayment-payment-gateway')}
            </small>
          )}
        </div>
      </div>
    </div>
  );
};

export default CardFields;