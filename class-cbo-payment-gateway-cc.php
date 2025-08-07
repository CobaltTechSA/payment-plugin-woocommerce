<?php

class CBOPAGA_Payment_Gateway_CC extends WC_Payment_Gateway_CC {

     public function __construct()
    {
        add_filter('woocommerce_credit_card_form_fields', [$this, 'cbopaga_reorder_credit_card_fields'], 20, 2);
    }

     public function cbopaga_reorder_credit_card_fields($fields, $gateway_id)
    {
        if ($gateway_id === $this->id) {
            $ordered   = [];
            $sequence  = [
                'card-holder-field',
                'card-number-field',
                'card-expiry-field',
                'card-cvc-field',
            ];
            foreach ($sequence as $key) {
                if (isset($fields[$key])) {
                    $ordered[$key] = $fields[$key];
                }
            }
            return $ordered;
        }
        return $fields;
    }
    /**
     * Builds our payment fields area - including tokenization fields for logged
     * in users, and the actual payment fields.
     *
     * @since 2.6.0
     */
    public function payment_fields() {
        if ( $this->supports( 'tokenization' ) && is_checkout() ) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->form();
            $this->save_payment_method_checkbox();
        } else {
            $this->form();
        }
    }

    /**
     * Output field name HTML
     *
     * Gateways which support tokenization do not require names - we don't want the data to post to the server.
     *
     * @since  2.6.0
     * @param  string $name Field name.
     * @return string
     */
     public function cbopaga_field_name($name) {
        return $this->supports('tokenization') ? '' : ' name="' . esc_attr($this->id . '-' . $name) . '" ';
    }

    /**
     * Outputs fields for entering credit card information.
     *
     * @since 2.6.0
     */
    public function form() {
        wp_enqueue_script( 'wc-credit-card-form' );

        $allowed_fields_html = array(
            'p'        => array(
                'class' => array(),
            ),
            'label'    => array(
                'for'   => array(),
                'class' => array(),
            ),
            'span'     => array(
                'class' => array(),
            ),
            'input'    => array(
                'id'             => array(),
                'class'          => array(),
                'inputmode'      => array(),
                'autocomplete'   => array(),
                'autocorrect'    => array(),
                'autocapitalize' => array(),
                'spellcheck'     => array(),
                'type'           => array(),
                'maxlength'      => array(),
                'placeholder'    => array(),
                'name'           => array(),
                'style'          => array(),
            ),
            'fieldset' => array(
                'id'    => array(),
                'class' => array(),
            ),
        );

        $cvc_field = '<p class="form-row form-row-last">
            <label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'cbo-payment-gateway' ) . '&nbsp;<span class="required">*</span></label>
            <input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="password" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="password" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'cbo-payment-gateway' ) . '" ' . $this->cbopaga_field_name( 'card-cvc' ) . ' style="width:100px" />
        </p>';

        $card_holder_field = '<p class="form-row form-row-wide">
            <label for="' . esc_attr( $this->id ) . '-card-holder">' . esc_html__( 'Card holder', 'cbo-payment-gateway' ) . '&nbsp;<span class="required">*</span></label>
            <input id="' . esc_attr( $this->id ) . '-card-holder" class="input-text wc-credit-card-form-card-holder" inputmode="text" autocomplete="cc-card-holder" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="' . esc_attr__( 'Card holder', 'cbo-payment-gateway' ) . '" ' . $this->cbopaga_field_name( 'card-holder' ) . ' />
        </p>';

        $default_fields = array(
            'card-number-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'cbo-payment-gateway' ) . '&nbsp;<span class="required">*</span></label>
                <input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->cbopaga_field_name( 'card-number' ) . ' />
            </p>',
            'card-expiry-field' => '<p class="form-row form-row-first">
                <label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YY)', 'cbo-payment-gateway' ) . '&nbsp;<span class="required">*</span></label>
                <input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'cbo-payment-gateway' ) . '" ' . $this->cbopaga_field_name( 'card-expiry' ) . ' />
            </p>',
        );

        if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
            $default_fields['card-cvc-field'] = $cvc_field;
        }

        $default_fields['card-holder-field'] = $card_holder_field;

        $fields = wp_parse_args( array(), apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
        ?>

        <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
            <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
            <?php foreach ( $fields as $field ) : ?>
                <?php echo wp_kses( $field, $allowed_fields_html ); ?>
            <?php endforeach; ?>

            <?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>

        <?php 
        if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) : ?>
            <fieldset>
                <?php echo wp_kses( $cvc_field, $allowed_fields_html ); ?>
            </fieldset>
        <?php endif;
}
}
