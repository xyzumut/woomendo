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

add_action('wp_ajax_paymendo_make_payment', 'make_payment_action');
add_action('wp_ajax_nopriv_paymendo_make_payment', 'make_payment_action');

function make_payment_function(){
	die('x1');
}


add_action('wp_ajax_paymendo_payment_control'		, 'make_payment_control_action' );
add_action('wp_ajax_nopriv_paymendo_payment_control', 'make_payment_control_action' );
//http://localhost/wp/wp-admin/admin-ajax.php?action=paymendo_payment_control&token=
function make_payment_control_action(){
	if (isset($_POST['token'], $_POST['order_id'] )) {
		
	}
	wp_send_json( [
		'status' => false,
		'message' => 'Must be "token" and "order_id"!'
	]);
}