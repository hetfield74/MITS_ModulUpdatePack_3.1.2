<?php
/* -----------------------------------------------------------------------------------------
   $Id: paypal.php 15236 2023-06-14 06:51:22Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/

$paypal_payment_method = array(
  'paypalpui',
  'paypalplus',
  'paypalclassic',
  'paypalcart',
  'paypallink',
  'paypalpluslink',
);

if (is_object($order) && in_array($order->info['payment_method'], $paypal_payment_method)) {

  // include needed classes
  require_once(DIR_FS_EXTERNAL.'paypal/classes/PayPalPayment.php');

  $paypal = new PayPalPayment($order->info['payment_method']);
  
  $tpl_file = DIR_FS_EXTERNAL.'paypal/templates/payment_info.html';
  if (is_file(DIR_FS_CATALOG.'templates/'.CURRENT_TEMPLATE.'/module/paypal/payment_info.html')) {
    $tpl_file = DIR_FS_CATALOG.'templates/'.CURRENT_TEMPLATE.'/module/paypal/payment_info.html';
  }

  if (strpos($order->info['payment_method'], 'link') !== false) {
    if (!isset($payment_modules) || !is_object($payment_modules)) {
      require_once (DIR_FS_CATALOG.'includes/classes/payment.php');
      $payment_modules = new payment($order->info['payment_class']);
    }
    $paypal_payment_info = array(
      array ('title' => method_exists($payment_modules, 'payment_title') ? $payment_modules::payment_title($order->info['payment_method'], $order->info['order_id']) : strip_tags($paypal->title).': ', // Fallback for shop version <= 2.0.4.2
             'class' => $paypal->code,
             'fields' => array(array('title' => '',
                                     'field' => sprintf(constant('MODULE_PAYMENT_'.strtoupper($paypal->code).'_TEXT_SUCCESS'), $paypal->create_paypal_link($order->info['order_id'])),
                                     )
                               )
             )
    );
    
    $paypal_smarty = new Smarty();
    if (defined('RUN_MODE_ADMIN')) {
      $paypal_smarty->template_dir = DIR_FS_CATALOG.'templates';
      $paypal_smarty->compile_dir = DIR_FS_CATALOG.'templates_c';
      $paypal_smarty->config_dir = DIR_FS_CATALOG.'lang';
    }
    $paypal_smarty->caching = 0;
    $paypal_smarty->assign('PAYMENT_INFO', $paypal_payment_info);
    $paypal_smarty->assign('language', $_SESSION['language']);
    $payment_info_content = $paypal_smarty->fetch($tpl_file);

    $smarty->assign('PAYMENT_INFO_HTML', $payment_info_content);
    $smarty->assign('PAYMENT_INFO_TXT', sprintf(constant('MODULE_PAYMENT_'.strtoupper($paypal->code).'_TEXT_SUCCESS'), $paypal->create_paypal_link($order->info['order_id'], true)));

  } else {
    $paypal_payment_info = $paypal->get_payment_instructions($order->info['order_id']);
  
    if (is_array($paypal_payment_info)) {
      $paypal_smarty = new Smarty();
      if (defined('RUN_MODE_ADMIN')) {
        $paypal_smarty->template_dir = DIR_FS_CATALOG.'templates';
        $paypal_smarty->compile_dir = DIR_FS_CATALOG.'templates_c';
        $paypal_smarty->config_dir = DIR_FS_CATALOG.'lang';
      }
      $paypal_smarty->caching = 0;
      $paypal_smarty->assign('PAYMENT_INFO', $paypal_payment_info);
      $paypal_smarty->assign('language', $_SESSION['language']);
      
      $payment_info_content = $paypal_smarty->fetch($tpl_file);
  
      $smarty->assign('PAYMENT_INFO_HTML', $payment_info_content);
      $smarty->assign('PAYMENT_INFO_TXT', $payment_info_content);
    }
  }
  
}

if (isset($_SESSION['paypal_express_new_customer']) 
    && $_SESSION['paypal_express_new_customer'] == 'true'
    && !isset($send_by_admin)
    && is_object($order)
    ) 
{
  require_once (DIR_FS_INC.'xtc_random_charcode.inc.php');
  
  $vlcode = xtc_random_charcode(32);
  $link = xtc_href_link(FILENAME_PASSWORD_DOUBLE_OPT, 'action=verified&customers_id='.$order->customer['ID'].'&key='.$vlcode, 'SSL', false);

  $sql_data_array = array('password_request_key' => $vlcode);
  xtc_db_perform(TABLE_CUSTOMERS, $sql_data_array, 'update', "customers_id = '" . $order->customer['ID'] . "'");
  
  $smarty->assign('NEW_PASSWORD', $link);
}
