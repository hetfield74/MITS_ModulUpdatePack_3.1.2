<?php
/* -----------------------------------------------------------------------------------------
   $Id: PayPalFunctions.php 16293 2025-01-29 10:48:45Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/


defined('ENCODE_DEFINED_CHARSETS') or define('ENCODE_DEFINED_CHARSETS','ASCII,UTF-8,ISO-8859-1,ISO-8859-15,cp866,cp1251,cp1252,KOI8-R,GB18030,SJIS,EUC-JP'); 
defined('ENCODE_DEFAULT_CHARSET') or define('ENCODE_DEFAULT_CHARSET', 'ISO-8859-15');

/*
 * helper functions
 */
function get_third_party_payments() {
  $selection = array();
  if (defined('MODULE_PAYMENT_PAYPAL_PLUS_THIRDPARTY_PAYMENT')) {
    $payment_allowed = explode(';', MODULE_PAYMENT_PAYPAL_PLUS_THIRDPARTY_PAYMENT);
    
    if (count($payment_allowed) > 0) {
      require_once (DIR_FS_CATALOG . 'includes/classes/payment.php');
      $payment_modules = new payment();

      if (is_array($payment_modules->modules)) {
        if (isset($GLOBALS['ot_payment']) && is_object($GLOBALS['ot_payment'])) {
          $GLOBALS['ot_payment']->xtc_order_total();
        }
        foreach ($payment_modules->modules as $value) {
          $class = substr($value, 0, strrpos($value, '.'));
          if (isset($GLOBALS[$class]) && $GLOBALS[$class]->enabled && in_array($class, $payment_allowed)) {
            $module_selection = $GLOBALS[$class]->selection();
            if (is_array($module_selection)) {
              if (isset($GLOBALS['ot_payment']) && is_object($GLOBALS['ot_payment']) && !isset($module_selection['module_cost'])) {
                $module_selection['module_cost'] = $GLOBALS['ot_payment']->get_module_cost($module_selection);
              }
              $selection[] = $module_selection;
            }
          }
        }
      }
    }
  }
  return $selection;
}


function get_paypal_js_sdk($client_id, $currency, $intent, $commit, $client_token, $user_token, $custom) {
  static $output;
  
  if (!isset($output)) {
    $output = true;
    
    $js = xtc_href_link(DIR_WS_EXTERNAL.'paypal/js/paypal-js.min.js', '', 'SSL', false);
    $script = '<script type="module">
      import { loadScript } from "'.$js.'";
    ';
    
    if ($custom === true) {
      $script = '<script type="module">
        import { loadCustomScript } from "'.$js.'";
        %s
      </script>';
      
      return $script;
    }
    
    $locale_array = preg_split("/[-_]/", strtolower($_SESSION['language_code']));
    if (count($locale_array) == 1) {
      $locale_array[1] = $locale_array[0];
      if ($locale_array[1] == 'en') {
        $locale_array[1] = 'GB';
      }
    }
    
    $script .= '
      loadScript({
        "client-id": "'.$client_id.'",
        "currency": "'.$currency.'",
        "intent": "'.strtolower($intent).'",
        "commit": "'.$commit.'",
        "locale": "'.$locale_array[0].'_'.strtoupper($locale_array[1]).'",
        "enable-funding": "paylater",
        "data-partner-attribution-id": "Modified_Cart_PPCP",
        '.(($client_token !== false) ? '"data-client-token": "'.$client_token.'",' : '').'
        '.(($user_token !== false) ? '"data-user-id-token": "'.$user_token.'",' : '').'
        "components": "buttons,funding-eligibility,messages,hosted-fields,applepay,googlepay"
      }).then((paypal) => {
        %s
      }).catch((error) => {
        $(".apms_form").hide();
        $(".apms_form_button").hide();
        console.error("failed to load the PayPal SDK", error);
        %s
      });
    </script>';
    
    return $script;
  }
}


/*
 * compatibility functions
 */
if (!function_exists('draw_on_off_selection')) {
  function draw_on_off_selection($name, $select_array, $key_value, $params = '') {
    $string = '';
    for ($i = 0, $n = sizeof($select_array); $i < $n; $i++) {
      $string .= '<input id="'.$name.'_'.$i.'" type="radio" name="'.$name.'" value="'.$select_array[$i]['id'].'" '.$params;
      if ($key_value == $select_array[$i]['id']) $string .= ' checked="checked"';
      $string .= '><label for="'.$name.'_'.$i.'">'.$select_array[$i]['text'].'</label><br/>';
    }
    return $string;
  }
}


