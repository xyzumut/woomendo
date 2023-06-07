<?php
/*
	Yapılacaklar:
	* Kullanıcı ödeme sayfasında script yine yüklenmiyor ona bakılacak
	* Fatih bey bana ödeme ile alakalı api tarafında yeni bir yapı hazırlayacak onu ordan sonra ilerleteceğim
*/
?>
<?php
	/* 
		pending: Sipariş henüz işlenmemiş durumda.
		on-hold: Sipariş, müşteriye gönderilmeden önce bekletiliyor.
		processing: Sipariş, işleme aşamasında, ödeme onayı vb. bekleniyor.
		completed: Sipariş başarıyla tamamlandı.
		cancelled: Sipariş müşteri veya yönetici tarafından iptal edildi.
		refunded: Siparişin tamamı iade edildi.
		failed: Siparişin ödeme işlemi başarısız oldu.
		$order->payment_complete()  processing moduna alıyor
	*/
?>
<?php 
	function make_payment_action(){
		?>
			<script>
				const message_error = 'Sipariş oluşturuldu ancak ödeme sağlanamadı, bankanızın cevabı :\t'
				const message_success = 'Sipariş oluşturuldu ve ödeme başarılı, yönlendiriliyorsunuz...'
				window.addEventListener(
					"message",
					(event) => {
						let messageData = event.data;
						let messageType = messageData.event;
						if(messageType === "payment_failed"){
							let message = "' "+messageData.message+" '";
							document.getElementById('paymendo-payment-result-message').innerText = message_error+message+'\n Yönlendiriliyorsunuz...';
						}
						else if (messageType === "payment_success") {
							document.getElementById('paymendo-payment-result-message').innerText = message_success;
							window.location.href = '/wp/faturalar-sayfasi/';
						}
					},
					false
				);
			</script>
		<?php

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

		$paymendo_request = new PaymendoRequest(get_option('login_password'), get_option('login_mail'), get_option('base_api_url'));

		try{

			$payment_response = $paymendo_request->makePaymentWithoutAccessToken($creditCardData, $_GET['order_api_id']);

			if (isset($payment_response['status']) && $payment_response['status'] == 'true') {
				$form = $payment_response['data']['attributes']['form'];
				$form = html_entity_decode($form);
				echo /*html*/"
					<div id='paymendo-payment-result-container'>
						<iframe srcdoc='$form' style='display:none;' id='woomendo_payment_iframe'></iframe>
						<div id='paymendo-payment-result'>
						<p id='paymendo-payment-result-message'>Bankanızla iletişim kuruluyor lütfen bekleyiniz...</p>
						<div v-if='loading' class='spinner'>
							<div class='rect1'></div>
							<div class='rect2'></div>
							<div class='rect3'></div>
							<div class='rect4'></div>
							<div class='rect5'></div>
						</div>
						</div>
					</div>
					<style>
						*{
							margin:0;
							padding:0;
						}
						#paymendo-payment-result-container{
							width:100vw;
							height:100vh;
							display:flex;
							justify-content:center;
							align-items:center;
							background-color:black;
						}
						#paymendo-payment-result{
							width:500px;
							height:250px;
							background-color:rgba(0, 141, 173, .6);
							display:flex;
							justify-content:center;
							align-items:center;
							padding:30px;
							border-radius:8px;
							flex-direction:column;
						}
						#paymendo-payment-result p{
							font-size:20px;
							color:white;
							line-height: 40px;
						}
						.spinner {
							width: 50px;
							height: 60px;
							text-align: center;
							font-size: 10px;
							margin-top:30px;
						}
						.spinner > div {
							background-color: #00d1b2;
							height: 100%;
							width: 6px;
							display: inline-block;
							-webkit-animation: stretchDelay 1.2s infinite ease-in-out;
							animation: stretchDelay 1.2s infinite ease-in-out;
						}
						.spinner .rect2 {
							-webkit-animation-delay: -1.1s;
							animation-delay: -1.1s;
						}
						.spinner .rect3 {
							-webkit-animation-delay: -1s;
							animation-delay: -1s;
						}
						.spinner .rect4 {
							-webkit-animation-delay: -0.9s;
							animation-delay: -0.9s;
						}
						.spinner .rect5 {
							-webkit-animation-delay: -0.8s;
							animation-delay: -0.8s;
						}
						@-webkit-keyframes stretchDelay {
							0%,
							40%,
							100% {
								-webkit-transform: scaleY(0.4);
							}
							20% {
								-webkit-transform: scaleY(1);
							}
						}
						@keyframes stretchDelay {
							0%,
							40%,
							100% {
								transform: scaleY(0.4);
								-webkit-transform: scaleY(0.4);
							}
							20% {
								transform: scaleY(1);
								-webkit-transform: scaleY(1);
							}
						}
					</style>		
				";
			}
			else {
				die('Error!');
			}
		}
		catch (Exception $error){
			return '<h4>Bir hata oluştu, daha sonra tekrar deneyin.</h4>';
		}
		die;
	}
?>