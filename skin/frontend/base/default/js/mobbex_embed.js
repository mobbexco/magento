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
            getEmbedData()
        },
        onFailure: function(){
            checkout.setLoadWaiting(false)
        },
        onError: function(){
            checkout.setLoadWaiting(false)
        }
    })
}

function getEmbedData() {
    new Ajax.Request(orderUrl, {
        method: "get",
        onSuccess: function(response){
            renderMobbex(response.responseJSON.checkoutId, response.responseJSON.returnUrl, response.responseJSON.orderId)
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

function renderMobbex (id, returnUrl, orderId) {
    let mbbxEmbed = window.MobbexEmbed.init({
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
    })

    mbbxEmbed.open()
}