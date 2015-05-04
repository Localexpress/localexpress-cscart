<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2015 Hans Broeke - Boxture - Orange-Rhino                          *
 *                                                                          *
 *                                                                          *
 ****************************************************************************/

namespace Tygh\Shippings\Services;

use Tygh\Shippings\IService;
use Tygh\Registry;

/**
 * Canada POST shipping service
 */
class Localexpress implements IService
{
   private $_allow_multithreading = true;
   
   public function prepareData($shipping_info)
   {
      $this->_shipping_info = $shipping_info;
   }

   public function processResponse($response)
   {
      $return = array(
         'cost' => false,
         'error' => false,
         'delivery_time' => false,
      );
      if(empty($response['rate'])){
         $response['rate']          = $_SESSION['boxtureHack']['rate'];
         $response['delivery_time'] = $_SESSION['boxtureHack']['delivery_time'];
      }
      
      if (isset($response['rate'])) {
         $return['cost'] = $response['rate'];

         if (isset($response['delivery_time'])) {
            $return['delivery_time'] = $response['delivery_time'];
         }
      } else {
         $return['error'] = $this->processErrors($response);
      }
      return $return;
   }
    
   public function getRequestData()
   {
      $weight_data   = fn_expand_weight($this->_shipping_info['package_info']['W']);

      $shipping_settings   = $this->_shipping_info['service_params'];
      $origination         = $this->_shipping_info['package_info']['origination'];
      $location            = $this->_shipping_info['package_info']['location'];

      $apikey  = !empty($shipping_settings['API_key']) ? $shipping_settings['API_key'] : '';
      $url     = "https://api".((!empty($shipping_settings['test_mode']) && $shipping_settings['test_mode'] == 'Y') ? "-qa" : "-new").".boxture.com";
      $weight        = $weight_data['full_pounds'];
      if (in_array($origination['country'], array('US', 'DO','PR'))) {
         $weight_unit = 'LBS';
         $measure_unit = 'IN';
      } else {
         $weight_unit = 'KGS';
         $measure_unit = 'CM';
         $weight = $weight * 0.4536;
      }

      $length = !empty($shipping_settings['length']) ? $shipping_settings['length'] : '0';
      $width = !empty($shipping_settings['width']) ? $shipping_settings['width'] : '0';
      $height = !empty($shipping_settings['height']) ? $shipping_settings['height'] : '0';


      if($origination['country'] == 'NL' && $location['country'] == 'NL'){
         $api_boxture_loc   = $this->_sentJSON("https://api.boxture.com/convert_address.php",json_encode(array("postal_code" => $location['zipcode'],"address"=> $location['address'],"iso_country_code"=> $location['country'])));
         $json_boxture_loc  = json_decode($api_boxture_loc['result'],true);
      }
      if(!empty($json_boxture_loc['lat'])){
         $return  = $this->_sentBOXJSON($url."/available_features?latitude=".$json_boxture_loc['lat']."&purpose=pickup&longitude=".$json_boxture_loc['lon'],$shipping_settings['API_key']);
         $return  = (($return['info']['http_code']=='404' || $return['info']['http_code']=='422') ? false : true);

         if(!$return){
            return false;
         } else {
            $api_boxture_org   = $this->_sentJSON("https://api.boxture.com/convert_address.php",json_encode(array("postal_code" => $origination['zipcode'],"address"=> $origination['address'],"iso_country_code"=> $origination['country'])));
            $json_boxture_org  = json_decode($api_boxture_org['result'],true);
            $this->_country();
            $json = array(
               "service_type" => "",
               "human_id" => null,
               "state" => null,
               "weight" => $weight,
               "value" => $this->_shipping_info['package_info']['C'],
               "quantity" => 1,
               "insurance" => false,
               "dimensions" => array("width" => $width,"height" => $height,"length" => $length),
               "comments" => "",
               "customer_email" => "",
               "origin" =>  array(
                  "country" => $this->_country[ucwords($origination['country'])],
                  "formatted_address" => $origination['address']."\n".$origination['zipcode']." ".$origination['city']."\n".$this->_country[ucwords($origination['country'])],
                  "administrative_area" => ucwords($origination['state']),
                  "iso_country_code" => ucwords($origination['country']),
                  "locality" => $origination['city'],
                  "postal_code" => $origination['zipcode'],
                  "sub_thoroughfare" => $json_boxture_org['subThoroughfare'],
                  "thoroughfare" => $json_boxture_org['thoroughfare'],
                  "contact" => "",
                  "email" => isset($origination['email']) ? $origination['email'] : "noreply@boxture.com",
                  "mobile" => $origination['phone'],
                  "comments" => "",
                  "company" => "",
               ),
               "destination" => array(
                  "country" => $this->_country[ucwords($location['country'])],
                  "formatted_address" => $location['address'] ."\n".(isset($location['address_2'])? $location['address_2']."\n":"").$location['zipcode']." ".$location['city']."\n".$this->_country[$location['country']],
                  "iso_country_code" => $location['country'],
                  "locality" => $location['city'],
                  "postal_code" => $location['zipcode'],
                  "administrative_area" => ucwords($location['state']),
                  "sub_thoroughfare" => $json_boxture_loc['subThoroughfare'], //state
                  "thoroughfare" => $json_boxture_loc['thoroughfare'],
                  "contact" => $location['firstname']." ".$location['lastname'],
                  "email" => isset($location['email']) ? $location['email'] : "noreply@boxture.com",
                  "mobile" => $location['phone'],
                  "comments" => "",
                  "company" => ""
               ),
               "waybill_nr" => null,
               "vehicle_type" => "bicycle"
            );
            $json = json_encode(array("shipment_quote" => $json));

            $api_local_express_q       = $this->_sentBOXJSON($url."/shipment_quotes",$shipping_settings['API_key'],$json);

            $json_local_express_q      = json_decode($api_local_express_q['result'],true);
            $return = array();
            $return['rate']            = (!empty($shipping_settings['price']) ? $shipping_settings['price'] : $json_local_express_q['shipment_quote']['price']);
            $return['delivery_time']   = str_replace(".000Z","",str_replace("T"," ",$json_local_express_q['shipment_quote']['destination']['service_types'][1]['windows'][0]['start']))." - ".str_replace(".000Z","",str_replace("T"," ",$json_local_express_q['shipment_quote']['destination']['service_types'][1]['windows'][0]['end']));
            $_SESSION['boxtureHack'] = $return;
            return (!empty($return['rate'])) ? $return : false;
         }
      } else {
         return false;
      }
      
   }
   public function processErrors($response)
   {
      return $response;
   }
   private function _sentJSON($url,$post=false,$debug=false){
      $ch         = curl_init($url);     
      curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
      if($post){
         curl_setopt($ch, CURLOPT_POST, 1);
         curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      }
      $result = curl_exec($ch);
      $info = curl_getinfo($ch);
      curl_close($ch);
      return array("info" => $info,"result" => $result);
   }
   private function _sentBOXJson($url,$key,$post=false){
      $ch         = curl_init($url);
      if($post){
         curl_setopt($ch, CURLOPT_POST, 1);
         curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      }
      curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
      curl_setopt($ch, CURLOPT_HTTPHEADER,array(
             'Content-Type: application/json',
             'Accept-Language: en',
             'Connection: Keep-Alive',
             'Authorization: Boxture '.$key));
      $result = curl_exec($ch);
      $info = curl_getinfo($ch);
      curl_close($ch);
      return array("info" => $info,"result" => $result);
   }
   public function getSimpleRates()
   {
      $data    = $this->getRequestData();
      return $data;
   }
   public function allowMultithreading()
   {
      return $this->_allow_multithreading;
   }
   private function _country(){
      $this->_country = array(
         'AF' => 'Afghanistan',
         'AL' => 'Albania',
         'DZ' => 'Algeria',
         'AD' => 'Andorra',
         'AO' => 'Angola',
         'AI' => 'Anguilla',
         'AG' => 'Antigua and Barbuda',
         'AR' => 'Argentina',
         'AM' => 'Armenia',
         'AW' => 'Aruba',
         'AU' => 'Australia',
         'AT' => 'Austria',
         'AZ' => 'Azerbaijan',
         'BS' => 'Bahamas',
         'BH' => 'Bahrain',
         'BD' => 'Bangladesh',
         'BB' => 'Barbados',
         'BY' => 'Belarus',
         'BE' => 'Belgium',
         'BZ' => 'Belize',
         'BJ' => 'Benin',
         'BM' => 'Bermuda',
         'BT' => 'Bhutan',
         'BO' => 'Bolivia',
         'BA' => 'Bosnia-Herzegovina',
         'BW' => 'Botswana',
         'BR' => 'Brazil',
         'VG' => 'British Virgin Islands',
         'BN' => 'Brunei Darussalam',
         'BG' => 'Bulgaria',
         'BF' => 'Burkina Faso',
         'MM' => 'Burma',
         'BI' => 'Burundi',
         'KH' => 'Cambodia',
         'CM' => 'Cameroon',
         'CA' => 'Canada',
         'CV' => 'Cape Verde',
         'KY' => 'Cayman Islands',
         'CF' => 'Central African Republic',
         'TD' => 'Chad',
         'CL' => 'Chile',
         'CN' => 'China',
         'CX' => 'Christmas Island (Australia)',
         'CC' => 'Cocos Island (Australia)',
         'CO' => 'Colombia',
         'KM' => 'Comoros',
         'CG' => 'Congo (Brazzaville),Republic of the',
         'ZR' => 'Congo, Democratic Republic of the',
         'CK' => 'Cook Islands (New Zealand)',
         'CR' => 'Costa Rica',
         'CI' => 'Cote d\'Ivoire (Ivory Coast)',
         'HR' => 'Croatia',
         'CU' => 'Cuba',
         'CY' => 'Cyprus',
         'CZ' => 'Czech Republic',
         'DK' => 'Denmark',
         'DJ' => 'Djibouti',
         'DM' => 'Dominica',
         'DO' => 'Dominican Republic',
         'TP' => 'East Timor (Indonesia)',
         'EC' => 'Ecuador',
         'EG' => 'Egypt',
         'SV' => 'El Salvador',
         'GQ' => 'Equatorial Guinea',
         'ER' => 'Eritrea',
         'EE' => 'Estonia',
         'ET' => 'Ethiopia',
         'FK' => 'Falkland Islands',
         'FO' => 'Faroe Islands',
         'FJ' => 'Fiji',
         'FI' => 'Finland',
         'FR' => 'France',
         'GF' => 'French Guiana',
         'PF' => 'French Polynesia',
         'GA' => 'Gabon',
         'GM' => 'Gambia',
         'GE' => 'Georgia, Republic of',
         'DE' => 'Germany',
         'GH' => 'Ghana',
         'GI' => 'Gibraltar',
         'GB' => 'Great Britain and Northern Ireland',
         'GR' => 'Greece',
         'GL' => 'Greenland',
         'GD' => 'Grenada',
         'GP' => 'Guadeloupe',
         'GT' => 'Guatemala',
         'GN' => 'Guinea',
         'GW' => 'Guinea-Bissau',
         'GY' => 'Guyana',
         'HT' => 'Haiti',
         'HN' => 'Honduras',
         'HK' => 'Hong Kong',
         'HU' => 'Hungary',
         'IS' => 'Iceland',
         'IN' => 'India',
         'ID' => 'Indonesia',
         'IR' => 'Iran',
         'IQ' => 'Iraq',
         'IE' => 'Ireland',
         'IL' => 'Israel',
         'IT' => 'Italy',
         'JM' => 'Jamaica',
         'JP' => 'Japan',
         'JO' => 'Jordan',
         'KZ' => 'Kazakhstan',
         'KE' => 'Kenya',
         'KI' => 'Kiribati',
         'KW' => 'Kuwait',
         'KG' => 'Kyrgyzstan',
         'LA' => 'Laos',
         'LV' => 'Latvia',
         'LB' => 'Lebanon',
         'LS' => 'Lesotho',
         'LR' => 'Liberia',
         'LY' => 'Libya',
         'LI' => 'Liechtenstein',
         'LT' => 'Lithuania',
         'LU' => 'Luxembourg',
         'MO' => 'Macao',
         'MK' => 'Macedonia, Republic of',
         'MG' => 'Madagascar',
         'MW' => 'Malawi',
         'MY' => 'Malaysia',
         'MV' => 'Maldives',
         'ML' => 'Mali',
         'MT' => 'Malta',
         'MQ' => 'Martinique',
         'MR' => 'Mauritania',
         'MU' => 'Mauritius',
         'YT' => 'Mayotte (France)',
         'MX' => 'Mexico',
         'MD' => 'Moldova',
         'MC' => 'Monaco (France)',
         'MN' => 'Mongolia',
         'MS' => 'Montserrat',
         'MA' => 'Morocco',
         'MZ' => 'Mozambique',
         'NA' => 'Namibia',
         'NR' => 'Nauru',
         'NP' => 'Nepal',
         'NL' => 'Netherlands',
         'AN' => 'Netherlands Antilles',
         'NC' => 'New Caledonia',
         'NZ' => 'New Zealand',
         'NI' => 'Nicaragua',
         'NE' => 'Niger',
         'NG' => 'Nigeria',
         'KP' => 'North Korea (Korea, Democratic People\'s Republic of)',
         'NO' => 'Norway',
         'OM' => 'Oman',
         'PK' => 'Pakistan',
         'PA' => 'Panama',
         'PG' => 'Papua New Guinea',
         'PY' => 'Paraguay',
         'PE' => 'Peru',
         'PH' => 'Philippines',
         'PN' => 'Pitcairn Island',
         'PL' => 'Poland',
         'PT' => 'Portugal',
         'QA' => 'Qatar',
         'RE' => 'Reunion',
         'RO' => 'Romania',
         'RU' => 'Russia',
         'RW' => 'Rwanda',
         'SH' => 'Saint Helena',
         'KN' => 'Saint Kitts (St. Christopher and Nevis)',
         'LC' => 'Saint Lucia',
         'PM' => 'Saint Pierre and Miquelon',
         'VC' => 'Saint Vincent and the Grenadines',
         'SM' => 'San Marino',
         'ST' => 'Sao Tome and Principe',
         'SA' => 'Saudi Arabia',
         'SN' => 'Senegal',
         'YU' => 'Serbia-Montenegro',
         'SC' => 'Seychelles',
         'SL' => 'Sierra Leone',
         'SG' => 'Singapore',
         'SK' => 'Slovak Republic',
         'SI' => 'Slovenia',
         'SB' => 'Solomon Islands',
         'SO' => 'Somalia',
         'ZA' => 'South Africa',
         'GS' => 'South Georgia (Falkland Islands)',
         'KR' => 'South Korea (Korea, Republic of)',
         'ES' => 'Spain',
         'LK' => 'Sri Lanka',
         'SD' => 'Sudan',
         'SR' => 'Suriname',
         'SZ' => 'Swaziland',
         'SE' => 'Sweden',
         'CH' => 'Switzerland',
         'SY' => 'Syrian Arab Republic',
         'TW' => 'Taiwan',
         'TJ' => 'Tajikistan',
         'TZ' => 'Tanzania',
         'TH' => 'Thailand',
         'TG' => 'Togo',
         'TK' => 'Tokelau (Union) Group (Western Samoa)',
         'TO' => 'Tonga',
         'TT' => 'Trinidad and Tobago',
         'TN' => 'Tunisia',
         'TR' => 'Turkey',
         'TM' => 'Turkmenistan',
         'TC' => 'Turks and Caicos Islands',
         'TV' => 'Tuvalu',
         'UG' => 'Uganda',
         'UA' => 'Ukraine',
         'AE' => 'United Arab Emirates',
         'UY' => 'Uruguay',
         'UZ' => 'Uzbekistan',
         'VU' => 'Vanuatu',
         'VA' => 'Vatican City',
         'VE' => 'Venezuela',
         'VN' => 'Vietnam',
         'WF' => 'Wallis and Futuna Islands',
         'WS' => 'Western Samoa',
         'YE' => 'Yemen',
         'ZM' => 'Zambia',
         'ZW' => 'Zimbabwe'
      );
      
   }
}
