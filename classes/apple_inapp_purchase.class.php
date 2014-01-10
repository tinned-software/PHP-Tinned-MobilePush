<?php
/**
 * Apple InApp Purchase verification
 *
 * This class is used to verify apples In App purchase. The recipient ID 
 * receidved from the apple device will be verified.
 *
 * @author Gerhard Steinbeis (info [at] tinned-software [dot] net)
 * @copyright Copyright (c) 2010
 * @version 0.2
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 *
 * @todo template todo item
 *
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../Submodules/PHP-Tinned-Core/classes/main.class.php');



/**
 * Apple InApp Purchase verification
 * 
 * This class is used to verify apples In App purchase. The recipient ID 
 * receidved from the apple device will be verified.
 * 
 * ERROR CODES:<br>
 *    101 ... initialised class with wrong system type.<br>
 *    102 ... Error connecting to API server.<br>
 *    103 ... Error wront response data returned from API server.<br>
 *    104 ... Recipient data not valid.<br>
 *    105 ... Method parameter of unexpected types.<br>
 * 
 * @package framework
 * @subpackage mobile-service
 * 
**/
class Apple_Inapp_Purchase extends Main
{
    ////////////////////////////////////////////////////////////////////////////
    // PROPERTIES of the class
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * @ignore
     * To enable internal logging. This will send log messages of the class to 
     * the browser. Used to debug the class.
     * @access public
     * @var integer
    **/
    public $dbg_intern                  = 0;
    
    // variable to hold the type (producton or developer)
    private $system_type                = NULL;
    
    // variable to hold last error message
    private $last_error                 = array();
    
    // variables to hold API gateway addresses
    private $api_gateway_developer      = 'https://sandbox.itunes.apple.com/verifyReceipt';
    private $api_gateway_production     = 'https://buy.itunes.apple.com/verifyReceipt';
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // CONSTRUCTOR & DESCTRUCTOR methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    /**
     * Constructor
     * 
     * @access public
     * 
     * @param $dbg_level Debug log level
     * @param $debug_object Debug object to send log messages to
    **/
    public function __construct ($type = 'production', $dbg_level = 0, &$debug_object = null)
    {
        // initialize parent class MainClass
        parent::Main_init($dbg_level, $debug_object);
        $this->dbg_level = $dbg_level;
        $this->debug_object = &$debug_object;
        
        date_default_timezone_set("UTC");
        
        
        // save the type of push notification
        if($type === 'production' || $type === 'developer')
        {
            $this->system_type = $type;
        }
        else
        {
            $error_info = array('code' => 101, 'text' => 'initialised class with wrong system type.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
        }
    }
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // SET methods to set class Options
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // GET methods to get class Options
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // PRIVATE methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // PROTECTED methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // PUBLIC methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    /**
     * Short description
     * 
     * This method ... Loooong description of the method
     * 
     * @access public
     * 
     * @param string $msg_text Text message
     * @param int $add Additional parameter
    **/
    public function verify_receipt_data($receipt_data)
    {
        
        // get the API url according to the system type
        if($this->system_type === 'developer')
        {
            $gateway = $this->api_gateway_developer;
        }
        else
        {
            $gateway = $this->api_gateway_production;
        }
        
        
        // build the post data
        $postData = json_encode(
            array('receipt-data' => $receipt_data)
        );
        
        // create the cURL request
        $curl_connect = curl_init();
        curl_setopt($curl_connect, CURLOPT_URL, $gateway);
        curl_setopt($curl_connect, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_connect, CURLOPT_POST, true);
        curl_setopt($curl_connect, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl_connect, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl_connect, CURLOPT_SSL_VERIFYPEER, FALSE);
        
        // execute the cURL request and fetch response data
        $response = curl_exec($curl_connect);
        $errno    = curl_errno($curl_connect);
        $errmsg   = curl_error($curl_connect);
        curl_close($curl_connect);
        
        // log the returned data
        parent::debug2("API server returned ErrNo : $errno");
        parent::debug2("API server returned ErrMsg: $errmsg");
        parent::debug2("API server returned Response: \n$response");
        
        
        // ensure the request succeeded
        if ($errno != 0)
        {
            $error_info = array('code' => 102, 'text' => 'Error connecting to API server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        
        
        // parse the response data
        $data = json_decode($response, TRUE);
        
        // ensure response data was a valid JSON string
        if (is_array($data) === FALSE)
        {
            $error_info = array('code' => 103, 'text' => 'Error wront response data returned from API server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        
        // ensure the expected data is present
        if (isset($data['status']) === FALSE || $data['status'] != 0)
        {
            $error_info = array('code' => 104, 'text' => 'Recipient data not valid.');
            parent::debug($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        
        
        
        // build the response array with the returned data
        $return_data['quantity']       = $data['receipt']['quantity'];
        $return_data['product_id']     = $data['receipt']['product_id'];
        $return_data['transaction_id'] = $data['receipt']['transaction_id'];
        $return_data['purchase_date']  = $data['receipt']['purchase_date'];
        $return_data['app_item_id']    = $data['receipt']['app_item_id'];
        $return_data['bid']            = $data['receipt']['bid'];
        $return_data['bvrs']           = $data['receipt']['bvrs'];
        
        // return the result data
        return $return_data;
        
        
        
    }
    
    
    
    /**
     * Get last error
     * 
     * This method is used to get the last error accoured. The last_error is 
     * not reset after this method is unless the $clear is set to TRUE. The 
     * last error will return NULL if no error occured, or an array with two 
     * fields. "code" will contain the code of the error and "text" will 
     * contain a short description of the error.
     * 
     * @access public
     * 
     * @param $clear Set to TRUE to clear the errors after returning it.
     * @param $all_errors Set to TRUE to return a list of all errors.
     * @return mixed A array with "code" and "test"
    **/
    public function get_last_error($clear = FALSE, $all_errors = FALSE)
    {
        // check if an error appeared
        if(count($this->last_error) >= 1)
        {
            if($all_errors === TRUE)
            {
                $error = $this->last_error;
            }
            else
            {
                $error_count = count($this->last_error) - 1;
                $error = $this->last_error[$error_count];
            }
        }
        else
        {
            $error = NULL;
        }
        
        // check if the error list should be cleared
        if($clear === FALSE)
        {
            return $error;
        }
        else
        {
            $this->last_error = array();
            return $error;
        }
    }
    
    
    
}


?>