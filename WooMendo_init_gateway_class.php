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

            # Form fieldleri tanımladığım yer
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->title = $this->get_option( 'title' );
            $this->base_api_url = $this->get_option( 'base_api_url' );
            $this->login_api_url = $this->get_option( 'login_api_url' );
            $this->payment_api_url = $this->get_option( 'payment_api_url' );
            $this->order_api_url = $this->get_option( 'order_api_url' );
            $this->login_mail = $this->get_option( 'login_mail' );
            # Form fieldleri tanımladığım yer
        
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
                'login_api_url' => array(
                    'title'       => __('LOGIN API URL', '@1@'),
                    'type'        => 'text',
                    'description' => __('URL for LOGIN API', '@1@'),
                ),
                'payment_api_url' => array(
                    'title'       => __('PAYMENT API URL', '@1@'),
                    'type'        => 'text',
                    'description' => __('URL for PAYMENT API', '@1@'),
                ),
                'order_api_url' => array(
                    'title'       => __('ORDER API URL', '@1@'),
                    'type'        => 'text',
                    'description' => __('URL for ORDER API', '@1@'),
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
            
            wc_clear_notices();

            global $woocommerce;
            
            if ( !empty($_POST['creditcard_ownerName']) && !empty($_POST['creditcard_cardnumber']) && !empty($_POST['creditcard_expirationdate']) && !empty($_POST['creditcard_securitycode'])) {

                # Güvenlik kodunun 3 hane olup olmadığına bak
                if (strlen($_POST['creditcard_securitycode'])!==3 ){ 
                    wc_add_notice(__('Credit card security number must be 3 digits.', '@1@'), 'error' );
                    return ;
                }
                # Güvenlik kodunun 3 hane olup olmadığına bak

                # Kart numarasının 16 hane olup olmadığına bak, boşluklar ile 19 oluyor
                if (strlen($_POST['creditcard_cardnumber']) !==19  ){ 
                    wc_add_notice(__('Credit card number must be 16 digits.', '@1@'), 'error' );
                    return ;
                }
                # Kart numarasının 16 hane olup olmadığına bak, boşluklar ile 19 oluyor

                # Son kullanma tarihinin formatına bak
                if (strlen($_POST['creditcard_expirationdate'])!==5){
                    wc_add_notice(__('Enter the credit card expiration date correctly.', '@1@'), 'error' );
                    return ;
                }
                # Son kullanma tarihinin formatına bak

                # sipariş bilgilerini aldık
                $order = wc_get_order( $order_id );  
                # sipariş bilgilerini aldık

                # Kredi Kartı Bilgilerini Aldık 
                $woomendo_card_holder = $_POST['creditcard_ownerName'] ;
                $woomendo_card_number = $_POST['creditcard_cardnumber'] ;
                $woomendo_card_expDate = $_POST['creditcard_expirationdate'] ;
                $woomendo_card_securityCode = $_POST['creditcard_securitycode'] ;
                # Kredi Kartı Bilgilerini Aldık 

                # Burada faturayı oluşturacağız ve ödeyeceğiz @@@@@@@@@@@@@@@@@@@ 
                if (true) {
                    /* 
                        pending: Sipariş henüz işlenmemiş durumda.
                        on-hold: Sipariş, müşteriye gönderilmeden önce bekletiliyor.
                        processing: Sipariş, işleme aşamasında, ödeme onayı vb. bekleniyor.
                        completed: Sipariş başarıyla tamamlandı.
                        cancelled: Sipariş müşteri veya yönetici tarafından iptal edildi.
                        refunded: Siparişin tamamı iade edildi.
                        failed: Siparişin ödeme işlemi başarısız oldu.


                        $order->payment_complete()  processing moduna alıyor
                    */
                    $order->payment_complete();
                    $order->reduce_order_stock();
                    $order->add_order_note( 'Siparişiniz alındı teşekkürler.', true );
                    $woocommerce->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
                # Burada faturayı oluşturacağız ve ödeyeceğiz @@@@@@@@@@@@@@@@@@@
            }

            # Kredi kartı bilgilerinden herhangi birisi boş ise uyarı ver
            else if (empty($_POST['creditcard_ownerName']) || empty($_POST['creditcard_cardnumber']) || empty($_POST['creditcard_expirationdate']) || empty($_POST['creditcard_securitycode'])) {
                wc_add_notice(__('Credit card information cannot be empty', '@1@'), 'error' );
                return ;
            }
            # Kredi kartı bilgilerinden herhangi birisi boş ise uyarı ver
            
            wc_add_notice(  'Bir hata oluştu, lütfen tekrar deneyin', 'error' );
            return;
        }
    }
?>