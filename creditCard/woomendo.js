// jQuery(document.body).trigger("update_checkout")

jQuery(document).on('ready updated_checkout', function (param) { 
    const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop),
    });

    if (params.pay_for_order !== null && params.key !== null) {
        // Müşterinin özel ödeme sayfasındayız, bunu key ve pay_for_order parametreleri ile teyit ediyoruz

        document.getElementById('order_review').addEventListener('submit', async (e) => {
            // order_review form'u kullanınıcın özel ödeme sayfasındaki ödeme yöntemlerinin bulunduğu form

            if(document.getElementById('payment_method_woomendo').checked === true){
                // ödeme yöntemlerinden woomendo seçili iken submit edilip edilmediğini filtreledik

                e.preventDefault()
                
                // Order id'yi linkten aldım
                const url_params = window.location.href.split('/')
                console.log(url_params)
                let order_id = url_params.filter(item => !isNaN(Number(item)) && (Number(item) !== 0) )


                if (typeof order_id === 'object' && order_id.length === 0) {
                    order_id = url_params.filter( item => item.includes('order-pay='))[0].split('&').filter(item => item.includes('order-pay'))[0].split('=')[1]
                }
                // Order id'yi linkten aldım
                


                // order id dışındaki verileri aldık
                let response = await fetch(woomendo_script.admin_url+'admin-ajax.php?action=paymendo_session&operation=get_id_and_token&order_id=' + order_id)

                response = await response.json()

                if (response.status === false) {
                    alert('Bir hata oluştu')
                    return;
                }

                document.querySelector('.blockUI.blockOverlay').style.display = 'none'
                const order_id_in_api = response.data.order_id_in_api
                const redirect_url = response.data.redirect_url
                const target_url_with_token = response.data.target_url_with_token

                const woomendo_card_expDate = document.getElementById('expirationdate').value;
                const woomendo_card_holder = document.getElementById('holder_name').value;
                const woomendo_card_number = document.getElementById('cardnumber').value.replaceAll(' ', '');
                const woomendo_card_securityCode = document.getElementById('securitycode').value;
                // order id dışındaki verileri aldık


                // İstek atmadan modalı açtım
                document.getElementById('woomendo_modal').style.display = 'flex' 
                document.getElementById('woomendo_modal_header_close_button').addEventListener('click', () => {document.getElementById('woomendo_modal').style.display='none'}) // modal kapatma eventim
                document.getElementById('woomendo_modal_content_container').innerHTML = '<div v-if="loading" class="spinner" id="woomendo-spinner"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>'
                document.getElementById('woomendo_modal_header_close_button').style.visibility='hidden'
                // İstek atmadan modalı açtım


                // Ödeme isteğini attık
                const myOptions = {
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    method: 'POST',
                    body : JSON.stringify({
                        data:{
                            attributes:{
                                cc_number: woomendo_card_number,
                                cc_cvv: woomendo_card_securityCode,
                                cc_exp: woomendo_card_expDate,
                                cc_holder: woomendo_card_holder,
                                order_id: order_id_in_api,
                                installment: '1'
                            }
                        }
                    })
                }
                response = await fetch(target_url_with_token, myOptions);
                response = await response.json()
                // Ödeme isteğini attık

                // formu modal'a basıp spinnerı kaldırdım ve mesajı güncelledim
                const form = response.data.attributes.form;

                const popup_container = document.getElementById('woomendo_modal_content_container');

                const iframe = document.createElement('iframe');
                iframe.style.width = '400px';
                iframe.style.height = '450px';
                iframe.srcdoc = form;
                iframe.id='paymendo-payment-iframe';
                popup_container.appendChild(iframe);

                document.getElementById('woomendo-spinner').remove();

                document.getElementById('woomendo_modal_header_content').innerText = woomendo_script._3d_secure_message

                document.getElementById('redirect_url').innerText = redirect_url;
                // formu modal'a basıp spinnerı kaldırdım ve mesajı güncelledim
            }
        })
        // Müşterinin özel ödeme sayfasındayız
    }
})


