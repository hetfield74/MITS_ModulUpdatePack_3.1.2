<?php
/* -----------------------------------------------------------------------------------------
   $Id: paypalmybank.php 16449 2025-05-14 08:24:16Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/


$lang_array = array(
  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_TITLE' => 'MyBank via PayPal',
  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_ADMIN_TITLE' => 'MyBank via PayPal',
  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_INFO' => '<img src="https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_mybank_color.svg" />',
  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_DESCRIPTION' => 'Der K&auml;ufer wird nach dem "Best&auml;tigen" zu MyBank geleitet, um hier die Bestellung zu bezahlen.<br />Danach gelangt er zur&uuml;ck in den Shop und erh&auml;lt Ihre Bestell-Best&auml;tigung.<br />Jetzt schneller bezahlen mit unbegrenztem PayPal-K&auml;uferschutz - nat&uuml;rlich kostenlos.<br /><br /><strong><font color="red">ACHTUNG:</font></strong> Damit der Bestellstatus korrekt gesetzt wird, m&uuml;ssen folgende <a href="'.xtc_href_link('paypal_webhook.php').'">Webhooks</a> in der PayPal Konfiguration eingestellt werden, damit der Status korrekt umgestellt wird:<ul><li>PAYMENT.CAPTURE.COMPLETED</li><li>PAYMENT.CAPTURE.DECLINED</li><li>PAYMENT.CAPTURE.DENIED</li><li>PAYMENT.CAPTURE.PENDING</li></ul>',
  'MODULE_PAYMENT_PAYPALMYBANK_ALLOWED_TITLE' => 'Erlaubte Zonen',
  'MODULE_PAYMENT_PAYPALMYBANK_ALLOWED_DESC' => 'Das Modul kann f&uuml;r die folgenden Zonen verwendet werden.',
  'MODULE_PAYMENT_PAYPALMYBANK_STATUS_TITLE' => 'MyBank via PayPal aktivieren',
  'MODULE_PAYMENT_PAYPALMYBANK_STATUS_DESC' => 'M&ouml;chten Sie Zahlungen per PayPal MyBank akzeptieren?',
  'MODULE_PAYMENT_PAYPALMYBANK_SORT_ORDER_TITLE' => 'Anzeigereihenfolge',
  'MODULE_PAYMENT_PAYPALMYBANK_SORT_ORDER_DESC' => 'Reihenfolge der Anzeige. Kleinste Ziffer wird zuerst angezeigt',
  'MODULE_PAYMENT_PAYPALMYBANK_ZONE_TITLE' => 'Zahlungszone',
  'MODULE_PAYMENT_PAYPALMYBANK_ZONE_DESC' => 'Wenn eine Zone ausgew&auml;hlt ist, gilt die Zahlungsmethode nur f&uuml;r diese Zone.',
  'MODULE_PAYMENT_PAYPALMYBANK_LP' => '<br /><br />F&uuml;r diese Zahlungsart ben&ouml;tigen Sie ein PayPal H&auml;ndler Konto.<br /><a target="_blank" href="https://www.paypal.com/business"><strong>Jetzt PayPal Konto hier erstellen.</strong></a>',

  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_EXTENDED_DESCRIPTION' => '<strong><font color="red">ACHTUNG:</font></strong> Bitte nehmen Sie noch die Einstellungen unter "Partner Module" -> "PayPal" -> <a href="'.xtc_href_link('paypal_config.php').'"><strong>"PayPal Konfiguration"</strong></a> vor!',

  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_ERROR_HEADING' => 'Hinweis',
  'MODULE_PAYMENT_PAYPALMYBANK_TEXT_ERROR_MESSAGE' => 'Die Zahlung mit MyBank via PayPal wurde abgebrochen',  
);


foreach ($lang_array as $key => $val) {
  defined($key) or define($key, $val);
}
