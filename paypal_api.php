<?php
require_once("xlsxwriter.class.php");
require_once("SimpleXLSX.php");

class Paypal {
   /**
    * Last error message(s)
    * @var array
    */
   protected $_errors = array();

   /**
    * API Credentials
    * Use the correct credentials for the environment in use (Live / Sandbox)
    * @var array
    */
   protected $_credentials;

   /**
    * API endpoint
    * Live - https://api-3t.paypal.com/nvp
    * Sandbox - https://api-3t.sandbox.paypal.com/nvp
    * @var string
    */
   protected $_endPoint = 'https://api-3t.paypal.com/nvp';

   /**
    * API Version
    * @var string
    */
   protected $_version = '74.0';

   /**
    * Make API request
    *
    * @param string $method string API method to request
    * @param array $params Additional request parameters
    * @return array / boolean Response array / boolean false on failure
    */
    function __construct($creds)
    {
      $this -> _credentials = $creds;
    }
   public function request($method,$params = array()) {
      $this -> _errors = array();
      if( empty($method) ) { //Check if API method is not empty
         $this -> _errors = array('API method is missing');
         return false;
      }

      //Our request parameters
      $requestParams = array(
         'METHOD' => $method,
         'VERSION' => $this -> _version
      ) + $this -> _credentials;

      //Building our NVP string

      $request = http_build_query($requestParams + $params);

      //cURL settings
      $curlOptions = array (
         CURLOPT_URL => $this -> _endPoint,
         // CURLOPT_VERBOSE => 1,
         // CURLOPT_SSL_VERIFYPEER => true,
         // CURLOPT_SSL_VERIFYHOST => 2,
         // CURLOPT_CAINFO => dirname(__FILE__) . '/cacert.pem', //CA cert file
         CURLOPT_RETURNTRANSFER => 1,
         CURLOPT_POST => 1,
         CURLOPT_POSTFIELDS => $request
);

      $ch = curl_init();
      
      // curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
      // curl_setopt($ch, CURLOPT_PROXY, '192.168.192.1:3211');
      curl_setopt_array($ch,$curlOptions);

      //Sending our request - $response will hold the API response
      $response = curl_exec($ch);

      //Checking for cURL errors
      if (curl_errno($ch)) {
         $this -> _errors = curl_error($ch);
         curl_close($ch);
         return false;
         //Handle errors
      } else  {
         curl_close($ch);
         $responseArray = array();
         $res=urldecode($response);
         $pts=explode("&", $res);
         foreach($pts as $val)
         {
           $pts2=explode("=", $val);
           $out["$pts2[0]"]=$pts2[1];
         }
         return $out;
      }
   }
}

require_once "accs.php";
$rows=array();
$data=array();
if(file_exists("transfers.xlsx"))
  $rows=SimpleXLSX::parse('transfers.xlsx')->rowsEx();
foreach($rows as $row)
{
  $exists["{$row[5]["value"]}"]=1;
  $arr=array();
  foreach($row as $cell)
    $arr[]=$cell["value"];
  $data[]=$arr;
}
date_default_timezone_set("America/Mexico_City");
foreach($accounts as $acc)
{
   $api=new Paypal($acc);
   $cur_acc=$acc["account_name"];
   $res=$api->request("TransactionSearch");
   for($i=0;$i<100;$i++)
   {
      if($res["L_TYPE$i"]!='Transfer')
         continue;
      if(isset($exists["{$res["L_TRANSACTIONID$i"]}"]))
         continue;
      $prev=$i-1;
      $currency=$res["L_CURRENCYCODE$prev"];
      $rate=$res["L_AMT$i"]/$res["L_AMT$prev"];
      $amount=$res["L_AMT$i"]*-1;
      $date=date("Y/m/d", strtotime($res["L_TIMESTAMP$i"]));
      $time=date("H:i:s", strtotime($res["L_TIMESTAMP$i"]));
      $arr=array($date,
         $time,
         $amount,
         $currency,
         $rate,
         $res["L_TRANSACTIONID$i"],
         $cur_acc
      );
      $data[]=$arr;
   }
   sleep(60);
}
usort($data, "sort_by_date");
$writer = new XLSXWriter();
$writer->writeSheet($data);
$writer->writeToFile('transfers.xlsx');
function sort_by_date($val1, $val2)
{
   $date1=strtotime($val1[0]." ".$val1[1]);
   $date2=strtotime($val2[0]." ".$val2[1]);
   return $date1>$date2;
}