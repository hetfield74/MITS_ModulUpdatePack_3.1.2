<?php
/* -----------------------------------------------------------------------------------------
   $Id: dhl.php 13634 2021-07-23 15:02:37Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License 
   ---------------------------------------------------------------------------------------*/

  defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

  if (defined('MODULE_DHL_BUSINESS_STATUS') 
      && MODULE_DHL_BUSINESS_STATUS == 'True'
      && isset($_GET['saction']) 
      && $_GET['saction'] == 'createlabel'
      && !isset($orders_status_lang_array)
      ) 
  {
    $orders_status_lang_array = array();
    $orders_status_query = xtc_db_query("SELECT orders_status_id,
                                                orders_status_name,
                                                language_id
                                           FROM ".TABLE_ORDERS_STATUS."
                                       ORDER BY sort_order");
    while ($orders_status = xtc_db_fetch_array($orders_status_query)) {
      $orders_status_lang_array[$orders_status['language_id']][$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
    }
  }
