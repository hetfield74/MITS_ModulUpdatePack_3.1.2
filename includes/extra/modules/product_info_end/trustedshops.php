<?php
  /* --------------------------------------------------------------
   $Id: trustedshops.php 16257 2025-01-14 17:57:36Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   --------------------------------------------------------------
   Released under the GNU General Public License
   --------------------------------------------------------------*/

  if (defined('MODULE_TS_TRUSTEDSHOPS_ID') 
      && MODULE_TS_PRODUCT_STICKER_STATUS == '1'
      && MODULE_TS_PRODUCT_STICKER != ''
      )
  {    
    $product_sticker = MODULE_TS_PRODUCT_STICKER;
    $product_sticker = preg_replace('/data-sku="([\w\-\_]+)"/', 'data-sku="%s"', $product_sticker);
    $product_sticker = preg_replace('/data-gtin="([\w\-\_]+)"/', 'data-sku="%s"', $product_sticker);
    $product_sticker = preg_replace('/data-mpn="([\w\-\_]+)"/', 'data-sku="%s"', $product_sticker);
    
    if (substr_count($product_sticker, '%s') == 1) {
      $info_smarty->assign('MODULE_products_reviews', sprintf($product_sticker, $product->data['products_model']));
    } elseif (substr_count($product_sticker, '%s') == 2) {
      $info_smarty->assign('MODULE_products_reviews', sprintf($product_sticker, MODULE_TS_TRUSTEDSHOPS_ID, $product->data['products_model']));
    } else {
      $info_smarty->assign('MODULE_products_reviews', $product_sticker);
    }
  }