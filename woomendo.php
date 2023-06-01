<?php
/*
Plugin Name: WooMendo
Description: Extends WooCommerce with an WooMendo gateway.
Version: 1.0
Author: Umut
*/
require (__DIR__).'/creditCard//woomendo_credit_card_class.php';
require (__DIR__).'/PaymendoRequest_class.php';
require (__DIR__).'/WooMendo_init_gateway_class.php';

/* 
	kredi kartı ülke değiştirmedikçe çalışıyor ve kredi kartına girilen değerleride tutabildim
	api ayarlarının girilebilmesi için ödeme filedlarını özelleştirip bunları kullanmayı çözmem gerek
*/


// Bu hook sınıfımı bir WooCommerce ödeme yöntemi olarak kaydeder
add_filter( 'woocommerce_payment_gateways', function ( $gateways ){
	$gateways[] = 'WC_WooMendo_Gateway'; // classımın ismi yazmalı burada 
	return $gateways;
});

add_action('wp_ajax_paymendo_make_payment', 'make_payment_action');
add_action('wp_ajax_nopriv_paymendo_make_payment', 'make_payment_action');

function make_payment_action(){
	$response = array(
        'success' => true,
        'message' => 'Deneme Basarili.'
    );

	wp_send_json( $response );
}
