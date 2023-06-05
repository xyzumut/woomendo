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
        
        public function payment_scripts() {

        }
        
        public function validate_fields(){

        }
        
        public function process_payment( $order_id ) {
            
            // wc_clear_notices();
            global $woocommerce;

            if ( !empty($_POST['creditcard_ownerName']) && !empty($_POST['creditcard_cardnumber']) && !empty($_POST['creditcard_expirationdate']) && !empty($_POST['creditcard_securitycode'])) {
                

                # Kredi Kartı Bilgilerini Aldık 
                $woomendo_card_holder = $_POST['creditcard_ownerName'] ;
                $woomendo_card_number = $_POST['creditcard_cardnumber'] ;
                $woomendo_card_expDate = $_POST['creditcard_expirationdate'] ;
                $woomendo_card_securityCode = $_POST['creditcard_securitycode'] ;
                # Kredi Kartı Bilgilerini Aldık 
                
                # Güvenlik kodunun 3 hane olup olmadığına bak
                if (strlen($woomendo_card_securityCode)!==3 ){ 
                    wc_add_notice(__('Credit card security number must be 3 digits.', '@1@'), 'error' );
                    return ;
                }
                # Güvenlik kodunun 3 hane olup olmadığına bak

                # Kart numarasının 16 hane olup olmadığına ve tamamen sayılardan oluşup oluşmadığına bak
                $woomendo_card_number = implode('', explode(' ', $woomendo_card_number)); # boşlukları ayıkladık
            
                $characters = str_split($woomendo_card_number); # diziye çevirdik
                
                foreach ($characters as $char) {
                    if (!is_numeric($char)) {
                        wc_add_notice(__('Credit card number must be numbers only.', '@1@'), 'error' );
                        return ;
                    }
                }
            
                if (strlen($woomendo_card_number) !== 16) {
                    wc_add_notice(__('Credit card number must be 16 digits.', '@1@'), 'error' );
                    return ;
                }
                # Kart numarasının 16 hane olup olmadığına ve tamamen sayılardan oluşup oluşmadığına bak

                # Son kullanma tarihinin formatına bak
                if (strlen($woomendo_card_expDate)!==5 || $woomendo_card_expDate[2]!=='/'){ 
                    wc_add_notice(__('credit card expiration date format is incorrect.', '@1@'), 'error' );
                    return ;
                }

                $month = explode('/', $woomendo_card_expDate)[0];

                if (intval($month)>12){
                    wc_add_notice(__('Month value cannot be greater than 12.', '@1@'), 'error' );
                    return ;
                }
                # Son kullanma tarihinin formatına bak

                # sipariş bilgilerini aldık
                $order = wc_get_order( $order_id );  
                # sipariş bilgilerini aldık

                try{

                    $order->set_status('pending');
                    $order->reduce_order_stock();
                    $order->save();

                    #  Burada api tarafında siparişi oluşturup akabinde wordpress tarafında oluşan siparişin durumu 'beklemede' moduna alır ve stoktan düşer
                    $order_comments = $_POST['order_comments'] ; # not
                    $currenyCode = 'TRY' ;
                    $amount = $order->get_total(); # Total fiyatı verir 
                    $create_order_response = $this->paymendoRequest->createOrder(array('amount' => $amount, 'notes' => $order_comments, 'currency_code' => $currenyCode));
                    $order_api_id = $create_order_response['data']['id']; # Siparişin api tarafındaki id'si
                    #  Burada api tarafında siparişi oluşturup akabinde wordpress tarafında oluşan siparişin durumu 'beklemede' moduna alır ve stoktan düşer
                    
                    $order_token = $this->paymendoRequest->getOrderToken($order_api_id);

                }
                catch (Exception $error){
                    die('1');
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
                    'credit_card_datas' => [
                        'woomendo_card_holder' => $woomendo_card_holder,
                        'woomendo_card_number' => $woomendo_card_number,
                        'woomendo_card_expDate' => $woomendo_card_expDate,
                        'woomendo_card_securityCode' => $woomendo_card_securityCode
                    ],
                    'order_id_in_api' => $order_api_id ,
                    'order_id_in_woocommerce' => $order_id ,
                    'amount' => $amount,
                    'target_url_with_token' => $base_url.PaymendoRequest::woomendo_unAuth_payment_api_url."/$order_token"
                ];

                return array(
                    'result' => 'success',
                    'ajax_datas' => $ajax_
                );
            }

            # Kredi kartı bilgilerinden herhangi birisi boş ise uyarı ver
            else if (empty($_POST['creditcard_ownerName']) || empty($_POST['creditcard_cardnumber']) || empty($_POST['creditcard_expirationdate']) || empty($_POST['creditcard_securitycode'])) {
                wc_add_notice(__('Credit card information cannot be empty', '@1@'), 'error' );
                return ;
            }
            # Kredi kartı bilgilerinden herhangi birisi boş ise uyarı ver
            
            // wc_add_notice(  'Bir hata oluştu, lütfen tekrar deneyin', 'error' );
            // return;
        }
    }
?>