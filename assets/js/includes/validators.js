// validate card number
export function validateLuhn(num) {
  // delete spaces and dashes
  num = num.replace(/\s|-/g, '');
  if (num.length < 13 || num.length > 19) {
    return false;
  }
  // verify that it's numeric
  if (!/^\d+$/.test(num)) {
    return false;
  }
  //validate using Luhn algorithm
  let sum = 0;
  let shouldDouble = false;
  for (let i = num.length - 1; i >= 0; i--) {
    let digit = parseInt(num.charAt(i), 10);
    if (shouldDouble) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
    shouldDouble = !shouldDouble;
  }
  return sum % 10 === 0;
}

// format expiry date
export function formatExpiry(value) {
  let digits = value.replace(/\D/g, '');
  if (digits.length > 2) digits = `${digits.slice(0,2)}/${digits.slice(2,4)}`;
  return digits.slice(0,5);
}
// validate expiry date
export function validateExpiry(value) {
  const [mm, yy] = (value || '').split('/');
  if (
    !/^\d{2}$/.test(mm) ||
    !/^\d{2}$/.test(yy) ||
    parseInt(mm, 10) < 1 ||
    parseInt(mm, 10) > 12
  ) {
    return false;
  }

  const month = parseInt(mm, 10);
  const year = 2000 + parseInt(yy, 10);

  const now = new Date();
  const currentMonth = now.getMonth() + 1;
  const currentYear = now.getFullYear();

  if (year < currentYear) return false;
  if (year === currentYear && month < currentMonth) return false;

  return true;
}

// format CVC
export function formatCvc(value) {
  return value.replace(/\D/g, '').slice(0,4);
}
// validate CVC
export function validateCvc(value) {
  return /^[0-9]{3,4}$/.test(value);
}

// validate all card data
export function validateCard({ number, expiry, cvc, holder }) {
  const rawNumber = number.replace(/\s/g, '');
  return {
    number: rawNumber.length === 16 && validateLuhn(rawNumber),
    expiry: validateExpiry(expiry),
    cvc: validateCvc(cvc),
    holder: holder.trim().length > 0,
  };
}