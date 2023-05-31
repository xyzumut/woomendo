<?php

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

        public function createOrder($url, $data, $refresh=false){
            
            $headers = array();
            $body = array();

            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 
            if ($this->woomendo_access_token === false || $this->woomendo_expires_in === false || $this->woomendo_refresh_token === false) {
                $refresh=true;
            }
            # Bu if eğer access/refresh tokenlar veya expires_in silinmiş ise veya ilk kez istek atılıyorsa logini tetiklemek için 

            $body = (object) [
                'data' => [
                    'attributes' => [
                        'amount'=>$data['amount'],
                        'notes'=> $data['notes'],
                        'currency_code'=> $data['currency_code']
                    ]
                ]
            ];

            $headers['Content-Type']    =   'application/json';
            $headers['Authorization']   =   'Bearer '.$this->getWoomendoAccessToken($refresh);

            $request_options = array (
                'body'      =>  wp_json_encode($body),
                'headers'   =>  $headers,
            );

            ########################################################## Bu kısmı daha sonra tümleşik tek bir fonksiyonda yapacağım ##########################################################
            $response = wp_remote_post( $url, $request_options );

            if (is_wp_error( $response )) {
                throw new Exception(__('There was a problem, try again later.(4)', '@1@'));
            }

            $responseStatusCode = wp_remote_retrieve_response_code($response);
            $response =  json_decode( wp_remote_retrieve_body( $response ), true);

            if ($responseStatusCode>=200 && $responseStatusCode<300 && isset($response['status']) && $response['status'] == 'true') {
                /* Dönen json
                    {
                        "status": true,
                        "meta": [],
                        "links": {
                            "self": "https://api.tahsilatmerkezi.com/api/v2/order/282"
                        },
                        "data": {
                            "type": "Order",
                            "id": 282,
                            "attributes": {
                                "id": 282,
                                "order_num": "PYM5343938257",
                                "amount": 100.0,
                                "balance": 100.0,
                                "currency_code": "TRY",
                                "currency_rate": "1",
                                "notes": "25.05.2023 ilk oluşturulan fatura",
                                "due_date": "2023-06-15T14:23:31+03:00",
                                "created": "2023-05-31T14:23:31+03:00",
                                "updated": "2023-05-31T14:23:31+03:00"
                            },
                            "relationships": []
                        },
                        "included": []
                    }
                */
                return $response;
            }

            # amountu boş veya hiç göndermeyince ve currency_code değerini hiç göndermesem "status": false ve "errors" veriyor, currency'i boş göndersem kabul ediyor
            # tokenı yanlış gönderince "error": "invalid_grant" ve "error_description": "The access token provided is invalid." dönüyor
            
            if ($responseStatusCode === 401 || (isset($response['error']) && $response['error'] === 'invalid_grant')) {

                // if($loginSwitch)
                //     throw new Exception(__('The information entered was incorrect, so the login failed.', 'Paymendo'));

                // if($refresh)
                //     throw new Exception(__('Authentication error, please sign in again.', 'Paymendo'));
                if ($refresh) {
                    throw new Exception(__('Doğrulama sağlanamıyor', 'Paymendo'));
                }
                return $this->createOrder($url, $data, true);
            }
            ########################################################## Bu kısmı daha sonra tümleşik tek bir fonksiyonda yapacağım ##########################################################
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