import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

import {
  formatExpiry,
  formatCvc,
  validateCard,
} from '../../includes/validators';

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
    <div className="cobalt-bank-operations-card-fields">
      {/* Holde Name */}
      <div className="cobalt-bank-operations-card-fields__group">
        <label>
          {__('Card holder', 'class-cobalt-bank-operations-payment-gateway')}<span class="required">*</span>
        </label>
        <input
          id="card_holder"
          type="text"
          placeholder={__('Full name', 'class-cobalt-bank-operations-payment-gateway')}
          maxLength="50"
          value={card_holder}
          onChange={e => setcard_holder(e.target.value)}
          aria-invalid={errors.holder}
          required
        />
        {errors.holder && (
          <small className="cobalt-bank-operations-card-fields__error">
            {__('You must enter the holder name', 'class-cobalt-bank-operations-payment-gateway')}
          </small>
        )}
      </div>
      {/* Card Number */}
      <div className="cobalt-bank-operations-card-fields__group">
        <label>
          {__('Card number', 'class-cobalt-bank-operations-payment-gateway')}<span class="required">*</span>
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
          <small className="cobalt-bank-operations-card-fields__error">
            {__('Invalid card number', 'class-cobalt-bank-operations-payment-gateway')}
          </small>
        )}
      </div>

      { }
      <div className="cobalt-bank-operations-card-fields__row">
        <div className="cobalt-bank-operations-card-fields__group">
          <label>
            {__('Expiration date', 'class-cobalt-bank-operations-payment-gateway')}<span class="required">*</span>
          </label>
          <input
            id="card_expiry"
            type="text"
            placeholder={__('MM/YY', 'class-cobalt-bank-operations-payment-gateway')}
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
            <small className="cobalt-bank-operations-card-fields__error">
              {__('Invalid date', 'class-cobalt-bank-operations-payment-gateway')}
            </small>
          )}
        </div>

        <div className="cobalt-bank-operations-card-fields__group">
          <label>
            {__('CVC', 'class-cobalt-bank-operations-payment-gateway')}<span class="required">*</span>
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
            <small className="cobalt-bank-operations-card-fields__error">
              {__('Invalid CVC', 'class-cobalt-bank-operations-payment-gateway')}
            </small>
          )}
        </div>
      </div>
    </div>
  );
};

export default CardFields;