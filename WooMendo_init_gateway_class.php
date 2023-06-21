<?php

    class WC_WooMendo_Gateway extends WC_Payment_Gateway {

        private CreditCard $creditCard;
        private PaymendoRequest $paymendoRequest;

        public function __construct() {

            $this->id = 'woomendo'; 
            $this->icon = ''; 
            $this->has_fields = true; 
            $this->method_title = 'WooMendo';
            $this->method_description = __('Make payments via Woomendo.', 'WooMendo'); 
        
            $this->creditCard = new CreditCard(); 

            $this->supports = array(
                'products'
            );
        
            $this->init_form_fields();
        
            $this->init_settings();
 
            # Form fieldleri tanımladığım yer
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->title = $this->get_option( 'title' );
            $this->base_api_url = $this->get_option( 'base_api_url' );
            $this->login_mail = $this->get_option( 'login_mail' );
            $this->login_password = $this->get_option( 'login_password' );
            # Form fieldleri tanımladığım yer

            # PaymendoRequest nesnesine başka bir yerden erişmek istersem diye optionlara kendimde kaydediyorum(payment ajax gibi)
            update_option( 'login_password', $this->login_password );
            update_option( 'login_mail', $this->login_mail );
            update_option( 'base_api_url', $this->base_api_url );
            # PaymendoRequest nesnesine başka bir yerden erişmek istersem diye optionlara kendimde kaydediyorum(payment ajax gibi)

            $this->paymendoRequest = new PaymendoRequest($this->login_password, $this->login_mail, $this->base_api_url);
            
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable / Disable', 'WooMendo'),
                    'label'       => __('Enable WooMendo Gateway', 'WooMendo'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __('Title', 'WooMendo'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'WooMendo'),
                    'default'     => 'WooMendo',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'WooMendo'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'WooMendo'),
                    'default'     => __('Make your payment quickly and reliably on paymendo.', 'WooMendo'),
                ),
                'login_mail' => array(
                    'title'       => __('Login e-mail', 'WooMendo'),
                    'type'        => 'text',
                    'description' => __('E-mail for Login', 'WooMendo'),
                ),
                'login_password' => array(
                    'title'       => __('Login password', 'WooMendo'),
                    'type'        => 'text',
                    'description' => __('Password for Login', 'WooMendo'),
                ),
                'base_api_url' => array(
                    'title'       => __('BASE API URL', 'WooMendo'),
                    'type'        => 'text',
                    'description' => __('Base URL for API', 'WooMendo'),
                ),
            );
        }

        public function payment_fields() {

            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }

            echo $this->creditCard->renderCreditCard();
            
        }
        
        public function payment_scripts() {
            wp_enqueue_script( 'woomendo_script', plugin_dir_url( __FILE__ ).'creditCard/woomendo.js', array(), '', true);
            wp_localize_script( 'woomendo_script', 'woomendo_script', ['admin_url' => get_admin_url()] );

            wp_enqueue_script( 'woomendo_payment_credit_card_script', plugin_dir_url( __FILE__ ).'creditCard/woomendo_credit_card.js', array(), '', true);
            wp_enqueue_style( 'woomendo_style', plugin_dir_url( __FILE__ ).'creditCard/woomendo_credit_card.css');
        }
        
        public function validate_fields(){}
        
        public function process_payment( $order_id ) {

            global $woocommerce;
            
            # sipariş bilgilerini aldık
            $order = wc_get_order( $order_id );  
            # sipariş bilgilerini aldık

            try{

                # Benim oluşturduğum token
                if (empty(get_post_meta( $order_id, 'woomendo_paymendo_payment_control_token', true))) {
                    $token = substr(uniqid(), 0, 16);
                    $order->reduce_order_stock();
                    update_post_meta( $order_id, 'woomendo_paymendo_payment_control_token', $token);
                }
                # Benim oluşturduğum token

                #  Burada api tarafında siparişi oluşturup akabinde wordpress tarafında oluşan siparişin durumu 'beklemede' moduna alır ve stoktan düşer
                $callback_admin = get_admin_url();//http://localhost/wp/wp-admin/;
                $my_comment = json_encode(["order_woocommerce_id" => $order_id, "unatuh_payment_control_token_for_paymendo_api" => get_post_meta( $order_id, "woomendo_paymendo_payment_control_token", true), "callback" => $callback_admin.'admin-ajax.php?action=paymendo_payment_control']);
                $currenyCode = 'TRY' ;
                $amount = $order->get_total(); # Total fiyatı verir 
                $create_order_response = $this->paymendoRequest->createOrder(array('amount' => $amount, 'notes' => $my_comment, 'currency_code' => $currenyCode));
                $order_api_id = $create_order_response['data']['id']; # Siparişin api tarafındaki id'si
                #  Burada api tarafında siparişi oluşturup akabinde wordpress tarafında oluşan siparişin durumu 'beklemede' moduna alır ve stoktan düşer

                $order_token = $this->paymendoRequest->getOrderToken($order_api_id);
                
            }
            catch (Exception $error){
                wc_add_notice($error->getMessage(), 'error' );
                return ;
            }

            $base_url = $this->paymendoRequest->get_woomendo_base_api_url();

            # Base Urldeki düzeltmeler
            if (substr($base_url,strlen($base_url)-1,1) === '/') {
                $base_url = substr($base_url,0,strlen($base_url)-1);
            }
            if (substr($base_url,0,4) !== 'http') {
                $base_url = 'https://'.$base_url;
            }
            # Base Urldeki düzeltmeler

            $redirect_url = $this->get_return_url( $order );
            $target_url_with_token = $base_url.PaymendoRequest::woomendo_unAuth_payment_api_url."/$order_token";

            if (empty(get_post_meta( $order_id, 'woomendo_paymendo_payment_redirect_url', true))) {
                $token = substr(uniqid(), 0, 16);
                $order->reduce_order_stock();
                update_post_meta( $order_id, 'woomendo_paymendo_payment_redirect_url', $redirect_url);
                update_post_meta( $order_id, 'woomendo_paymendo_payment_token_in_api', $order_token);
                update_post_meta( $order_id, 'woomendo_paymendo_payment_target_url_with_token', $target_url_with_token);
                update_post_meta( $order_id, 'woomendo_paymendo_payment_order_id_in_api', $order_api_id);
            }

            $return = array('result' => 'success');

            $ajax_ = [
                'order_id_in_api' => $order_api_id ,
                'target_url_with_token' => $target_url_with_token ,
                'redirect_url' => $redirect_url
            ];

            $return['ajax_datas'] = $ajax_;    
        
            add_filter('woocommerce_payment_successful_result', function ($result, $order_id){
                $result['messages'] = '<div id="woomendo_notice_container"> <div id="woomendo_first_notice">İşleminiz Devam Etmekte...</div> </div>';
                return $result;  
            }, 10, 2);

            return $return;
        }
    }
?>