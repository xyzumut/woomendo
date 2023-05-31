<?php

    class PaymendoRequest{

        private $woomendo_is_login;

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

        public function __construct($woomendo_password ,$woomendo_mail ,$woomendo_base_api_url ,$woomedo_login_api_url ,$woomedo_payment_api_url ,$woomedo_order_api_url) { 
            $this->woomendo_password = $woomendo_password;
            $this->woomendo_mail = $woomendo_mail;
            $this->woomendo_base_api_url = $woomendo_base_api_url;
            $this->woomedo_login_api_url = $woomedo_login_api_url;
            $this->woomedo_payment_api_url = $woomedo_payment_api_url;
            $this->woomedo_order_api_url = $woomedo_order_api_url;
        }

        public function loginWithPassword(){
            
            $url = $this->woomendo_base_api_url.$this->woomedo_login_api_url;
            
            $data = (object) [
                'data' => [
                    'attributes' => [
                        'grant_type'=> 'password',
                        'login'=> $this->woomendo_mail,
                        'auth'=> $this->woomendo_password
                    ]
                ]
            ];
            
            $response = wp_remote_post( $url, array(
                'method'      => 'POST',
                'body'        => $data
                )
            );

            # herhangi birini(grant_type, login ve auth) hiç göndermediğimde "status" => "false" dönüyor
            # mail boş veya yanlış oldugundada "status" => "false" 
            # grant type boş olduğunda "status" => "false"
            # authu yanlış(ama boş değil) gönderince "error" ve "error_description" dönüyor."error" =>  "invalid_grant"  ve "error_description" => "Invalid username and password combination"
            # authu         boş           gönderince "error" ve "error_description" dönüyor."error" => "invalid_request" ve "error_description" => "Missing parameters. \"username\" and \"password\" required"

            if (is_wp_error( $response )) {
                throw new Exception(__('There was a problem, try again later.', '@1@'));
            }

            $responseStatusCode = wp_remote_retrieve_response_code($response);
            $response =  json_decode( wp_remote_retrieve_body( $response ), true);

            # Başarılı Cevap
            if ($status_code>=200 && $status_code<300) {
                update_option( 'woomendo_access_token', $response['access_token'] );
                update_option( 'woomendo_expires_in', $response['expires_in']+time() );
                update_option( 'woomendo_refresh_token', $response['refresh_token'] );
                update_option( 'woomendo_is_login', 'true' );
                $this->woomendo_is_login = true;
                $this->$woomendo_access_token = $response['access_token'];
                $this->$woomendo_refresh_token = $response['refresh_token'];
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

            throw new Exception(__('There was a problem, try again later.', '@1@'));
        }

        public function getWoomendoAccessToken(){



        }

        public function createOrder($url, $data){

            $data = (object) [
                'data' => [
                    'attributes' => [
                        'amount'=>$data['amount'],
                        'notes'=> $data['notes'],
                        'currency_code'=> $data['currency_code']
                    ]
                ]
            ];

            $response = wp_remote_post( $url, $data );

            if (is_wp_error( $response )) {
                throw new Exception(__('There was a problem, try again later.', '@1@'));
            }

            $responseStatusCode = wp_remote_retrieve_response_code($response);
            $response =  json_decode( wp_remote_retrieve_body( $response ), true);
            
            if ($status_code>=200 && $status_code<300) {
                return $response;
            }

            if ($status_code === 401 || (isset($response['error']) && $response['error'] === 'invalid_grant')) {
                if($loginSwitch)
                    throw new Exception(__('The information entered was incorrect, so the login failed.', 'Paymendo'));

                if($refresh)
                    throw new Exception(__('Authentication error, please sign in again.', 'Paymendo'));
                
                return $this->createOrder($url, $data);
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