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

	global $woocommerce;
	$order_woo_commerce_id = $_GET['order_woocommerce_id'];

	
	$creditCardData = array(
		'cc_number' => $_GET['woomendo_card_number'],
		'cc_cvv' => $_GET['woomendo_card_securityCode'],
		'cc_exp' => $_GET['woomendo_card_expDate'],
		'cc_holder' => $_GET['woomendo_card_holder'] ,
		'order_id' => $_GET['order_api_id'],
		'installment' => '1'
	);
	// $creditCardData = array(
	// 	'cc_number' => '5571135571135571',
	// 	'cc_cvv' => '000',
	// 	'cc_exp' => '12/26',
	// 	'cc_holder' => 'umut gedik' ,
	// 	'order_id' => '406' ,
	// 	'installment' => '1'
	// );
	$paymendo_request = new PaymendoRequest(get_option('login_password'), get_option('login_mail'), get_option('base_api_url'));

	$payment_response = $paymendo_request->makePaymentWithoutAccessToken($creditCardData, $_GET['order_api_id']);

	
	// $form = $payment_response['data']['attributes']['form'];
	// $form = html_entity_decode($form);
	// var_dump($payment_response);


}