if (!function_exists('xtc_cfg_save_max_display_results')) {
  function xtc_cfg_save_max_display_results($cfg_key) {
    if (isset($_POST[$cfg_key])) {
      $configuration_value = preg_replace('/[^0-9-]/','',$_POST[$cfg_key]);
      $configuration_value = xtc_db_prepare_input($configuration_value);
      $configuration_query = xtc_db_query("SELECT configuration_key,
                                                  configuration_value
                                             FROM " . TABLE_CONFIGURATION . "
                                            WHERE configuration_key = '" . xtc_db_input($cfg_key) . "'
                                         ");
      if (xtc_db_num_rows($configuration_query) > 0) {
        //update
        xtc_db_query("UPDATE " . TABLE_CONFIGURATION . "
                         SET configuration_value ='" . xtc_db_input($configuration_value) . "',
                             last_modified = NOW()
                       WHERE configuration_key='" . xtc_db_input($cfg_key) . "'");
      } else {
        //new entry
        $sql_data_array = array(
          'configuration_key' => $cfg_key,
          'configuration_value' => $configuration_value,
          'configuration_group_id' => '1000',
          'sort_order' => '-1',
          'last_modified' => 'now()',
          'date_added' => 'now()'
          );
        xtc_db_perform(TABLE_CONFIGURATION,$sql_data_array);
      }
      return $configuration_value;
    }
    return defined($cfg_key) && (int)constant($cfg_key) > 0 ? constant($cfg_key) : 20;
  }
}


if (!function_exists('encode_utf8')) {
  function encode_utf8($in_str) {
    if (strtolower($_SESSION['language_charset']) == 'utf-8') {
      $cur_encoding = detect_encoding($in_str);
      if($cur_encoding == "UTF-8" && mb_check_encoding($in_str,"UTF-8")) {
        return $in_str;
      } else {
        return mb_convert_encoding($in_str,"UTF-8","ISO-8859-15");
      }
    } else {
      return $in_str;
    }
  }
}


if (!function_exists('decode_utf8')) {
  function decode_utf8($in_str) {
    if (strtolower($_SESSION['language_charset']) != 'utf-8') {
      $cur_encoding = detect_encoding($in_str);
      if($cur_encoding == "UTF-8" && mb_check_encoding($in_str,"UTF-8")) {
        return mb_convert_encoding($in_str,"ISO-8859-15","UTF-8");
      } else {
        return $in_str;
      }
    } else {
      return $in_str;
    }
  }
}


if (!function_exists('detect_encoding')) {
  function detect_encoding($string, $encodings = ENCODE_DEFINED_CHARSETS, $strict = true) {
    $encoding = mb_detect_encoding($string, $encodings, $strict);
    if ($encoding === false) {
      $encoding = mb_detect_encoding($string, $encodings, false);
    }
    return $encoding;
  }
}


if (!function_exists('draw_input_per_page')) {
  function draw_input_per_page($PHP_SELF,$cfg_max_display_results_key,$page_max_display_results) {
    $output = '<div class="clear"></div>'. PHP_EOL;
    $output .= '<div class="smallText pdg2 flt-l">'. PHP_EOL;
    $output .= xtc_draw_form('cfg_max', basename($PHP_SELF)). PHP_EOL;         
    $output .= DISPLAY_PER_PAGE.xtc_draw_input_field($cfg_max_display_results_key, $page_max_display_results, 'style="width: 40px"'). PHP_EOL; 
    $output .= '<input type="submit" class="button" onclick="this.blur();" title="' . BUTTON_SAVE . '" value="' . BUTTON_SAVE . '"/>'. PHP_EOL; 
    $output .=  '</form>'. PHP_EOL; 
    $output .= '</div>'. PHP_EOL; 
    return $output;
  }
}


if (!function_exists('decode_htmlentities')) {
  function decode_htmlentities ($string, $flags = ENT_COMPAT, $encoding = '') {
    $supported_charsets = explode(',',strtoupper(ENCODE_DEFINED_CHARSETS));  
    $default_charset = isset($_SESSION['language_charset']) && in_array(strtoupper($_SESSION['language_charset']), $supported_charsets) ? strtoupper($_SESSION['language_charset']) : ENCODE_DEFAULT_CHARSET;
    $encoding = !empty($encoding) && in_array(strtoupper($encoding), $supported_charsets) ? strtoupper($encoding) : $default_charset;
    return html_entity_decode($string, $flags , $encoding);
  }
}


if (!function_exists('encode_htmlentities')) {
  function encode_htmlentities ($string, $flags = ENT_COMPAT, $encoding = '') {
    $supported_charsets = explode(',', strtoupper(ENCODE_DEFINED_CHARSETS));  
    $default_charset = isset($_SESSION['language_charset']) && in_array(strtoupper($_SESSION['language_charset']), $supported_charsets) ? strtoupper($_SESSION['language_charset']) : ENCODE_DEFAULT_CHARSET;
    $encoding = !empty($encoding) && in_array(strtoupper($encoding), $supported_charsets) ? strtoupper($encoding) : $default_charset;  
    return htmlentities($string, $flags , $encoding);
  }
}
