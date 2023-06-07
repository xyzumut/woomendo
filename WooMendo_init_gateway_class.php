<?php
    class WC_WooMendo_Gateway extends WC_Payment_Gateway {

        private CreditCard $creditCard;
        private PaymendoRequest $paymendoRequest;

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
                    'title'       => __('Enable / Disable', '@1@'),
                    'label'       => __('Enable WooMendo Gateway', '@1@'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __('Title', '@1@'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', '@1@'),
                    'default'     => 'WooMendo',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', '@1@'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', '@1@'),
                    'default'     => __('Make your payment quickly and reliably on paymendo.', '@1@'),
                ),
                'login_mail' => array(
                    'title'       => __('Login e-mail', '@1@'),
                    'type'        => 'text',
                    'description' => __('E-mail for Login', '@1@'),
                ),
                'login_password' => array(
                    'title'       => __('Login password', '@1@'),
                    'type'        => 'text',
                    'description' => __('Password for Login', '@1@'),
                ),
                'base_api_url' => array(
                    'title'       => __('BASE API URL', '@1@'),
                    'type'        => 'text',
                    'description' => __('Base URL for API', '@1@'),
                ),
            );
        }

        public function payment_fields() {

            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }

            echo $this->creditCard->renderCreditCard();
        }
        
        public function payment_scripts() {}
        
        public function validate_fields(){}
        
        public function process_payment( $order_id ) {

            // global $woocommerce;
            
            # sipariş bilgilerini aldık
            $order = wc_get_order( $order_id );  
            # sipariş bilgilerini aldık

            try{

                if (empty(get_post_meta( $order_id, 'woomendo_paymendo_payment_control_token', true))) {
                    $token = substr(uniqid(), 0, 16);
                    $order->reduce_order_stock();
                    update_post_meta( $order_id, 'woomendo_paymendo_payment_control_token', $token);
                }

                #  Burada api tarafında siparişi oluşturup akabinde wordpress tarafında oluşan siparişin durumu 'beklemede' moduna alır ve stoktan düşer
                $my_comment = json_encode(["order_woocommerce_id" => $order_id, "unatuh_payment_control_token_for_paymendo_api" => get_post_meta( $order_id, "woomendo_paymendo_payment_control_token", true), "callback" => "http://localhost/wp/wp-admin/admin-ajax.php?action=paymendo_payment_control"]);
                $currenyCode = 'TRY' ;
                $amount = $order->get_total(); # Total fiyatı verir 
                $create_order_response = $this->paymendoRequest->createOrder(array('amount' => $amount, 'notes' => $my_comment, 'currency_code' => $currenyCode));
                $order_api_id = $create_order_response['data']['id']; # Siparişin api tarafındaki id'si
                #  Burada api tarafında siparişi oluşturup akabinde wordpress tarafında oluşan siparişin durumu 'beklemede' moduna alır ve stoktan düşer

                $order_token = $this->paymendoRequest->getOrderToken($order_api_id);//postmetada sakla bunu, ordser token yoksa stoktan düş yoksa
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
                $base_url = 'http://'.$base_url;
            }
            # Base Urldeki düzeltmeler

            $ajax_ = [
                'order_id_in_api' => $order_api_id ,
                'target_url_with_token' => $base_url.PaymendoRequest::woomendo_unAuth_payment_api_url."/$order_token",
                'redirect_url' => $this->get_return_url( $order )
            ];

            add_filter('woocommerce_payment_successful_result', function ($result, $order_id){
                $result['result'] = 'failure';
                $result['messages'] = /*html*/'<div id="woomendo_notice_container"> <div id="woomendo_first_notice">İşleminiz Devam Etmekte...</div> </div>';
                return $result;  
            }, 10, 2);

            return array(
                'result' => 'success',
                'ajax_datas' => $ajax_
            );
        }
    }
?>