<?php
/* -----------------------------------------------------------------------------------------
   $Id: DHLBusinessShipment.php 16487 2025-06-25 12:04:56Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License 
   ---------------------------------------------------------------------------------------*/

  define('DHL_API_AUTH', 'http://dhl.de/webservice/cisbase');
  define('DHL_API_URL', 'https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/3.1/geschaeftskundenversand-api-3.1.wsdl');

  define('DHL_SANDBOX_URL', 'https://cig.dhl.de/services/sandbox/soap');
  define('DHL_PRODUCTION_URL', 'https://cig.dhl.de/services/production/soap');

  // include needed function
  require_once(DIR_FS_INC.'xtc_get_countries_with_iso_codes.inc.php');
  require_once(DIR_FS_INC.'xtc_get_countries.inc.php');

  // include nneded classes
  require_once(DIR_WS_CLASSES.'order.php');

  #[AllowDynamicProperties]
  class DHLBusinessShipment {

    private $data;
    private $info;
    private $client;
    private $order;
    private $insurance_array;
    private $loglevel;
    private $LoggingManager;

    protected $sandbox;


    function __construct($data) {
      $this->sandbox = false;
      $this->loglevel = defined('MODULE_DHL_BUSINESS_LOGLEVEL') ? MODULE_DHL_BUSINESS_LOGLEVEL : 'ERROR';
      $this->LoggingManager = new LoggingManager(DIR_FS_LOG.'mod_dhl_%s_%s.log', 'dhl', strtolower($this->loglevel));
      
      $this->data = array(
        'user'          => MODULE_DHL_BUSINESS_USER,
        'signature'     => MODULE_DHL_BUSINESS_SIGNATURE,
        'ekp'           => MODULE_DHL_BUSINESS_EKP,
        'api_user'      => 'ModifiedShopV1_1',
        'api_password'  => 'tHv3UHNBc9FE6VXZz2mgWXK9oEFO5i',
      );
      
      $account_data = preg_split("/[:,]/", MODULE_DHL_BUSINESS_ACCOUNT); 
      for ($i=0, $n=count($account_data); $i<$n; $i+=2) {
        if (!isset($this->data['account'][$account_data[$i]])) {
          $this->data['account'][$account_data[$i]] = array();
        }
        if (strpos($account_data[$i+1], 'PK') !== false) {
          $this->data['account'][$account_data[$i]]['PK'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
        } elseif (strpos($account_data[$i+1], 'KP') !== false) {
          $this->data['account'][$account_data[$i]]['KP'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
        } elseif (strpos($account_data[$i+1], 'WP') !== false) {
          $this->data['account'][$account_data[$i]]['WP'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
        } elseif (strpos($account_data[$i+1], 'RT') !== false) {
          $this->data['account'][$account_data[$i]]['RT'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
        } else {
          $this->data['account'][$account_data[$i]]['PK'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
          $this->data['account'][$account_data[$i]]['KP'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
          $this->data['account'][$account_data[$i]]['WP'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
          $this->data['account'][$account_data[$i]]['RT'] = preg_replace('/[^\d]/', '', $account_data[$i+1]);
        }
      }
      
      $country = xtc_get_countries_with_iso_codes(STORE_COUNTRY);
      $street_address = $this->parse_street_address(MODULE_DHL_BUSINESS_ADDRESS);
      $this->info = array(
        'name'            => MODULE_DHL_BUSINESS_FIRSTNAME . ' ' . MODULE_DHL_BUSINESS_LASTNAME,
        'firstname'       => MODULE_DHL_BUSINESS_FIRSTNAME,
        'lastname'        => MODULE_DHL_BUSINESS_LASTNAME,
        'company'         => MODULE_DHL_BUSINESS_COMPANY,
        'street_name'     => $street_address['street_name'],
        'street_number'   => $street_address['street_number'],
        'street_address'  => MODULE_DHL_BUSINESS_ADDRESS,
        'postcode'        => MODULE_DHL_BUSINESS_POSTCODE,
        'city'            => MODULE_DHL_BUSINESS_CITY,
        'country'         => $country['countries_name'],
        'country_iso_2'   => $country['countries_iso_code_2'],
        'email_address'   => STORE_OWNER_EMAIL_ADDRESS,
        'telephone'       => MODULE_DHL_BUSINESS_TELEPHONE,
      );
      $this->info = $this->encode_request($this->info);
      
      foreach ($data as $k => $v) {
        $this->$k = $v;
      }
      
      if (isset($this->weight)) {
        $this->weight = str_replace(',', '.', $this->weight);
      }
      
      $this->insurance_array = array(
        0 => '500',
        1 => '2500',
        2 => '25000',
      );      
    }


    public function CreateLabel($order_id) {
      $this->order = new order($order_id);
      
      $this->buildClient();
      $request = $this->buildLabelData();
      
      try {
        $response = $this->client->createShipmentOrder($request);
      } catch (Exception $ex) {
        $this->LoggingManager->log('ERROR', 'CreateLabel', array('exception' => $ex));
        return array('message' => 'ERROR - <b>Code:</b> '.$ex->faultcode.' <b>Message:</b> '.$this->encode_message($ex->faultstring));
      }
      
      if ($response->Status->statusCode == '0') {
        $this->SaveLabel(
          $response->CreationState->shipmentNumber, 
          $response->CreationState->LabelData->labelUrl, 
          ((isset($response->CreationState->LabelData->exportLabelUrl)) ? $response->CreationState->LabelData->exportLabelUrl : '')
        );
        
        $result = array(
          'parcel_id' => $response->CreationState->shipmentNumber
        ); 
        
        if (isset($response->Status->statusText)
            && $response->Status->statusText != ''
            && strtolower($response->Status->statusText) != 'ok'
            && $this->loglevel == 'INFO'
            )
        {
          $message = array('INFO - <b>Message:</b> '.$this->encode_message($response->Status->statusText));
        }
      } else {
        $message = array('ERROR - <b>Code:</b> '.$response->Status->statusCode.' <b>Message:</b> '.$this->encode_message($response->Status->statusText));
      }
      
      if (isset($message) 
          && isset($response->CreationState->LabelData->Status->statusMessage)
          && is_array($response->CreationState->LabelData->Status->statusMessage)
          )
      {
        foreach ($response->CreationState->LabelData->Status->statusMessage as $status_message) {
          if (!isset($message[md5($status_message)])) {
            $message[md5($status_message)] = decode_utf8(encode_utf8($status_message, 'ISO-8859-1', true));
          }
        }
      }
      
      if (isset($message) && count($message) > 0) {
        $this->LoggingManager->log('ERROR', 'CreateLabel', array('exception' => $message));
        $result['message'] = implode('<br>- ',$message);
      }

      return $result;
    }


    private function SaveLabel($shipment_number, $dhl_label_url, $dhl_export_url = '') {
      $sql_data_array = array(
        'orders_id' => $this->order->info['order_id'],
        'carrier_id' => '1',
        'external' => '2',
        'date_added' => 'now()',
        'parcel_id' => $shipment_number,
        'dhl_label_url' => $dhl_label_url,
        'dhl_export_url' => $dhl_export_url,
      );
      xtc_db_perform(TABLE_ORDERS_TRACKING, $sql_data_array);
    }


    public function DeleteLabel($shipmentNumber) {
      $this->buildClient();

      // request
      $request = new stdClass();
      $request->Version = $this->buildVersion();
      $request->shipmentNumber = $shipmentNumber;
      
      try {
        $response = $this->client->deleteShipmentOrder($request);
      
        return $response->Status->statusCode;
      } catch (Exception $ex) {
        $this->LoggingManager->log('ERROR', 'buildClient', array('exception' => $ex));
        return array('message' => 'ERROR - <b>Code:</b> '.$ex->faultcode.' <b>Message:</b> '.$this->encode_message($ex->faultstring));
      }
    }


    private function buildVersion() {
      $Version = new stdClass();
      $Version->majorRelease = 3;
      $Version->minorRelease = 1;
      $Version->build = 0;
      
      return $Version;
    }


    private function buildClient() {
      $header = $this->buildAuthHeader();
      
      if ($this->sandbox === true) {
        $location = DHL_SANDBOX_URL;
      } else {
        $location = DHL_PRODUCTION_URL;
      }
      
      $ssl_opts = array(
        'ssl' => array(
          'verify_peer' => false, 
          'verify_peer_name' => false
        )
      );
      
      $auth_params = array(
        'login' => $this->data['api_user'],
        'password' => $this->data['api_password'],
        'location' => $location,
        'trace' => 1,
        'authentication' => SOAP_AUTHENTICATION_BASIC,
        'connection_timeout' => 60,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'stream_context' => stream_context_create($ssl_opts)
      );
      
      try {
        $this->client = new SoapClient(DHL_API_URL, $auth_params);
      } catch (Exception $ex) {
        $this->LoggingManager->log('ERROR', 'buildClient', array('exception' => $ex));
        return array('message' => 'ERROR - <b>Code:</b> '.$ex->faultcode.' <b>Message:</b> '.$this->encode_message($ex->faultstring));
      }
      
      try {
        $this->client->__setSoapHeaders($header);
      } catch (Exception $ex) {
        $this->LoggingManager->log('ERROR', 'buildClient', array('exception' => $ex));
        return array('message' => 'ERROR - <b>Code:</b> '.$ex->faultcode.' <b>Message:</b> '.$this->encode_message($ex->faultstring));
      }
    }


    private function buildAuthHeader() {
      $auth_params = array(
        'user' => $this->data['user'],
        'signature' => $this->data['signature'],
        'type' => 0
      );
    
      return new SoapHeader(DHL_API_AUTH, 'Authentification', $auth_params);
    }


    private function buildLabelData() {
      // customers_data
      $customers_data = $this->buildCustomersData();
            
      // Service
      $Service = new stdClass();
      
      // international
      if (in_array($this->data['product_code'], array('53', '66'))) {
        $this->notification = 1;
        if ($this->premium > 0) {
          $Service->Premium['active'] = '1';
        }
        if ($this->dutypaid > 0) {
          $Service->PDDP['active'] = '1';
        }
        if ($this->droppoint > 0) {
          $Service->CDP['active'] = '1';
        }
      }

      // Shipper
      $Shipper = $this->buildShippingDetails($this->info, 'sender');
      
      // Receiver
      $Receiver = $this->buildShippingDetails($customers_data, 'receiver');
      
      // ReturnReceiver
      $ReturnReceiver = $this->buildShippingDetails($this->info, 'sender');

      // cod
      if ($this->data['payment_class'] == 'cod') {
        $Service->CashOnDelivery = array(
          'active' => 1,
          'codAmount' => $this->data['amount']
        );
        
        // bankdata
        $BankData = new stdClass();
        $BankData->accountOwner = MODULE_DHL_BUSINESS_ACCOUNT_OWNER;
        $BankData->bankName = MODULE_DHL_BUSINESS_BANK_NAME;
        $BankData->iban = MODULE_DHL_BUSINESS_IBAN;
        $BankData->bic = MODULE_DHL_BUSINESS_BIC;
        $BankData->note1 = $this->data['reference'];
      }
      
      // insurance
      if ($this->insurance > 0) {
        $Service->AdditionalInsurance = array(
          'active' => '1',
          'insuranceAmount' => $this->insurance_array[$this->insurance]
        );
      }
      
      // avs
      if ($this->avs > 0) {
        $Service->VisualCheckOfAge['active'] = '1';
        $Service->VisualCheckOfAge['type'] = 'A'.$this->avs;
      }
      
      // personal
      if ($this->personal > 0) {
        $Service->NamedPersonOnly['active'] = '1';
      }
      
      // no neighbour
      if ($this->no_neighbour > 0) {
        $Service->NoNeighbourDelivery['active'] = '1';
      }

      // parcel outlet
      if ($this->parcel_outlet > 0) {
        $Service->ParcelOutletRouting['active'] = '1';
        $Service->ParcelOutletRouting['details'] = $customers_data['email_address'];
        if ($this->notification < 1) {
          $Service->ParcelOutletRouting['details'] = $this->info['email_address'];
        }
      }

      // signed
      if ($this->data['product'] == 'V01PAK' 
          && $this->signed > 0
          )
      {
        $Service->signedForByRecipient['active'] = '1';
      }

      // bulky
      if ($this->bulky > 0) {
        $Service->BulkyGoods['active'] = '1';
      }
      
      if ($this->ident > 0) {        
        $Ident = new stdClass();
        $Ident->surname = $this->order->delivery['lastname'];
        $Ident->givenName = $this->order->delivery['firstname'];
        $Ident->dateOfBirth = date('Y-m-d', strtotime($this->dob));
        $Ident->minimumAge = 'A'.$this->ident;
        
        $Service->IdentCheck = new stdClass();
        $Service->IdentCheck->active = '1';
        $Service->IdentCheck->Ident = $Ident;
      }
      
      // endorsement
      if (in_array($this->data['product_code'], array('53', '66'))) {
        $Service->Endorsement['active'] = '1';
        $Service->Endorsement['type'] = $this->endorsement;
      }
      
      // ShipmentDetails
      $ShipmentDetails = new stdClass();
      $ShipmentDetails->product = $this->data['product'];
      $ShipmentDetails->accountNumber = $this->data['ekp'].$this->data['product_code'].((isset($this->data['account'][$customers_data['country_iso_2']])) ? $this->data['account'][$customers_data['country_iso_2']][$this->data['product_type']] : $this->data['account']['WORLD'][$this->data['product_type']]);
      $ShipmentDetails->shipmentDate = date('Y-m-d');
      $ShipmentDetails->customerReference = $this->data['reference'];
      
      if ($this->retoure > 0) {
        $ShipmentDetails->returnShipmentAccountNumber = $this->data['ekp'].'07'.((isset($this->data['account'][$customers_data['country_iso_2']])) ? $this->data['account'][$customers_data['country_iso_2']]['RT'] : $this->data['account']['WORLD']['RT']);
        $ShipmentDetails->returnShipmentReference = $this->data['reference'];
      }
      
      $ShipmentDetails->Service = $Service;
      if (isset($BankData) && is_object($BankData)) {
        $ShipmentDetails->BankData = $BankData;
      }
    
      // ShipmentItem
      $ShipmentItem = new stdClass();
      $ShipmentItem->weightInKG = $this->data['weight'];
      $ShipmentDetails->ShipmentItem = $ShipmentItem;
      
      // Shipment
      $Shipment = new stdClass();
      $Shipment->ShipmentDetails = $ShipmentDetails;
      $Shipment->Shipper = $Shipper;    
      $Shipment->Receiver = $Receiver;
      if ($this->retoure > 0) {
        $Shipment->ReturnReceiver = $ReturnReceiver;
      }
      
      $tax_rate = 0;                        
      $tax_rates_query = xtc_db_query("SELECT tr.* 
                                         FROM " . TABLE_COUNTRIES . " c
                                         JOIN " . TABLE_ZONES_TO_GEO_ZONES . " ztgz 
                                              ON c.countries_id = ztgz.zone_country_id
                                         JOIN " . TABLE_TAX_RATES . " tr 
                                              ON tr.tax_zone_id = ztgz.geo_zone_id
                                        WHERE c.countries_iso_code_2 = '".xtc_db_input($customers_data['country_iso_2'])."'
                                     GROUP BY ztgz.zone_country_id");
      while ($tax_rates = xtc_db_fetch_array($tax_rates_query, true)) {
        $tax_rate += $tax_rates['tax_rate'];
      }
      
      if ($tax_rate == 0) {
        $Shipment->ExportDocument = $this->buildExportDocument();
        $Shipment->ShipmentDetails->Service->Endorsement['active'] = '1';
        $Shipment->ShipmentDetails->Service->Endorsement['type'] = 'IMMEDIATE';
      }
      
      // ShipmentOrder
      $ShipmentOrder = new stdClass();
      $ShipmentOrder->PrintOnlyIfCodeable['active'] = $this->codeable;
      $ShipmentOrder->sequenceNumber = MODULE_DHL_BUSINESS_PREFIX.$this->data['orders_id'];
      $ShipmentOrder->Shipment = $Shipment;
    
      // request
      $request = new stdClass();
      $request->Version = $this->buildVersion();
      $request->ShipmentOrder = $ShipmentOrder;
      $request->labelResponseType = 'URL';

      return $request;
    }


    private function buildCustomersData() {
      $street_address = $this->parse_street_address($this->order->delivery['street_address']);
      
      $customers_data = array(
        'name' => $this->order->delivery['name'],
        'firstname' => $this->order->delivery['firstname'],
        'lastname' => $this->order->delivery['lastname'],
        'company' => $this->order->delivery['company'],
        'suburb' => $this->order->delivery['suburb'],
        'street_name' => $street_address['street_name'],
        'street_number' => $street_address['street_number'],
        'street_address' => $this->order->delivery['street_address'],
        'postcode' => $this->order->delivery['postcode'],
        'city' => $this->order->delivery['city'],
        'country' => $this->order->delivery['country'],
        'country_iso_2' => $this->order->delivery['country_iso_2'],
        'email_address' => $this->order->customer['email_address'],
        'packstation' => ((stripos($street_address['street_name'], 'packstation') !== false) ? true : false),
        'postfiliale' => ((stripos($street_address['street_name'], 'postfiliale') !== false) ? true : false),
        'postnumber' => '',
        'telephone' => $this->order->customer['telephone'],
      );
      
      if ($customers_data['packstation'] === true || $customers_data['postfiliale'] === true) {        
        if (preg_replace('/[^0-9]/', '', $customers_data['company']) != '') {
          $customers_data['postnumber'] = preg_replace('/[^0-9]/', '', $customers_data['company']);
          $customers_data['company'] = '';
        }
        if (preg_replace('/[^0-9]/', '', $customers_data['suburb']) != '') {
          $customers_data['postnumber'] = preg_replace('/[^0-9]/', '', $customers_data['suburb']);
          $customers_data['suburb'] = '';
        }
      }

      $customers_data = $this->encode_request($customers_data);
      
      // global data
      $this->data['reference'] = $this->order->info['order_id'];
      $this->data['orders_id'] = $this->order->info['order_id'];
      $this->data['orders_status'] = $this->order->info['orders_status_id'];
      $this->data['payment_class'] = $this->order->info['payment_class'];
      $this->data['amount'] = number_format(($this->order->info['pp_total']), 2, '.', '');
      $this->data['currency'] = $this->order->info['currency'];
      $this->data['name'] = $this->order->delivery['name'];
      $this->data['email_address'] = $this->order->customer['email_address'];
      $this->data['weight'] = ($this->weight > 0) ? $this->weight : $this->calculate_weight($this->order->info['order_id']);
      $this->data['product_type'] = 'PK';
      
      // create product code
      switch ($this->order->delivery['country_iso_2']) {
        case 'DE':
          $this->data['product'] = 'V01PAK';
          $this->data['product_code'] = '01';
          if ($this->type == 1) {
            $this->data['product'] = 'V62KP';
            $this->data['product_code'] = '62';
            $this->data['product_type'] = 'KP';
          }
          break;
        default:
          $this->data['product'] = 'V53WPAK';
          $this->data['product_code'] = '53';
          if ($this->type == 1) {
            $this->data['product'] = 'V66WPI';
            $this->data['product_code'] = '66';
            $this->data['product_type'] = 'WP';
          }
          break;
      }
      $this->data = $this->encode_request($this->data);
  
      return $customers_data;
    }


    private function buildShippingDetails($data, $type = 'sender') {
      $Name = new stdClass();
      $Name->name1 = (($data['company'] != '') ? substr($data['company'], 0, 35) : substr(($data['firstname'] . ' ' . $data['lastname']), 0, 35));
      $Name->name2 = (($data['company'] != '') ? substr(($data['firstname'] . ' ' . $data['lastname']), 0, 35) : '');
      
      if (isset($data['suburb'])
          && $data['suburb'] != '' 
          ) 
      {
        if ($Name->name2 == '') {
          $Name->name2 = $data['suburb'];
        } else {
          $Name->name3 = $data['suburb'];
        }
      }

      $Origin = new stdClass();
      $Origin->countryISOCode = $data['country_iso_2'];
      
      $Communication = new stdClass();
      $Communication->phone = $data['telephone'];
      if ($this->notification == 1 && $type != 'sender') {
        $Communication->email = $data['email_address'];
      }
      
      $Address = new stdClass();
      $Address->streetName = $data['street_name'];
      $Address->streetNumber = $data['street_number'];
      if (isset($data['suburb']) && $data['suburb'] != '') {
        //$Address->addressAddition = $data['suburb'];
      }
      $Address->zip = $data['postcode'];
      $Address->city = $data['city'];
      $Address->Origin = $Origin;
  
      if (isset($data['packstation']) && $data['packstation'] === true) {
        $Packstation = new stdClass();
        $Packstation->packstationNumber = preg_replace('/[^0-9]/', '', (($data['street_number'] != '') ? $data['street_number'] : $data['street_name']));
        $Packstation->postNumber = $data['postnumber'];
        $Packstation->zip = $data['postcode'];
        $Packstation->city = $data['city'];
        $Packstation->Origin = $Origin;
      }

      if (isset($data['postfiliale']) && $data['postfiliale'] === true) {
        $Postfiliale = new stdClass();
        $Postfiliale->postfilialNumber = preg_replace('/[^0-9]/', '', (($data['street_number'] != '') ? $data['street_number'] : $data['street_name']));
        $Postfiliale->postNumber = $data['postnumber'];
        $Postfiliale->zip = $data['postcode'];
        $Postfiliale->city = $data['city'];
        $Postfiliale->Origin = $Origin;
      }
      
      switch ($type) {
        case 'sender':
          $shipping_details = new stdClass();
          $shipping_details->Name = $Name;
          $shipping_details->Address = $Address;
          $shipping_details->Communication = $Communication;
          break;
    
        case 'receiver':
          $shipping_details = new stdClass();
          if (isset($Packstation) && is_object($Packstation)) {
            $shipping_details->name1 = (($Name->name2 != '') ? $Name->name2 : $Name->name1);
            $shipping_details->Packstation = $Packstation;
          } elseif (isset($Postfiliale) && is_object($Postfiliale)) {
            $shipping_details->name1 = (($Name->name2 != '') ? $Name->name2 : $Name->name1);
            $shipping_details->Postfiliale = $Postfiliale;
          } else {
            $shipping_details->name1 = $Name->name1;
            $shipping_details->Address = $Address;
            $shipping_details->Address->name2 = $Name->name2;
            if (isset($Name->name3)) {
              $shipping_details->Address->name3 = $Name->name3;
            }
            if ($this->notification == 1) {
              $shipping_details->Communication = $Communication;
            }
          }
          break;
      }
  
      return $shipping_details;
    }


    private function buildExportDocument() {
      $ExportDocument = new stdClass();
      $ExportDocument->exportType = 'COMMERCIAL_GOODS';
      $ExportDocument->placeOfCommital = $this->info['city'];
      $ExportDocument->additionalFee = $this->order->info['pp_shipping'] + $this->order->info['pp_fee'];
      $ExportDocument->customsCurrency = $this->order->info['currency'];
      if ($this->mrn != '') {
        $ExportDocument->MRN = $this->mrn;
      }
      
      $ExportDocument->ExportDocPosition = array();
      $this->order->products = $this->encode_request($this->order->products);
      for ($i=0, $n=count($this->order->products); $i<$n; $i++) {
        $ExportDocument->ExportDocPosition[$i] = new stdClass();
        $ExportDocument->ExportDocPosition[$i]->description = ((isset($this->order->products[$i]['tariff_title']) && $this->order->products[$i]['tariff_title'] != '') ? $this->order->products[$i]['tariff_title'] : $this->order->products[$i]['name']);
        $ExportDocument->ExportDocPosition[$i]->countryCodeOrigin = ((isset($this->order->products[$i]['origin']) && $this->order->products[$i]['origin'] != '') ? $this->order->products[$i]['origin'] : $this->info['country_iso_2']);
        $ExportDocument->ExportDocPosition[$i]->customsTariffNumber = ((isset($this->order->products[$i]['tariff']) && $this->order->products[$i]['tariff'] != '') ? $this->order->products[$i]['tariff'] : '');
        $ExportDocument->ExportDocPosition[$i]->amount = $this->order->products[$i]['quantity'];
        $ExportDocument->ExportDocPosition[$i]->netWeightInKG = $this->order->products[$i]['weight'] + (($this->order->products[$i]['weight'] == 0) ? (double)MODULE_DHL_BUSINESS_WEIGHT_CN23 : 0);
        $ExportDocument->ExportDocPosition[$i]->customsValue = $this->order->products[$i]['price'];
      }
      
      return $ExportDocument;
    }


    private function parse_street_address($street_address) {
      preg_match_all("! [0-9]{1,5}[/ \- 0-9 a-z A-Z]*!m", $street_address, $matches, PREG_SET_ORDER);
      if (count($matches) < 1) {
        preg_match_all("/^([\d][a-z-\/\d]*)|[\s]+([\d][a-z-\/][\d]*)/i", $street_address, $matches, PREG_SET_ORDER);
      }
      if (count($matches) < 1) {
        preg_match_all("![0-9]{1,5}[/ \- 0-9 a-z A-Z]*!m", $street_address, $matches, PREG_SET_ORDER);
      }
      $addr = end($matches);
      
      return array(
        'street_name' => ((isset($addr[0])) ? trim(str_replace(trim($addr[0]), '', $street_address), ', ') : $street_address),
        'street_number' => ((isset($addr[0])) ? trim($addr[0]) : ''),
      );
    }


    public function calculate_weight($order_id) {
      if (!isset($this->order)) {
        $this->order = new order($order_id);
      }
            
      $weight = 0;
      for ($i = 0, $n = count($this->order->products); $i < $n; $i++) {
        $weight += ($this->order->products[$i]['qty'] * $this->order->products[$i]['weight']);
      }
      
      if ($weight > 0) {
        if ((double)SHIPPING_BOX_WEIGHT >= ($weight * (double)SHIPPING_BOX_PADDING / 100)) {
          $weight = $weight + (double)SHIPPING_BOX_WEIGHT;
        } else {
          $weight = $weight + ($weight * (double)SHIPPING_BOX_PADDING / 100);
        }
      } else {
        $weight = (double)SHIPPING_BOX_WEIGHT;
      }
      
      if ($weight == 0) {
        $weight = 1;
      }
    
      return $weight;
    }


    private function encode_message($string) {
      return decode_utf8(encode_utf8($string, 'ISO-8859-1', true));
    }
    
    
    private function encode_request($array) {
      foreach ($array as $key => $value) {
        if (is_array($value)) {
          $array[$key] = $this->encode_request($value);
        } else {
          $array[$key] = ((!is_bool($value)) ? encode_utf8(decode_htmlentities($value), $_SESSION['language_charset'], true) : $value);
        }
      }
    
      return $array;
    }

  }
