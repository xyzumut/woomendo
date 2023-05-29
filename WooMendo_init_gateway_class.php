<?php


class WC_WooMendo_Gateway extends WC_Payment_Gateway {

    private CreditCard $creditCard;
 		
    public function __construct() {

        $this->id = 'woomendo'; 
        $this->icon = ''; 
        $this->has_fields = true; 
        $this->method_title = 'WooMendo';
        $this->method_description = __('Make payments via Woomendo.', '@1@'); 
    
        $this->creditCard = new CreditCard(); 

        $this->supports = array(
            'products'
        );
    
        $this->init_form_fields();
    
        $this->init_settings();
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->title = $this->get_option( 'title' );

    
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        
     }

     public function init_form_fields(){

        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable / Disable', 'text_domain_woomendo'),
                'label'       => __('Enable WooMendo Gateway', 'text_domain_woomendo'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'WooMendo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Make your payment quickly and reliably on paymendo.',
            ),
        );
    }

    public function payment_fields() {

        if ( $this->description ) {
            echo wpautop( wp_kses_post( $this->description ) );
        }
        

        echo 'Kart';
        echo $this->creditCard->renderCreditCard();
        /* 
        
        Burada Kartı Çekeceğim
        
        */
     
    }
    
    public function payment_scripts() {

    }
    
    public function validate_fields(){

    }
    
    public function process_payment( $order_id ) {
        global $woocommerce;
        $order = wc_get_order( $order_id ); // sipariş bilgilerini aldık
        if (!true) {
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note( 'Siparişiniz alındı teşekkürler.', true );
            $woocommerce->cart->empty_cart();
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }
        else{
            wc_add_notice(  'Bir hata oluştu, lütfen tekrar deneyin', 'error' );
            return;
        }
    }
}
?>