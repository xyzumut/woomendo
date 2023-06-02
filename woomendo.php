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
	
	$creditCardData = array(
		'cc_number' => $_GET['woomendo_card_number'],
		'cc_cvv' => $_GET['woomendo_card_securityCode'],
		'cc_exp' => $_GET['woomendo_card_expDate'],
		'cc_holder' => $_GET['woomendo_card_holder'] ,
		'order_id' => $_GET['order_api_id'],
		'installment' => '1'
	);

	$paymendo_request = new PaymendoRequest(get_option( 'login_password' ), get_option( 'login_mail' ), get_option( 'base_api_url' ));
	
	try{

		$paymentResponse = $paymendo_request->makePayment($creditCardData);
		// wp_send_json(array(
		// 	'message' => $paymentResponse,
		// ));
		if (isset($paymentResponse['status']) && $paymentResponse['status'] === true) {
			$form = $paymentResponse['data']['attributes']['form'];
			$form = html_entity_decode($form);
			wp_send_json (array(
				'message' => 'Istek Basarili.',
				'form'    => $form,
				'state'   => '1',
				'res' => $paymentResponse['status']===true
			));
		}
		else {
			wp_send_json(array(
				'message' => 'Istek Basarisiz.',
				'form'    => null,
				'state'   => '0',
			));
		}
	}
	catch (Excepiton $error){
		wp_send_json(array(
			'message' => 'Bir hata oluştu lütfen daha sonra tekrar deneyin',
			'form'    => null,
			'state'   => '-1',
		));
	}
}
