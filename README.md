
# CBO Payment Gateway

**Author:** [Cobalt Tech](https://cobalt.tech)  
**Tags:** woocommerce 
Requires at least: 5.3
Tested up to: 6.8
Stable tag: 2.4.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Accept credit card payments in WooCommerce using the Cobalt Tech payment gateway.

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

2. Upload the entire `cbo-payment-gateway` folder into:

   ```
   /wp-content/plugins/
   ```

3. In your WordPress admin, go to **Plugins** → **Installed Plugins** → **Activate** the **Cobalt Tech Gateway**.

---

## Usage

Once activated, navigate to **WooCommerce** → **Settings** → **Payments** and enable **Cobalt Tech Gateway**. Enter your API credentials and save. 

## i18n / Translations

All user-facing strings are translatable. To generate and compile translation files, follow these steps from the plugin root (Requires WP-CLI):

1. **Extract strings to a `.pot` file**  
   ```bash
   wp i18n make-pot . i18n/cbo-payment-gateway.pot --slug=cbo-payment-gateway
   ```

2. **Initialize a new locale** (example: Spanish – `es_ES`)  
   ```bash
   msginit \
     --no-translator \
     --input=i18n/cbo-payment-gateway.pot \
     --locale=es_ES \
     --output-file=i18n/cbo-payment-gateway-es_ES.po
   ```

3. **Translate strings**  
   - Open `i18n/cbo-payment-gateway-es_ES.po`  
   - Fill in each `msgstr` for every `msgid`  
   - Save your changes  

4. **Compile the `.mo` file**  
   ```bash
   msgfmt \
     i18n/cbo-payment-gateway-es_ES.po \
     -o i18n/cbo-payment-gateway-es_ES.mo
   ```

5. **Generate JSON for React/JS blocks**  
   ```bash
   wp i18n make-json i18n i18n --domain=cbo-payment-gateway
   ```
   - **Input**: All `.pot` files inside `i18n/`  
   - **Output**: `cbo-payment-gateway-<locale>.json` files in `i18n/`

6. **Copy JSON files into your build directory**  
   ```bash
   cp i18n/*.json build/i18n/
   ```

---