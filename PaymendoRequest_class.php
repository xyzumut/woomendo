<?php
/* 
    yeni requesti ekledim ve sipariş oluşturabiliyorum, login olup olamamayı denemedim henüz
*/
    class PaymendoRequest{

        private $woomendo_access_token;
        private $woomendo_expires_in;
        private $woomendo_refresh_token;

        # Ayarlarda tanımlı olanlar
        private $woomendo_password;
        private $woomendo_mail;
        private $woomendo_base_api_url;
        # Ayarlarda tanımlı olanlar

        const woomedo_login_api_url = '/login';
        const woomedo_payment_api_url = '/api/v2/payment/make';
        const woomedo_order_api_url = '/api/v2/order' ;
        const woomendo_unAuth_payment_api_url = '/payment/make' ;

        public function __construct($woomendo_password , $woomendo_mail , $woomendo_base_api_url ) { 
            $this->woomendo_password = $woomendo_password ;
            $this->woomendo_mail = $woomendo_mail ;
            $this->woomendo_base_api_url = $woomendo_base_api_url ;
            $this->woomendo_access_token = get_option( 'woomendo_access_token' ); # optionlarda böyle bir option bulamazsa boolean false dönüyor
            $this->woomendo_expires_in = get_option( 'woomendo_expires_in' ); # optionlarda böyle bir option bulamazsa boolean false dönüyor
            $this->woomendo_refresh_token = get_option( 'woomendo_refresh_token' ); # optionlarda böyle bir option bulamazsa boolean false dönüyor
        }

        public function getWoomendoAccessToken($refresh){

            if ( $refresh === true || $this->woomendo_expires_in<time() ) {
                $response = $this->loginWithPassword();
                if ($response) {
                    return $this->woomendo_access_token;
                }
                else{
                    throw new Exception(__('There was a problem, try again later(3).', '@1@'));
                }
            }
            
            return $this->woomendo_access_token;
        }

        public function loginWithPassword(){


            $data = (object) [
                'data' => [
                    'attributes' => [
                        'grant_type'=> 'password',
                        'login'=> $this->woomendo_mail,
                        'auth'=> $this->woomendo_password
                    ]
                ]
            ];

           
            $response = $this->requestWoomendo( PaymendoRequest::woomedo_login_api_url, $data);

            // var_dump($response); access token yanlış cevabı geliyor 

            if (isset($response['access_token'])) {
                update_option( 'woomendo_access_token', $response['access_token'] );
                update_option( 'woomendo_expires_in', $response['expires_in']+time() );
                update_option( 'woomendo_refresh_token', $response['refresh_token'] );
                $this->woomendo_access_token = get_option( 'woomendo_access_token' );
                $this->woomendo_refresh_token = get_option( 'woomendo_refresh_token' );
                $this->woomendo_expires_in = get_option( 'woomendo_expires_in' );
                return true;
            }
            
            return false;
        }

        public function createOrder($data, $refresh=false){

            $endpoint_url = PaymendoRequest::woomedo_order_api_url;

            $data = (object) [
                'data' => [
                    'attributes' => [
                        'amount'=>$data['amount'],
                        'notes'=> $data['notes'],
                        'currency_code'=> $data['currency_code']
                    ]
                ]
            ];

            return $this->requestWoomendo( $endpoint_url, $data, $refresh );
        }

        public function makePayment($creditCardData, $refresh=false){

            $endpoint_url = PaymendoRequest::woomedo_payment_api_url;

            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 
            if ($this->woomendo_access_token === false || $this->woomendo_expires_in === false || $this->woomendo_refresh_token === false) {
                $refresh=true;
            }
            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 

            $data = (object) [
                'data' => [
                    'attributes' => [
                        "cc_number"=> $creditCardData['cc_number'],
                        "cc_cvv"=> $creditCardData['cc_cvv'],
                        "cc_exp"=> $creditCardData['cc_exp'],
                        "cc_holder"=> $creditCardData['cc_holder'],
                        "order_id"=> $creditCardData['order_id'],
                        "installment"=> $creditCardData['installment']
                    ]
                ]
            ];


            return $this->requestWoomendo( $endpoint_url, $creditCardData, $refresh );
        }

        public function getOrderToken($order_id){

            
            $endpoint_url = PaymendoRequest::woomedo_order_api_url."/$order_id?include=additional_meta";
            
            $response = $this->requestWoomendo( $endpoint_url, array(), false, 'GET' );
            print_r($response);die;
            
            return $response['included'][$order_id]['data']['meta_value'];

        }

        public function makePaymentWithoutAccessToken($creditCardData, $order_id, $refresh=false){

            $orderToken = $this->getOrderToken($order_id);
            
            // $orderToken='9bd03b83c59bac627079';

            $endpoint_url = PaymendoRequest::woomendo_unAuth_payment_api_url."/$orderToken";

            $data = (object) [
                'data' => [
                    'attributes' => [
                        "cc_number"=> $creditCardData['cc_number'],
                        "cc_cvv"=> $creditCardData['cc_cvv'],
                        "cc_exp"=> $creditCardData['cc_exp'],
                        "cc_holder"=> $creditCardData['cc_holder'],
                        "order_id"=> $creditCardData['order_id'],
                        "installment"=> $creditCardData['installment']
                    ]
                ]
            ];

            return $this->requestWoomendo( $endpoint_url, $data, $refresh );
        }

        public function requestWoomendo($endpoint_url = '', $data = array(), $refresh=false, $method = 'POST'){


            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 
            if ($this->woomendo_access_token === false || $this->woomendo_expires_in === false || $this->woomendo_refresh_token === false) {
                $refresh=true;
            }
            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 

            $body = wp_json_encode($data);
            $headers = array();

            $request_function = 'wp_remote_post';

            $isThisLoginRequest = $endpoint_url === PaymendoRequest::woomedo_login_api_url;

            $base_url = $this->woomendo_base_api_url;



            # Base Urldeki düzeltmeler
            if (substr($base_url,strlen($base_url)-1,1) === '/') {
                $base_url = substr($base_url,0,strlen($base_url)-1);
            }
            if (substr($base_url,0,4) !== 'http') {
                $base_url = 'http://'.$base_url;
            }
            # Base Urldeki düzeltmeler

            $target_url = $base_url.$endpoint_url;

            
            $headers['Content-Type'] = 'application/json';
            
            if ($method==='GET') {
                $request_function = 'wp_remote_get';
            }
            
            # İstek login isteği değilse tokeni ekle
            if (!$isThisLoginRequest && substr($endd,0,14) !== '/payment/make/' ) {
                $headers['Authorization'] = 'Bearer '.$this->getWoomendoAccessToken($refresh);
            }
            # İstek login isteği değilse tokeni ekle



            # Request Optionsı ayarladık
            $request_options = array (
                'headers'   =>  $headers,
                'body'      =>  $body,
                'method'    =>  $method
            );
            # Request Optionsı ayarladık

            /* 
                226 - 228.satırlarda anlamadığım bir şekilde patlıyor
            */

            print_r([
                'endpoint_url' => $endpoint_url,
                'body' => $body,
                'headers' => $headers,
                'request_function' => $request_function,
                'method' => $method,
                'isThisLoginRequest' => $isThisLoginRequest===true ? 'Login' : 'Login Değil', 
                'target_url' => $target_url,
                'request_options' => $request_options
            ]);die;

            # Requesti atıyoruz
            $response = $request_function($target_url, $request_options);
            # Requesti atıyoruz


            $responseStatusCode = wp_remote_retrieve_response_code($response);

            $response =  json_decode( wp_remote_retrieve_body( $response ), true);

            // print_r([
            //     'donusturulmus_response' => $response,
            //     'target_url' => $target_url,
            //     'options' => $request_options,
            //     'method' => $method,
            //     'order_token' => $orderToken
            // ]);


            if ($responseStatusCode>=200 && $responseStatusCode<300) 
                return $response;
            

            if (isset($response['status']) && $response['status'] === false) {
                
                if ($this->getWoomendoAccessToken($refresh) === '') 
                    return $this->requestWoomendo($endpoint_url, $data, true);
                
                throw new Exception(__('Missing information sent.', '@1@'));
            }

            if (isset($response['error']) ) {

                if ($isThisLoginRequest) { # Buraya girdiyse login isteği atılmış ama bilgiler yanlış gitmiş demektir
                    
                    # Auth yani şifre yanlış gönderilmiş
                    if ($response['error'] === 'invalid_grant') 
                        throw new Exception(__('Wrong password.', '@1@'));
                    # Auth yani şifre yanlış gönderilmiş

                    # Auth yani şifre içi boş gönderilmiş
                    if ($response['error'] === 'invalid_request') 
                        throw new Exception(__('The password was sent blank.', '@1@'));
                    
                    # Auth yani şifre içi boş gönderilmiş
                    throw new Exception(__('There was a problem, try again later.(1)', '@1@'));
                }

                if ($refresh) 
                    throw new Exception(__('Doğrulama sağlanamıyor', 'Paymendo'));
                
                return $this->requestWoomendo($endpoint_url, $data, true);
                
            }
            
            throw new Exception(__('An error occurred, base api url may be wrong!', '@1@'));
        }

    }

?>