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


// Bu hook sınıfımı bir WooCommerce ödeme yöntemi olarak kaydeder
add_filter( 'woocommerce_payment_gateways', function ( $gateways ){
	$gateways[] = 'WC_WooMendo_Gateway'; // classımın ismi yazmalı burada 
	return $gateways;
});

add_action('wp_ajax_paymendo_payment_control'		, 'make_payment_control_action' );
add_action('wp_ajax_nopriv_paymendo_payment_control', 'make_payment_control_action' );

function make_payment_control_action(){
	if (!isset($_POST['token'], $_POST['order_id'], $_POST['is_it_paid'] )) {
		wp_send_json( [
			'status' => false,
			'message' => "Must be 'is_it_paid', 'token' and 'order_id'!",
			'info' => null
		]);
	}

	if ($_POST['token'] === get_post_meta( $_POST['order_id'], 'woomendo_paymendo_payment_control_token', true )) {
		# token doğru ve ilgili postmeta kaydı bulunuyor demektir

		$order = wc_get_order( (int)$_POST['order_id'] );  

		if (json_decode($_POST['is_it_paid']) === true) {

			$order->set_status('processing');
			$order->save();

			wp_send_json( [
				'status' => true,
				'message' => 'Order Payment Confirmed',
				'info' => [
					'order_id' => $_POST['order_id'],
					'order_status' => $order->get_status()
				],
			]);
		}

		else if(json_decode($_POST['is_it_paid']) === false){
			wp_send_json( [
				'status' => false,
				'message' => 'Order Payment Not Confirmed',
				'info' => null,
			]);
		}
	}
	wp_send_json( [
		'status' => false,
		'message' => 'Something went wrong',
		'info' => null,
	]);
}