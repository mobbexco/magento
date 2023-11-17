window.addEventListener('load', function () {

    var mbbxPaymentData = false;

    // Catch checkout section change
    if ((typeof Checkout !== 'undefined')) {
        Checkout.prototype.gotoSection = Checkout.prototype.gotoSection.wrap(
            function (parentMethod, section, reloadProgressBlock) {
                parentMethod(section, reloadProgressBlock, reloadProgressBlock);

                // If it's review section and mobbex was selected
                if (section == 'review' && payment.currentMethod === 'mobbex') {
                    // Add event to init embed checkout
                    document.getElementById('review-buttons-container').children[0].onclick = function () {
                        formRequest();
                    }
                }
            }
        );
    }

    //Firecheckout integration
    document.observe('firecheckout:saveBefore', function (e) {

        checkout.setLoadWaiting(true)

        if (e.memo.forceSave) {
            return;
        }

        if (payment.getCurrentMethod() === 'mobbex') {
            e.memo.stopFurtherProcessing = true;
            firecheckoutOrderSave();
            return false;
        }

    });
});

/** PAYMENT METHODS SUBDIVISION EVENTS */
// Current payment method & card
var mbbxCurrentMethod = '';
var mbbxCurrentCard = '';

$(document).on('click', '.radio', function (e) {
    if (e.target.getAttribute('id') == "p_method_mobbex")
        return;

    mbbxCurrentMethod = '';
    mbbxCurrentCard = '';
    document.querySelectorAll(".mobbex-wallet-form").forEach(element => {
        element.classList.add('mbbx-hidden');
    });

    if (e.target.classList.contains('mbbx-payment-method-input')) {
        if (e.target.classList.contains("mbbx-card")) {
            mbbxCurrentCard = e.target.getAttribute('value');
            document.querySelector(`#${mbbxCurrentCard}`).classList.remove('mbbx-hidden');
            document.querySelector(`#${mbbxCurrentCard} input`).disabled = false
            document.querySelector(`#${mbbxCurrentCard} select`).disabled = false
        } else {
            mbbxCurrentMethod = e.target.getAttribute('value');
        }
        document.querySelector('#p_method_mobbex').click();
    } else {
        document.querySelectorAll('.mbbx-payment-method-input').forEach(element => {
            element.checked = false;
        });
    }
});

/* PROCESS ORDER */
function processOrder() {
    new Ajax.Request(orderUrl, {
        method: "get",
        onSuccess: function (response) {
            if (wallet && mbbxCurrentCard) {
                executeWallet(response.responseJSON);
            } else if (embed) {
                renderMobbex(response.responseJSON.checkoutId, response.responseJSON.returnUrl);
            } else {
                mbbxRedirect(response.responseJSON.url);
            }
        },
        onFailure: function () {
            checkout.setLoadWaiting(false)
            window.top.location.reload();
        },
        onError: function (error) {
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
function renderMobbex(id, returnUrl) {
    let options = {
        id: id,
        type: 'checkout',
        paymentMethod: mbbxCurrentMethod || null,

        onPayment: (data) => {
            mbbxPaymentData = data.data;
        },

        onResult: (responseData) => {
            location.href = returnUrl + '&status=' + responseData.status.code
        },

        onClose: () => {
            checkout.setLoadWaiting(false)
            location.href = returnUrl + '&status=500' + (mbbxPaymentData ? mbbxPaymentData.status.code : '500');
        }
    }

    let mbbxEmbed = window.MobbexEmbed.init(options)
    mbbxEmbed.open()
}

/**
* Redirect to Mobbex Checkout
* @param checkoutUrl 
*/
function mbbxRedirect(checkoutUrl) {
    let mobbexForm = document.querySelector('#mbbx_redirect_form');
    mobbexForm.setAttribute('action', checkoutUrl);
    if (mbbxCurrentMethod)
        mobbexForm.innerHTML = `<input type='hidden' name='paymentMethod' value='${mbbxCurrentMethod}'/>`
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
            location.href = response.returnUrl + '&status=500';
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
        onSuccess: function () {
            processOrder()
        },
        onFailure: function () {
            checkout.setLoadWaiting(false)
        },
        onError: function () {
            checkout.setLoadWaiting(false)
        }
    })
}

/**
* Save the order in case that Firecheckout Plugin is active.
*/
function firecheckoutOrderSave() {
    new Ajax.Request(checkout.urls.save, {
        method: 'post',
        parameters: checkout.getFormData(),
        onSuccess: function (response) {
            processOrder();
        },
        onFailure: function (response) {
            console.log(response)
        }
    });
}