jQuery(document)
.on("ajaxSend", function(event, xhr, options){   
})
.on("ajaxComplete", async function(event, xhr, options){
    let url = options.url;
    if(url !== wc_checkout_params.checkout_url)
        return false;
    let data = options.data;
    if(data.indexOf("payment_method=woomendo") === -1)
        return false;

    const xhr_datas = xhr.responseJSON.ajax_datas ?? null;
    
    if (xhr_datas === null) 
        return false;
    
    // Bilgileri aldım
    const order_id_in_api = xhr_datas.order_id_in_api;
    const target_url_with_token = xhr_datas.target_url_with_token;

    document.getElementById('redirect_url').innerText = xhr_datas.redirect_url;

    const woomendo_card_expDate = document.getElementById('expirationdate').value;
    const woomendo_card_holder = document.getElementById('holder_name').value;
    const woomendo_card_number = document.getElementById('cardnumber').value.replaceAll(' ', '');
    const woomendo_card_securityCode = document.getElementById('securitycode').value;
    // Bilgileri aldım



    // Mecburi aradaki kısa uyarıyı sildim
    document.getElementById('woomendo_first_notice').remove() 
    // Mecburi aradaki kısa uyarıyı sildim

    
    
    
    // Modal ve spinnerı ekrana bastım
    document.getElementById('woomendo_modal').style.display = 'flex' 
    document.getElementById('woomendo_modal_header_close_button').addEventListener('click', () => {document.getElementById('woomendo_modal').style.display='none'}) // modal kapatma eventim
    document.getElementById('woomendo_modal_content_container').innerHTML = '<div v-if="loading" class="spinner" id="woomendo-spinner"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>'
    document.getElementById('woomendo_modal_header_close_button').style.visibility='hidden'
    // Modal ve spinnerı ekrana bastım


    // ödeme isteği attım
    const myOptions = {
        headers: {
            'Content-Type': 'application/json'
        },
        method: 'POST',
        body : JSON.stringify({
            data:{
                attributes:{
                    cc_number: woomendo_card_number,
                    cc_cvv: woomendo_card_securityCode,
                    cc_exp: woomendo_card_expDate,
                    cc_holder: woomendo_card_holder,
                    order_id: order_id_in_api,
                    installment: '1'
                }
            }
        })
    }
    let response = await fetch(target_url_with_token, myOptions);
    response = await response.json()
    // ödeme isteği attım


    // Formu modala bastım, spinner'ı ekrandan kaldırıp yazıyı güncelledim
    const form = response.data.attributes.form;

    const popup_container = document.getElementById('woomendo_modal_content_container');

    const iframe = document.createElement('iframe');
    iframe.style.width = '400px';
    iframe.style.height = '450px';
    iframe.srcdoc = form;
    iframe.id='paymendo-payment-iframe';
    popup_container.appendChild(iframe);

    document.getElementById('woomendo-spinner').remove(); // spinnerı sildim

    document.getElementById('woomendo_modal_header_content').innerText = woomendo_script._3d_secure_message
    // Formu modala bastım, spinner'ı ekrandan kaldırıp yazıyı güncelledim
});

// Bankadan gelen formun bana döndüğü mesajı dinleyip ona göre çıktı verdim
window.addEventListener(
    "message",
    (event) => {
        document.getElementById('woomendo_modal_header_content').innerText = woomendo_script.response_has_been_received_message
        let messageData = event.data;
        let messageType = messageData.event;
        if(messageType === "payment_failed"){
            let message = messageData.message;
            const woomendo_modal_close_button = document.getElementById('woomendo_modal_header_close_button')
            const woomendo_modal_content_container = document.getElementById('woomendo_modal_content_container')
            woomendo_modal_content_container.innerHTML = '<p id="woomendo_modal_content">'+message+'</p>'
            woomendo_modal_close_button.style.visibility='visible'
            woomendo_modal_close_button.addEventListener('click', () => {
                woomendo_modal_content_container.innerHTML = ''
                document.getElementById('woomendo_modal_header_content').innerText = woomendo_script.response_has_been_received_message
            })
        } 
        else if (messageType === "payment_completed") {
            const redirect_url = document.getElementById('redirect_url').innerText;
            document.getElementById('paymendo-payment-iframe').style.display = 'none'
            document.getElementById('woomendo_modal_header_content').innerText = woomendo_script.payment_successful_redirect_message
            document.getElementById('woomendo_modal_content_container').innerHTML = '<div v-if="loading" class="spinner" id="woomendo-spinner"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>'
            location.href = redirect_url;
        }
    },
    false
);
// Bankadan gelen formun bana döndüğü mesajı dinleyip ona göre çıktı verdim