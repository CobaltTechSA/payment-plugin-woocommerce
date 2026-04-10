
# Neopayment Payment Gateway

**Author:** [Neopayment](https:/neopayment.com)  
**Tags:** woocommerce 
Requires at least: 5.3
Tested up to: 6.8
Stable tag: 2.4.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Accept credit card payments in WooCommerce using the Neopayment payment gateway.

---

## Requirements

- WooCommerce ≥ 5.3  
- PHP ≥ 7.2.0  
- WordPress ≥ 4.4  

---

## Installation 

1. From your terminal, run:

   ```bash
   npm install
   npm run build
   ```

2. Upload the entire `neopayment-payment-gateway` folder into:

   ```
   /wp-content/plugins/
   ```

3. In your WordPress admin, go to **Plugins** → **Installed Plugins** → **Activate** the **Neopayment Gateway**.

---

## Usage

Once activated, navigate to **WooCommerce** → **Settings** → **Payments** and enable **Neopayment Gateway**. Enter your API credentials and save. 

## i18n / Translations

All user-facing strings are translatable. To generate and compile translation files, follow these steps from the plugin root (Requires WP-CLI):

1. **Extract strings to a `.pot` file**  
   ```bash
   wp i18n make-pot . i18n/neopayment-payment-gateway.pot --slug=neopayment-payment-gateway
   ```

2. **Initialize a new locale** (example: Spanish – `es_ES`)  
   ```bash
   msginit \
     --no-translator \
     --input=i18n/neopayment-payment-gateway.pot \
     --locale=es_ES \
     --output-file=i18n/neopayment-payment-gateway-es_ES.po
   ```

3. **Translate strings**  
   - Open `i18n/neopayment-payment-gateway-es_ES.po`  
   - Fill in each `msgstr` for every `msgid`  
   - Save your changes  

4. **Compile the `.mo` file**  
   ```bash
   msgfmt \
     i18n/neopayment-payment-gateway-es_ES.po \
     -o i18n/neopayment-payment-gateway-es_ES.mo
   ```

5. **Generate JSON for React/JS blocks**  
   ```bash
   wp i18n make-json i18n i18n --domain=neopayment-payment-gateway
   ```
   - **Input**: All `.pot` files inside `i18n/`  
   - **Output**: `neopayment-payment-gateway-<locale>.json` files in `i18n/`

6. **Copy JSON files into your build directory**  
   ```bash
   cp i18n/*.json build/i18n/
   ```

---