<?php
/*
Plugin Name: WooMendo
Description: Extends WooCommerce with an WooMendo gateway.
Version: 1.0
Author: Umut
*/
require (__DIR__).'/creditCard//woomendo_credit_card.php';
require (__DIR__).'/WooMendo_init_gateway_class.php';


// Bu kanca sınıfımı bir WooCommerce ödeme yöntemi olarak kaydeder
add_filter( 'woocommerce_payment_gateways', function ( $gateways ){
	$gateways[] = 'WC_WooMendo_Gateway'; // classımın ismi yazmalı burada 
	return $gateways;
});

add_action( 'plugins_loaded', function() {
    $woomendo = new WC_WooMendo_Gateway();
});

// add_action( 'init', function () {
// 	wp_enqueue_script( 'woomendo_page_admin_script', plugin_dir_url( __FILE__ ).'creditCard/woomendo_credit_card.js', array(), '', true);
// 	wp_enqueue_style( 'woomendo_page_admin_style', plugin_dir_url( __FILE__ ).'creditCard/woomendo_credit_card.css');
// });