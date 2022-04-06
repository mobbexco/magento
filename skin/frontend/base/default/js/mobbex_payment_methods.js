window.addEventListener('load', function () {
    // Catch checkout section change
    Checkout.prototype.gotoSection = Checkout.prototype.gotoSection.wrap(
        function (parentMethod, section, reloadProgressBlock) {
            parentMethod(section, reloadProgressBlock, reloadProgressBlock);

            // If it's review section and mobbex was selected
            if (section == 'review' && getFormData()["payment[method]"] == "mobbex") {
                // Add event to init embed checkout
                document.getElementById('review-buttons-container').children[0].onclick = function() {
                    formRequest();
                }
            }
        }
    );
});

/** PAYMENT METHODS SUBDIVISION EVENTS */
// Current payment method & card
let mbbxCurrentMehtod = '';
let mbbxCurrentCard = '';

$(document).on('click', '.radio', function (e) {
    if(e.target.getAttribute('id') == "p_method_mobbex")
        return;

    mbbxCurrentMehtod = '';
    mbbxCurrentCard = '';
    document.querySelectorAll(".mobbex-wallet-form").forEach(element => {
        element.classList.add('mbbx-hidden');
    });

    if(e.target.classList.contains('mbbx-payment-method-input')){
        if (e.target.classList.contains("mbbx-card")) {
            mbbxCurrentCard = e.target.getAttribute('value');
            document.querySelector(`#${mbbxCurrentCard}`).classList.remove('mbbx-hidden');
            document.querySelector(`#${mbbxCurrentCard} input`).disabled=false
            document.querySelector(`#${mbbxCurrentCard} select`).disabled=false
        } else {
            mbbxCurrentMehtod = e.target.getAttribute('value');
        }
        document.querySelector('#p_method_mobbex').click();
    } else {
        document.querySelectorAll('.mbbx-payment-method-input').forEach(element => {
            element.checked=false;
        });
    }
});

/* PROCESS ORDER */
function processOrder() {
    new Ajax.Request(orderUrl, {
        method: "get",
        onSuccess: function(response){
            if(wallet && mbbxCurrentCard){
                executeWallet(response.responseJSON);
            } else if(embed){
                renderMobbex(response.responseJSON.checkoutId, response.responseJSON.returnUrl, response.responseJSON.orderId);
            } else {
                mbbxRedirect(response.responseJSON.url);
            }
        },
        onFailure: function(){
            checkout.setLoadWaiting(false)
        },
        onError: function(error){
            console.log(error)
            checkout.setLoadWaiting(false)
        }
    })
}

/**
 * Render Mobbex Checkout in page.
 * @param id 
 * @param returnUrl 
 * @param orderId 
 */
function renderMobbex (id, returnUrl, orderId) {
    let options = {
        id: id,
        type: 'checkout',

        onResult: (responseData) => {
            location.href = returnUrl + '&status=' + responseData.status.code + '&orderId=' + orderId
        },

        onClose: () => {
            checkout.setLoadWaiting(false)
            location.href = returnUrl
        },

        onError: (error) => {
            console.log(error)
            checkout.setLoadWaiting(false)
            location.href = returnUrl
        }
    }

    if(mbbxCurrentMehtod)
        options.paymentMethod = mbbxCurrentMehtod;

    let mbbxEmbed = window.MobbexEmbed.init(options)
    mbbxEmbed.open()
}

/**
 * Redirect to Mobbex Checkout
 * @param checkoutUrl 
 */
function mbbxRedirect(checkoutUrl) {
    let mobbexForm = document.querySelector('#mbbx_redirect_form');
    mobbexForm.setAttribute('action', url);
    if(mbbxCurrentMehtod)
        mobbexForm.innerHTML = `<input type='hidden' name='paymentMethod' value='${mbbxCurrentMehtod}'/>`
    mobbexForm.submit();
}

/**
 * Call Mobbex API using sdk to make the payment
 * with wallet card
 * @param response 
 */
 function executeWallet(response) {
    let updatedCard = response.wallet.find(card => card.card.card_number == document.querySelector(`#${mbbxCurrentCard} input[name=card-number]`).getAttribute('value'));

    var options = {
        intentToken: updatedCard.it,
        installment: document.querySelector(`#${mbbxCurrentCard} select`).value,
        securityCode: document.querySelector(`#${mbbxCurrentCard} input[name=security-code]`).value
    };
    
    window.MobbexJS.operation.process(options)
        .then(data => {
            window.top.location = response.returnUrl + '&status=' + data.data.status.code;
        })
        .catch(error => {
            location.href = response.returnUrl;
        })
}

/* Place Order Events */
function getFormData() {
    const fullData = {}
    const data = Form.getElements(payment.form)
    data.forEach(input => {
        fullData[input.name] = input.value
    })
    return fullData
};

function formRequest() {
    checkout.setLoadWaiting("review", true)
    new Ajax.Request(review.saveUrl, {
        method: "post",
        parameters: getFormData(),
        onSuccess: function(){
            processOrder()
        },
        onFailure: function(){
            checkout.setLoadWaiting(false)
        },
        onError: function(){
            checkout.setLoadWaiting(false)
        }
    })
}