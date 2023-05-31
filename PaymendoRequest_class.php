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
        private $woomedo_login_api_url;
        private $woomedo_payment_api_url;
        private $woomedo_order_api_url;
        # Ayarlarda tanımlı olanlar

        public function __construct($woomendo_password, $woomendo_mail, $woomendo_base_api_url, $woomedo_login_api_url, $woomedo_payment_api_url, $woomedo_order_api_url) { 
            $this->woomendo_password = $woomendo_password ;
            $this->woomendo_mail = $woomendo_mail ;
            $this->woomendo_base_api_url = $woomendo_base_api_url ;
            $this->woomedo_login_api_url = $woomedo_login_api_url ;
            $this->woomedo_payment_api_url = $woomedo_payment_api_url ;
            $this->woomedo_order_api_url = $woomedo_order_api_url ;
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

            $body = array();
            $headers = array();
           
            $url = $this->woomendo_base_api_url.$this->woomedo_login_api_url;

            $headers['Content-Type'] = 'application/json';

            $body = (object) [
                'data' => [
                    'attributes' => [
                        'grant_type'=> 'password',
                        'login'=> $this->woomendo_mail,
                        'auth'=> $this->woomendo_password
                    ]
                ]
            ];

            $request_options = array (
                'method'    => 'POST',
                'body'      =>  wp_json_encode($body),
                'headers'   =>  $headers
            );

            $response = wp_remote_post( $url, $request_options);

            # herhangi birini(grant_type, login ve auth) hiç göndermediğimde "status" => "false" dönüyor
            # mail boş veya yanlış oldugundada "status" => "false" 
            # grant type boş olduğunda "status" => "false"
            # authu yanlış(ama boş değil) gönderince "error" ve "error_description" dönüyor."error" =>  "invalid_grant"  ve "error_description" => "Invalid username and password combination"
            # authu          boş          gönderince "error" ve "error_description" dönüyor."error" => "invalid_request" ve "error_description" => "Missing parameters. \"username\" and \"password\" required"

            if (is_wp_error( $response )) {
                throw new Exception(__('There was a problem, try again later.(1)', '@1@'));
            }

            $responseStatusCode = wp_remote_retrieve_response_code($response);
            $response =  json_decode( wp_remote_retrieve_body( $response ), true);

            # Başarılı Cevap
            if ($responseStatusCode >= 200 && $responseStatusCode < 300) {

                update_option( 'woomendo_access_token', $response['access_token'] );
                update_option( 'woomendo_expires_in', $response['expires_in']+time() );
                update_option( 'woomendo_refresh_token', $response['refresh_token'] );
                $this->woomendo_access_token = $response['access_token'];
                $this->woomendo_refresh_token = $response['refresh_token'];
                $this->woomendo_expires_in = get_option( 'woomendo_expires_in' );
                return true;
            }
            # Başarılı Cevap


            # Bilgiler eksik gönderildi ise burası çalışacak
            if (isset($response['status']) && $response['status'] === 'false') {
                throw new Exception(__('Missing information sent.', '@1@'));
            }
            # Bilgiler eksik gönderildi ise burası çalışacak

            # authta hata var ise burası çalışacak 
            if (isset($response['error']) && isset($response['error_description'])) {

                # Auth yani şifre yanlış gönderilmiş
                if ($response['error'] === 'invalid_grant') {
                    throw new Exception(__('Wrong password.', '@1@'));
                }
                # Auth yani şifre yanlış gönderilmiş

                # Auth yani şifre içi boş gönderilmiş
                if ($response['error'] === 'invalid_request') {
                    throw new Exception(__('The password was sent blank.', '@1@'));
                }
                # Auth yani şifre içi boş gönderilmiş

            }
            # authta hata var ise burası çalışacak

            throw new Exception(__('There was a problem, try again later.(2)', '@1@'));
        }



        public function createOrder($url, $data, $refresh=false){
            
            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 
            if ($this->woomendo_access_token === false || $this->woomendo_expires_in === false || $this->woomendo_refresh_token === false) {
                $refresh=true;
            }
            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 

            $data = (object) [
                'data' => [
                    'attributes' => [
                        'amount'=>$data['amount'],
                        'notes'=> $data['notes'],
                        'currency_code'=> $data['currency_code']
                    ]
                ]
            ];

            return $this->requestWoomendo( $url, $data, $refresh );
        }

        public function requestWoomendo($url = '', $data = array(), $refresh=false, $method = 'POST'){
            
            $body = array();
            $headers = array();
            $request_function = 'wp_remote_post';
            $isThisLoginRequest = $url === $woomendo_base_api_url.$woomedo_login_api_url;

            $headers['Content-Type'] = 'application/json';


            if ($method==='GET') {
                $request_function = 'wp_remote_get';
            }

            # İstek login isteği değilse tokeni ekle
            if (!$isThisLoginRequest) {
                $headers['Authorization'] = 'Bearer '.$this->getWoomendoAccessToken($refresh);
            }
            # İstek login isteği değilse tokeni ekle

            # Request Optionsı ayarladık
            $request_options = array (
                'body'      =>  wp_json_encode($data),
                'headers'   =>  $headers,
            );

            $response = $request_function($url, (object)$request_options);

            $responseStatusCode = wp_remote_retrieve_response_code($response);
            $response =  json_decode( wp_remote_retrieve_body( $response ), true);
            if (is_wp_error( $response )) {
                throw new Exception(__('There was a problem, try again later.', '@1@'));
            }

            if ($responseStatusCode>=200 && $responseStatusCode<300) {
                return $response;
            }

            if (isset($response['status']) && $response['status'] === 'false') {
                throw new Exception(__('Missing information sent.', '@1@'));
            }

            if (isset($response['error']) ) {
                if ($isThisLoginRequest) { # Buraya girdiyse login isteği atılmış ama bilgiler yanlış gitmiş demektir
                    # Auth yani şifre yanlış gönderilmiş
                    if ($response['error'] === 'invalid_grant') {
                        throw new Exception(__('Wrong password.', '@1@'));
                    }
                    # Auth yani şifre yanlış gönderilmiş

                    # Auth yani şifre içi boş gönderilmiş
                    if ($response['error'] === 'invalid_request') {
                        throw new Exception(__('The password was sent blank.', '@1@'));
                    }
                    # Auth yani şifre içi boş gönderilmiş
                    throw new Exception(__('There was a problem, try again later.', '@1@'));
                }
                if ($refresh) {
                    throw new Exception(__('Doğrulama sağlanamıyor', 'Paymendo'));
                }
                return $this->requestWoomendo($url, $body, true);
            }
        }

    }
    // $response = wp_remote_post( $url, array(
    //     'method'      => 'POST',
    //     'timeout'     => 45,
    //     'redirection' => 5,
    //     'httpversion' => '1.0',
    //     'blocking'    => true,
    //     'headers'     => array(),
    //     'body'        => array(
    //         'username' => 'bob',
    //         'password' => '1234xyz'
    //     ),
    //     'cookies'     => array()
    //     )
    // );
?>