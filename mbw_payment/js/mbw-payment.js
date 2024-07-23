(function (Drupal) {

  /**
   * Order ID from div.
   * @type {string}
   */
  const orderId = document.querySelector('#order-payment').dataset.orderId;

  /**
   * Checkout payment form container.
   * @type {string}
   */
  const paymentDiv = "paymentContainer";

  /**
   * Div ID to render checkout form.
   * @type {string}
   */
  const checkoutDiv = "paymentCheckout";

  /**
   * Div ID to render message.
   * @type {string}
   */
  const messageDiv = "paymentMessage";

  /**
   * Instatiate new checkout form.
   *
   * @type {monerisCheckout}
   */
  const myCheckout = new monerisCheckout();

  // Set checkout form mode and div.
  myCheckout.setMode("qa");
  myCheckout.setCheckoutDiv(checkoutDiv);

  // Create callbacks for checkout actions.
  myCheckout.setCallback("page_loaded", pageLoaded);
  myCheckout.setCallback("cancel_transaction", cancelTransaction);
  myCheckout.setCallback("error_event", paymentError);
  myCheckout.setCallback("payment_receipt", paymentReceipt);
  myCheckout.setCallback("payment_complete", paymentComplete);

  /**
   * Page loaded callback.
   * @param data
   */
  function pageLoaded(data) {
    console.log('Payment page loaded.');
    const response = JSON.parse(data);

    switch (response.response_code) {
      case '001':
        console.log('Token accepted, generating payment form...');
        break;
      case '2003':
        console.log('Payment preload expired, getting a new token...');
        getPreload(orderId, true);
        break;
    }
  }

  /**
   * Transaction cancelled callback.
   * @param data
   */
  function cancelTransaction(data) {
    console.log('Transaction cancelled.');    
    displayMessage('Payment cancelled.');

    if (orderId) {
      window.location.replace(`/order/review/${orderId}`);
    }
  }

  function paymentError(data) {
    console.log('Payment error.');
    console.log(data);
    removeCheckout();
  }

  function paymentReceipt(data) {
    console.log('Payment receipt.');
    getPreauth(orderId);
  }

  function paymentComplete(data) {
    console.log('Payment complete.');
    getPreauth(orderId);
  }

  function getPreload(orderId = null, expired = 'false') {
    // Build payment preload
    console.log('Acquiring preload token...');

    // Get order ID
    if (!orderId) {
      window.location.replace(`/order/error`);
    }

    const url = '/payment/preload';

    // post body data
    const formData = new FormData();
    formData.append('order_uuid', orderId);
    formData.append('ticket_expired', expired);

    // create request object
    const request = new Request(url, {
      method: 'POST',
      body: formData
    });

    // pass request object to `fetch()`
    fetch(request)
      .then(response => response.json())
      .then(data => {
        if (data.data.status === 'success') {
          console.log('Token acquired!');
          myCheckout.startCheckout(data.data.token);
        } else {
          console.log('Could not acquire payment token.');
          window.location.replace(`/order/error/${orderId}`);
        }
      })
      .catch(error => {
        console.log(error);
        window.location.replace(`/order/error/${orderId}`);
      });
  }

  function getPreauth(orderId = null) {
    // Build payment preload
    console.log('Acquiring receipt for order...');

    // Get order ID
    if (!orderId) {
      // window.location.replace(`/order/error`);
    }

    const url = '/payment/preauth';

    // post body data
    const formData = new FormData();
    formData.append('order_uuid', orderId);

    // create request object
    const request = new Request(url, {
      method: 'POST',
      body: formData
    });

    // pass request object to `fetch()`
    fetch(request)
      .then(response => response.json())
      .then(data => {
        if (data.data.status === 'success') {
          console.log('Preauth acquired!');
          window.location.replace(`/order/process/${orderId}`);
        } else {
          console.log('Could not acquire preauth.');
          window.location.replace(`/order/error/${orderId}`);
        }
      })
      .catch(error => {
        console.log(error);
        window.location.replace(`/order/error/${orderId}`);
      });
  }

  function removeCheckout() {
    myCheckout.closeCheckout();
    document.getElementById(paymentDiv).remove();
  }

  function displayMessage(message = "") {
    const container = document.getElementById(messageDiv);
    container.innerHTML = `<p>${message}</p>`;
  }

  // Start checkout form process...
  // Get preload token to render payment form.
  getPreload(orderId);

})(Drupal);
