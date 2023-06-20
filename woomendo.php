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
	
    $post = json_decode(file_get_contents('php://input'), true);

	# Token api tarafındaki token değil process payment içerisinde oluşturup meta options'lara kaydettiğim şahsi tokenim olmalı
	# Order ID'de api tarafındaki id değil wordpress tarafındaki id
	if (!isset($post['token'], $post['order_id'], $post['paid'] )) {

		wp_send_json( [
			'status' => false,
			'message' => "Must be 'paid', 'token' and 'order_id'!",
			'info' => null
		]);
	}

	if ($post['token'] === get_post_meta( $post['order_id'], 'woomendo_paymendo_payment_control_token', true )) {
        # token doğru ve ilgili postmeta kaydı bulunuyor demektir
		
        if (json_decode($post['paid']) === 1) {
            $order = wc_get_order( (int)$post['order_id'] );  
			$order->set_status('processing');
			$order->save();

			wp_send_json( [
				'status' => true,
				'message' => 'Order Payment Confirmed',
				'info' => [
					'order_id' => $post['order_id'],
					'order_status' => $order->get_status()
				],
			]);
		}
		else{
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

add_action('wp_ajax_paymendo_session'		, 'paymendo_session_return' );
add_action('wp_ajax_nopriv_paymendo_session', 'paymendo_session_return' );

function paymendo_session_return(){
	function _return($data = null){
		if ($data === null) {
			wp_send_json([ 
				'status' => false,
				'data' => null
			]);
		}
		wp_send_json([ 
			'status' => true,
			'data' => $data
		]);
	}

	if (isset($_GET['operation'], $_GET['order_id']) && !empty(get_post_meta( $_GET['order_id'], 'woomendo_paymendo_payment_redirect_url', true))){
		
		if ($_GET['operation'] === 'get_id_and_token' && !empty(get_post_meta($_GET['order_id'],'woomendo_paymendo_payment_redirect_url', true)) && !empty(get_post_meta($_GET['order_id'],'woomendo_paymendo_payment_token_in_api', true))) {
			$token_of_api = get_post_meta($_GET['order_id'],'woomendo_paymendo_payment_token_in_api', true);
			$redirect_url = get_post_meta($_GET['order_id'],'woomendo_paymendo_payment_redirect_url', true);
			$target_url_with_token = get_post_meta($_GET['order_id'],'woomendo_paymendo_payment_target_url_with_token', true);
			$order_id_in_api = get_post_meta($_GET['order_id'],'woomendo_paymendo_payment_order_id_in_api', true);

			
			$data = [
				'token_of_api' => $token_of_api,
				'redirect_url' => $redirect_url,
				'target_url_with_token' => $target_url_with_token,
				'order_id_in_api' => $order_id_in_api
			];
			_return($data);
		}
	}
	_return();
}