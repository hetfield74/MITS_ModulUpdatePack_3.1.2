<?php
/* -----------------------------------------------------------------------------------------
   $Id: create_paypal_order.php 16303 2025-02-05 08:40:39Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/

  // include needed classes
  require_once(DIR_WS_CLASSES.'order.php');
  require_once(DIR_WS_CLASSES.'order_total.php');
  require_once(DIR_FS_EXTERNAL.'paypal/classes/PayPalPaymentV2.php');


  function create_paypal_order() {
    global $order;
    
    if (isset($_SESSION['shipping']) 
        && $_SESSION['shipping'] != false
        )
    {
      require_once (DIR_WS_CLASSES . 'shipping.php');
      $shipping_modules = new shipping($_SESSION['shipping']);
    }

    $order = new order();

    $order_total_modules = new order_total();
    $order_total_modules->pre_confirmation_check();
    $order_total_modules->process();

    $paypal = new PayPalPaymentV2(isset($_REQUEST['payment_method']) ? $_REQUEST['payment_method'] : 'paypal');
        
    $payment_source = array();
    if (isset($_POST['save_payment']) 
        && $_POST['save_payment'] == 'save_payment'
        )
    {
      if ($paypal->code == 'paypalacdc') {
        $payment_source = array(
          'payment_source' => array(
            'card' => array(
              'attributes' => array(
                'vault' => array(
                  'store_in_vault' => 'ON_SUCCESS',
                ),
                'verification' => array(
                  'method' => 'SCA_WHEN_REQUIRED',
                )
              )
            )
          )
        );
      } else {
        $payment_source = array(
          'payment_source' => array(
            'paypal' => array(
              'attributes' => array(
                'vault' => array(
                  'store_in_vault' => 'ON_SUCCESS',
                  'usage_type' => 'MERCHANT',
                  'customer_type' => 'CONSUMER',
                  'permit_multiple_payment_tokens' => true,
                )
              )
            )
          )
        );
      }
      
      if (isset($_SESSION['customer_id'])) {
        $customer_id = $paypal->getCustomerId($_SESSION['customer_id']);
        
        if (!is_null($customer_id)) {
          $payment_source['payment_source']['paypal']['attributes']['customer'] = array(
            'id' => $customer_id
          );
        }
      }
    }
    
    $_SESSION['paypal'] = array(
      'cartID' => $_SESSION['cart']->cartID,
      'OrderID' => $paypal->CreateOrder($payment_source)
    );

    if ($paypal->code != 'paypalexpress') {
      $paypal->PatchOrder($_SESSION['paypal']['OrderID']);
    }
    
    return $_SESSION['paypal']['OrderID'];
  }
