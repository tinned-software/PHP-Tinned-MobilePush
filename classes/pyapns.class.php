<?php
/**
 * @author Apostolos Karakousis
 * @author Gerhard Steinbeis (info [at] tinned-software [dot] net)
 * @copyright Copyright (c) 2012 based on APNS & C2DM classes by Gerhard Steinbeis
 * @version 0.0.13
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 *
 * Apple Push Notification Class (Apple APNS via pyAPNS)
 * 
 * This class provides access to the Apple Push Notification Service (APNs).
 * The service is available for Apple mobile deviced with iOS (IPhoneOS).
 * The specifics of the supported devices are to be found at the service used
 * https://github.com/samuraisam/pyapns.
 * 
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/main.class.php');
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/xml_manager.class.php');
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/functions/json_encode.php');
include_once(dirname(__FILE__).'/mobile_push.interface.php');


/**
 * Apple Push Notification Class (Apple APNS)
 * 
 * This class provides access to the Apple Push Notification Service (APNs).
 * The service is available for Apple mobile deviced with iOS (IPhoneOS) 3.0 or
 * higher. Not all features are available in iOS 3.0. Full set of features is
 * available with iOS 4.0 and higher.
 * 
 * 
 * ERROR CODES:<br>
 *    101 ... initialised class with wrong system type.<br>
 *    102 ... app_id not specified or incorrectly formated<br>
 *    103 ... Wrong type parameter value.<br>
 *    104 ... Certificate has wrong permission (644 expected)<br>
 *    105 ... Method parameter of unexpected types.<br>
 *    106 ... Message data not defined or recipient missing.<br>
 *    107 ... Error connecting to API server.<br>
 *    108 ... Error sending data to API server.<br>
 *    109 ... Recipient OS not supported or not set.<br>
 *    110 ... Maximum allowed message size exceeded (exceeded by X bytes)<br>
 *    111 ... API reported an error, see get_api_last_error() and the 2xx error codes<br>
 *    112 ... helper object not set or not usable
 * PUSH API Error Codes<br>
 *    200 ... Push Gateway reports a general error, not yet specified in this class<br>
 *    201 ...  NOT USED -> Push Gateway reports that the device is no longer active
 *    202 ... Push Gateway reports that the app_id specified has not been provisioned (error 404)
 *    203 ... Push Gateway reports APNS server timeout (error 500)
 *    
 * @package framework
 * @subpackage mobile-service
 * 
**/
class PyAPNS extends Main implements Mobile_Push
{
    ////////////////////////////////////////////////////////////////////////////
    // CONSTANTS of the class
    ////////////////////////////////////////////////////////////////////////////
    
    // not used/padding from apns class
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 
    // 

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
    
    // variable to hold the system type (producton or developer)
    private $system_type                = NULL;
    
    // not used/padding
    // 
    
    // variable to hold database object
    private $db_object                  = NULL;
    private $db_table                   = NULL;
    
    // variables to hold certificate file paths
    //private $security_cert_developer    = NULL;
    //private $security_cert_production   = NULL;
    
    // variable for the service authentication
    // 
    // 
    
    // variables to hold API gateway addresses
    private $api_gateway_developer      = 'http://127.0.0.1:7077';
    private $api_gateway_production     = 'http://127.0.0.1:7077';
    
    // 
    private $_app_id                    = NULL;
    private $api_last_error             = NULL;
    
    // variable to hold the API feedback service url
    private $api_feedback_developer     = '';
    private $api_feedback_production    = '';
    
    // define if the table should be searched (and cleaned if required) for douplicate entries.
    private $check_unique_devices       = FALSE;
    
    // define variable to store message details
    private $message_content            = NULL;
    private $message_recipient          = NULL;
    private $message_target_os          = 'aps';
    
    // set the maximum message length
    private $message_max_length         = 256;
    private $message_truncate           = TRUE;
    
    // keep connections open between requests
    private $keepalive                  = FALSE;
    private $curl_handler               = NULL;
    
    // Timeout time in seconds
    private $timeout_time               = 10;
    private $_xml_manager               = NULL;
    // not used/padding
    // 
    // 
    
    // 
    // 
    
    // internal store of payload -> json encoded
    private $_cache_payload_json      = "";
    // flag to mark recalculation of the cache or not
    private $_cache_is_valid          = FALSE;
    // 
    // 
    // 
    // 
    // 
    // 
    
    // 
    // 


    ////////////////////////////////////////////////////////////////////////////
    // CONSTRUCTOR & DESCTRUCTOR methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    /**
     * Constructor for the class
     * 
     * This method is used to create the class. It takes the debug level and
     * the debug object as parameter for logging. Additional, it takes the system
     * type "developer" or "production" as parameter.
     * 
     * @access public
     * 
     * @param string  $type         The Push type "developer" or "production" (default: production)
     * @param integer $dbg_level    Debug log level
     * @param mixed   $debug_object Debug object to send log messages to
    **/
    public function __construct ($type = 'production', $dbg_level = 0, &$debug_object = null)
    {
        // initialize parent class MainClass
        parent::Main_init($dbg_level, $debug_object, 1);
        $this->dbg_level = $dbg_level;
        $this->debug_object = &$debug_object;
        
        date_default_timezone_set("UTC");
        
        
        $required_functions = array('json_encode','json_decode');
        $required_classes = array();
        $check_prerequisites = parent::check_prerequisites($required_functions, $required_classes);
        
        // set the error variable
        $error = ($check_prerequisites !== TRUE) ? TRUE : FALSE;
        
        //
        // check if missing functions can be disabled in the class
        // if so we disable them and set the error back to FALSE
        
        if($error === TRUE)
        {
            $this->errnr = 105;
            $this->errtxt = 'Internal Error / required classes or functions missing';
            return FALSE;
        }
        
        // save the type of push notification
        if($type === 'production' || $type === 'developer')
        {
            $this->system_type = $type;
        }
        else
        {
            $error_info = array('code' => 101, 'text' => 'initialised class with wrong system type.');
            parent::error($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
        }
    }
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // SET methods to set class Options
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    /**
     * Set the API credentials
     * 
     * This method is used to Set the API credentials to interact with the
     * service. The $type parameter can be "production" or "developer".
     * The $file_path is used to set the API certificate.
     * The $unused_parameter is not used in this class. It is available for
     * compatibility mode.
     * 
     * @access public
     * 
     * @param  string  $type              The type of system (production, developer)
     * @param  string  $app_id            The pyAPNS app id to which to send messages
     * @param  string  $pyapns_url        The pyAPNS server URL
     * @param  string  $unused_parameter2 A unused parameter for compatibility
     * @return boolean                    TRUE is returned on success and FALSE otherwhise
    **/
    public function set_api_credentials($type, $app_id, $pyapns_url, $unused_parameter2 = NULL)
    {
        parent::debug2(__FUNCTION__.' called with type='.$type.', app_id='.$app_id.', pyapns_url='.$pyapns_url.', unused_parameter2='.$unused_parameter2);
        
        if (is_string($app_id) === TRUE && strlen($app_id)>0)
        {
            $this->_app_id = $app_id;
        }
        else
        {
            $error_info = array('code' => 102, 'text' => 'app_id not specified or incorrectly formated.');
            parent::error($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        
        // check the type
        if($type === 'production')
        {
            $this->api_gateway_production     = $pyapns_url;
            return TRUE;
        }
        else if($type === 'developer')
        {
            $this->api_gateway_developer      = $pyapns_url;
            return TRUE;

            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
            //
        }
        else
        {
            $error_info = array('code' => 103, 'text' => 'Wrong type parameter value.');
            parent::error($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        
        // return false
        return FALSE;
    }
    
    
    /**
     * Enables or disables keepalive connections.
     *
     * Keep alive can speed up performance if you send more than one message at once.
     *
     * @param bool $enabled TRUE if keep alive should be enabled, FALSE if it should be disabled.
     *
     * @return boolean TRUE is returned on success and FALSE otherwise
    **/
    public function set_keepalive($enabled)
    {
        if(empty($enabled) != empty($this->keepalive))
        {
            $this->keepalive = $enabled ? TRUE : FALSE;
            if(isset($this->socket))
            {
                curl_close($this->curl_handler);
                $this->curl_handler = NULL;
            }
        }
        return TRUE;
    }
    
    /**
     * Sets the socket timeout time in seconds.
     *
     * @param int $timeout_time The timeout time in seconds
     *
     * @return boolean TRUE is returned on success and FALSE otherwise
    **/
    public function set_timeout_time($timeout_time)
    {
        $this->timeout_time = $timeout_time;
        return TRUE;
    }
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // GET methods to get class Options
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    /**
     * the API credentials
     * 
     * This method is used to get the the API credentials for a specific type.
     * The $type can be "developer" or "production"
     * 
     * @access public
     * 
     * @param string $type The type of certificate to be returned
     * @return string The certificate filename and path
    **/
    public function get_api_credentials($type = 'production')
    {
        parent::debug("Getting credentials for type: ".$type);
        
        // Return credentials according to the type
        $varname = 'certificate_'.$type;
        if(isset($this->$varname) === TRUE)
        {
            // return the API credentials (certificate filename)
            return $this->$varname;
        }
        
        
        // return FALSE to indicate an error
        return FALSE;
    }
    
    
    /**
     * Find out if keep alive is enabled.
     *
     * Keep alive means that the connection to the apple push service is kept
     * open until it is explicitly closed. (Or implicitly on script end, of course.)
     *
     * @return bool indicating if keep alive is enabled
     *
     * @see set_keepalive()
    **/
    public function get_keepalive()
    {
        return $this->keepalive;
    }
    
    
    
    /**
     * Returns the socket timeout time in seconds
     *
     * @return int representing the timeout time in seconds
    **/
    public function get_timeout_time()
    {
        return $this->timeout_time;
    }
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // PRIVATE methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    /**
     * Get message payload as json encoded.
     * 
     * This method fetches the message payload as json encoded. If the cache
     * is invalid, it will be recalculated in this method only, if it's valid
     * it will be simply returned as is. Invalidation occurs only in the setter
     * methods.
     * 
     * @return string the json encoded message payload
     */
    private function _get_message_content_encoded()
    {
        // re-encode only if necessary
        if ($this->_cache_is_valid === FALSE)
        {
            // parent::debug("json encoded cache is invalid, re-encoding");
            $this->_cache_payload_json = json_encode($this->message_content);
            $this->_cache_is_valid = TRUE;
        }

        // return cached json encoded payload
        return $this->_cache_payload_json;
    }
    
    
    
    /**
     * Check message size
     * 
     * This method is used to check the size of the message and check if it 
     * exceeds the maximum allowed amount of bytes. If the maximum size is 
     * exceeded, a error is returned.
     * 
     * @access private
     * 
     * @return boolean TRUE is returned on success and FALSE is exceeded
    **/
    private function _check_message_size()
    {
        //
        // calculate the amount of chars of the message payload
        //
        
        // get the text content length
        if(is_array($this->message_content['aps']['alert']) === TRUE && array_key_exists('body', $this->message_content['aps']['alert']) === TRUE)
        {
            $text_len = mb_strlen($this->message_content['aps']['alert']['body']);
            parent::debug2("Text-length (L): $text_len");
        }
        else
        {
            $text_len = mb_strlen($this->message_content['aps']['alert']);
            parent::debug2("Text-length (NL): $text_len");
        }
        
        
        // get the payload size
        $payload_size = mb_strlen($this->_get_message_content_encoded());
        // calculate the difference between max-size and payload-size (x chars bigger then allowed)
        $payload_diff = $payload_size - $this->message_max_length;
        // calculate the number of remaining characters after truncating
        $remaining_chars = $text_len - $payload_diff;
        parent::debug2("Payload size of message is $payload_size / ".$this->message_max_length." characters.");
        parent::debug2("Payload exceeded by $payload_diff, remaining characters of message $remaining_chars.");
        
        if($payload_diff >= 1)
        {
            parent::debug2("Check if message should be truncated.");
            
            // Check if truncating is is possible
            if($this->message_truncate === TRUE && $remaining_chars <= 0)
            {
                parent::debug2("Truncate payload by $payload_diff is not possible, remaining message length shorter then 0 ($remaining_chars) characters.");
            }
            
            // Check if the message should be truncated and if it is possible
            if($this->message_truncate === TRUE && $remaining_chars >= 1)
            {
                parent::debug2("PUSH MESSAGE CONTENT: ".print_r($this->message_content['aps'], TRUE));
                
                // check if it is a LOCALISED message
                if(is_array($this->message_content['aps']['alert']) === TRUE && array_key_exists('body', $this->message_content['aps']['alert']) === TRUE)
                {
                    $this->message_content['aps']['alert']['body'] = substr($this->message_content['aps']['alert']['body'], 0, $remaining_chars - 4).' ...';
                    parent::debug2("Truncated payload (L): original-size: $text_len, truncate-size: $payload_diff, remaining-size: $remaining_chars (".mb_strlen($this->message_content['aps']['alert']['body'])."), payload-size: ".mb_strlen($this->_get_message_content_encoded()));
                }
                // check if it is a NON LOCALISED message
                else
                {
                    $this->message_content['aps']['alert'] = substr($this->message_content['aps']['alert'], 0, $remaining_chars - 4).' ...';
                    parent::debug2("Truncated payload (NL): original-size: $text_len, truncate-size: $payload_diff, remaining-size: $remaining_chars (".mb_strlen($this->message_content['aps']['alert'])."), payload-size: ".mb_strlen($this->_get_message_content_encoded()));
                }
            }
            // If the message should not be truncated, or can not be truncated, return with error
            else
            {
                // set error text and code
                $error_info = array('code' => 110, 'text' => 'Maximum allowed message size exceeded (exceeded by '.$payload_diff.' bytes)');
                parent::warning($error_info['code'].': '.$error_info['text']);
                parent::report_error($error_info['code'],$error_info['text']);
                return FALSE;
            }
        }
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Build message payload
     * 
     * This method is used to build the message payload from all the configured 
     * message parameters. It will  all dependencies and return the 
     * message as a string.
     * 
     * @todo cache the payload internally if none of the content has changed
     * @todo strip newlines and whitespace from the payload 
     * @access private
     * 
     * @return The push message payload as string
    **/
    private function _build_message_payload()
    {
        // set up the xmlrpc payload, we define the method, app_id, push_message options to send
        $rpc['methodCall']['methodName']['@value'] = "notify";
        $rpc['methodCall']['params']['param'][] = $this->_xmlrpc_param_encode($this->_app_id);
        $rpc['methodCall']['params']['param'][] = $this->_xmlrpc_param_encode(array($this->message_recipient));
        $rpc['methodCall']['params']['param'][] = $this->_xmlrpc_param_encode(array($this->message_content));
        
        // test for _xml_manager helper class existance
        if(is_object($this->_xml_manager) !== FALSE)
        {
            if(get_class($this->_xml_manager) !== 'XML_Manager')
            {
                parent::report_error(112, "helper object not set or not usable");
                return FALSE;
            }
        }
        else
        {
            parent::report_error(112, "helper object not set or not usable");
            return FALSE;
        }
        
        $payload = $this->_xml_manager->array_to_xmlstring($rpc);
        //
        //
        
        // return the payload
        return $payload;
    }
    
    
    
    /**
     * Service Authentication
     * 
     * This method is not used by this class. It is only listed for 
     * compatibility in line numbers to the other classes for push 
     * notifications. 
     * 
     * @access public
     * 
     * 
     * 
     * 
     * 
     * 
    **/
    function _service_authenticate()
    {
        // 
        // This method is not required for this class
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
    }
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // PROTECTED methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    ////////////////////////////////////////////////////////////////////////////
    // PUBLIC methods of the class
    ////////////////////////////////////////////////////////////////////////////
    
    
    
    /**
     * Set the message recipient
     * 
     * This method is used to set the push message recipient. This method can 
     * be called multiple time to send the message to multiple recipients. The 
     * method accepts the recipient value and the db_field parameter. The 
     * db_field parameter defines the database field where the recipient value 
     * will be searched. When the db_field "push_tocken" is defined (default), 
     * the database is not searched.
     * 
     * @access public
     * 
     * @param string $receipent The recipient value or push token
     * @param string $recipient_os The OS of the recipient device ("aps")
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_message_recipient($recipient, $recipient_os = 'aps')
    {
        //invalidate the cache
        $this->_cache_is_valid = FALSE;
        if(preg_match('/^[a-zA-Z0-9]{64}$/', $recipient) >= 1)
        {
            // store recipient in class
            $this->message_recipient = $recipient;
            $this->message_target_os = $recipient_os;
            
            // Process recipient according to the recipient_os
            // 
            // Not required
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            // 
            
            
            return TRUE;
        }
        else
        {
            parent::debug("push token rejected: result=".preg_match('/^[\d\w]{32-64}$/', $recipient));
            parent::debug("push token rejected: length=".strlen($recipient).' ; recipient='.$recipient);
            
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
    }
    
    
    
    /**
     * Set the message alert text
     * 
     * This method is used to set the push message alert text. This is the text 
     * shown on the mobile device when the message is received. It is also 
     * possible to change the lable of the action button (Button shows "View" 
     * if not defined). Additional the launch image can be defined.
     * 
     * @link http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Introduction/Introduction.html#//apple_ref/doc/uid/TP40008194-CH1-SW1
     * 
     * @access public
     * 
     * @param string $alert_text The push message text
     * @param string $action_key The lable for the action key (or NULL to hide the button)
     * @param string $launch_image The launch image filename
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_message_alert($alert_text, $action_key = '', $launch_image = NULL)
    {
        //invalidate the cache
        $this->_cache_is_valid = FALSE;
        if(is_string($alert_text) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
            
        if(is_string($action_key) === FALSE && $action_key !== NULL) 
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        if(is_string($launch_image) === FALSE && $launch_image !== NULL)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        
        
        //
        // Set the recipeint according to the parameters
        // 
        if($action_key === '' && $launch_image === NULL)
        {
            // define message content
            parent::debug("Set message content ... alert: $alert_text");
            $this->message_content['aps']['alert'] = $alert_text;
        }
        else
        {
            // define message content
            parent::debug("Set message content ... alert - body: $alert_text");
            $this->message_content['aps']['alert']['body'] = $alert_text;
            if($action_key !== '')
            {
                parent::debug("Set message content ... alert - action-loc-key: $action_key");
                $this->message_content['aps']['alert']['action-loc-key'] = $action_key;
            }
            if($launch_image !== NULL)
            {
                parent::debug("Set message content ... alert - launch-image: $launch_image");
                $this->message_content['aps']['alert']['launch-image'] = $launch_image;
            }
        }
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Set localised message alert text
     * 
     * This method is used to set the push message alert text as localised 
     * string. The localise_key is taken as key to find the localised text. 
     * The localised text with the values of the localised_args is shown on the 
     * mobile device when the message is received. It is also possible to 
     * change the lable of the action button (Button shows "View" if not 
     * defined). Additional the launch image can be defined.
     * 
     * @link http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Introduction/Introduction.html#//apple_ref/doc/uid/TP40008194-CH1-SW1
     * 
     * @access public
     * 
     * @param string $localise_key The localise-key for the alert text
     * @param string $localise_args Additional values for the localised string
     * @param string $action_key The lable for the action key (or NULL to hide the button)
     * @param string $launch_image The launch image filename
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_message_localised($localise_key, $localise_args = NULL, $action_key = '', $launch_image = null)
    {
        //invalidate the cache
        $this->_cache_is_valid = FALSE;
        parent::debug2(__FUNCTION__.' called with localise_key='.$localise_key.' , action_key='.$action_key.' , launch_image='.$launch_image);
        parent::debug2(__FUNCTION__.' called with localise_args='.$localise_args);
        
        // Prepare localised push message content
        if(is_string($localise_key) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        // check for the launch image
        if(is_string($launch_image) === FALSE && $launch_image !== NULL)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        // define message content
        parent::debug("Set message content ... alert - loc-key: $localise_key");
        $this->message_content['aps']['alert']['loc-key'] = $localise_key;
        if($localise_args !== NULL)
        {
            parent::debug("Set message content ... alert - loc-args: $localise_args");
            $this->message_content['aps']['alert']['loc-args'] = $localise_args;
        }
        if($action_key !== '')
        {
            parent::debug("Set message content ... alert - action-loc-key: $action_key");
            $this->message_content['aps']['alert']['action-loc-key'] = $action_key;
        }
        if($launch_image !== NULL)
        {
            parent::debug("Set message content ... alert - launch-image: $launch_image");
            $this->message_content['aps']['alert']['launch-image'] = $launch_image;
        }
        
        // return Success
        return TRUE;
    }
    
    
    
    /**
     * Set message dabge
     * 
     * This method is used to set the badge number for the App icon on the 
     * mobile device. The badge number is shown at the app ichon after the push 
     * message is received.
     * 
     * @link http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Introduction/Introduction.html#//apple_ref/doc/uid/TP40008194-CH1-SW1
     * 
     * @access public
     * 
     * @param badge_number
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_message_badge($badge_number)
    {
        //invalidate the cache
        $this->_cache_is_valid = FALSE;
        if(is_int($badge_number) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        
        
        //
        // Set the badge number according to the target os
        //
        // Not required for this class
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        // Set the bapge number
        parent::debug("Set message content ... badge: $badge_number");
        $this->message_content['aps']['badge'] = $badge_number;
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Set message sound
     * 
     * This method is used to set the sound file for the push message. The 
     * sound file is played when the push message is received.
     * 
     * @link http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/IPhoneOSClientImp/IPhoneOSClientImp.html#//apple_ref/doc/uid/TP40008194-CH103-SW6
     * 
     * @access public
     * 
     * @param string $sound_file The soundfile within the mobile device application
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_message_sound($sound_file)
    {
        //invalidate the cache
        $this->_cache_is_valid = FALSE;
        if(is_string($sound_file) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        
        
        //
        // Set the sound file according to the target os
        //
        // Not required to this class
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        // Set the sound file
        parent::debug("Set message content ... sound: $sound_file");
        $this->message_content['aps']['sound'] = $sound_file;
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Set the custom payload.
     * 
     * This method is a dummy method to sync with the other classes attaches a custom payload to the push message. The
     * custom payload must be an array, but there are no requirements 
     * as to which fields it may or may not contain. Variable types for
     * subelements are limited to: string, array, integer.
     * 
     * 
     * 
     * 
     * 
     * 
     * 
     * 
     * 
     * 
     * 
     * 
     * @param $payload mixed the custom payload which should be attached to the push notification
     * @return boolean TRUE if successful, FALSE otherwise
     **/
    public function set_custom_payload($payload = NULL)
    {
        //invalidate the cache
        $this->_cache_is_valid = FALSE;
        
        if(is_null($payload) === FALSE)
        {
            foreach($payload as $key => $value)
            {
                $this->message_content[$key] = $value;
            }
        }
        else
        {
            return FALSE;
        }
        
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        
        return TRUE;
    }
    
    
    
    /**
     * Send the push message
     * 
     * This method is used to send the defined push message. The message is 
     * immediately sent to the API system. The message content and recipint 
     * information is cleared after the message was sent.
     * 
     * @link http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/CommunicatingWIthAPS/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW1
     * 
     * @access public
     * 
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function send_push_message()
    {
        if($this->message_content === NULL)
        {
            // set error text and code
            $error_info = array('code' => 106, 'text' => 'Message data not defined.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        if($this->message_recipient === NULL)
        {
            // set error text and code
            $error_info = array('code' => 106, 'text' => 'Recipient missing.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            return FALSE;
        }
        
        // prepare message content
        if($this->_check_message_size() == TRUE)
        {
            // get Push message content
            $push_payload = $this->_build_message_payload();
            if ($push_payload === FALSE)
            {
                return FALSE;
            }
        }
        else
        {
            return FALSE;
        }
        parent::debug("Send message content (summary)... \n ".print_r($this->message_content,TRUE));
        parent::debug2("XML-RPC message content (complete) ... \n".print_r($push_payload, TRUE));
        
        
        // Define variables
        $error = $error_string = NULL;
        
        // connect to system service
        $gateway = $this->api_gateway_production;
        // 
        // 
        //
        //
        // Prepare for connecting to the server
        //
        // Not Required
        //
        //
        //
        //
        //
        // set the development / production informations
        if($this->system_type === 'developer')
        {
            // 
            $gateway = $this->api_gateway_developer;
        }
        $push_header = array('Content-type: text/xml; charset=utf-8', 'Content-length: '.strlen($push_payload));
        
        parent::debug("API URL        : ".$gateway);
        // parent::debug("API Credentials: "."$certificate");
        
        // reset the API error code
        $this->api_last_error = NULL;
        
        // Connect to API server
        
        
        
        if(empty($this->curl_handler))
        {
            $this->curl_handler = curl_init();
            // the options we request are to get the return result, verify ssl and timeouts for connect and receive
            curl_setopt($this->curl_handler, CURLOPT_URL, $gateway);
            curl_setopt($this->curl_handler, CURLOPT_HEADER, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($this->curl_handler, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($this->curl_handler, CURLOPT_CONNECTTIMEOUT, $this->timeout_time);
            curl_setopt($this->curl_handler, CURLOPT_TIMEOUT, $this->timeout_time);
            if(isset($_SERVER['HTTP_USER_AGENT']))
            {
                curl_setopt($this->curl_handler, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
            }
        }
        // finally specify the payload
        curl_setopt($this->curl_handler, CURLOPT_HTTPHEADER, $push_header);     // the header
        curl_setopt($this->curl_handler, CURLOPT_POSTFIELDS, $push_payload);    // xmlrpc payload
        curl_setopt($this->curl_handler, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($this->curl_handler, CURLOPT_FOLLOWLOCATION, 0);

        // Connect to the server by executing the curl request
        $result = curl_exec($this->curl_handler);
        $curl_info = curl_getinfo($this->curl_handler);
        // log the result
        parent::debug2("Curl result:".$result);
        parent::debug2("Curl result:".print_r($curl_info, TRUE));
        
        // close connection to the API server
        if(empty($this->keepalive))
        {
            curl_close($this->curl_handler);
            $this->curl_handler = NULL;
        }
        
        // check if connecting was successful
        if($curl_info['http_code'] !== 200)
        {
            // set error text and code
            $error_info = array('code' => 107, 'text' => 'Error connecting to API server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            parent::report_error($error_info['code'],$error_info['text']);
            
            if($curl_info['http_code'] == 503)
            {
                parent::info('Returned HTTP code 503 / twisted/pyapns reports that resource is temporarily unavailable');
            }
            elseif($curl_info['http_code'] == 401)
            {
                parent::debug('Returned HTTP code 401 / twisted/pyapns reported that we need authorization');
                $this->service_auth_token = NULL;
            }
            
            // return with error
            return FALSE;
        }
        
        // padding
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        // 
        
        // detect if an error occured, within the response
        // if we got a response with a body
        if (strlen($result) > 100)
        {
            // search if an error (single first is reported) occured (fault)
            $pattern = "/<\?xml/";
            preg_match($pattern, $result, $matches, PREG_OFFSET_CAPTURE);

            $result_doc = $this->_xml_manager->xmlstring_to_array(substr($result,$matches[0][1]));
            if (isset($result_doc['methodResponse']['fault']['value']['struct']['member']) === TRUE)
            {
                // extract the code, text
                $xmlrpc_response_error_code = $result_doc['methodResponse']['fault']['value']['struct']['member'][0]['value']['int']['@value'];
                $xmlrpc_response_error_text = $result_doc['methodResponse']['fault']['value']['struct']['member'][1]['value']['string']['@value'];
                // register the error, default is 200, we override if we have more details
                $this->api_last_error = 200;
                if ($xmlrpc_response_error_code == 500)
                {
                    $this->api_last_error = 203;
                }
                else if ($xmlrpc_response_error_code == 404)
                {
                    $this->api_last_error = 202;
                }
                $error_info = array('code' => 111, 'text' => 'API reported an Error: faultCode:'.$xmlrpc_response_error_code." faultString:".$xmlrpc_response_error_text);
                parent::report_error($error_info['code'],$error_info['text']);
                return FALSE;
            }
        }
		// Not required
		//
		//
		//
		//
		//
		//
		//
		//
		//
		//
		//
		//
		//        
		//        
		//        
        
        // reset the message data
        $this->message_content = $this->message_recipient = NULL;
        
        // return success
        return TRUE;
    }
        
    
    
    /**
     * A convenience method to reset the curl connection.
     * 
     * The instance variable is closed via fclose and then set to NULL to
     * reset it in preparation for reuse.
     *
     * This method uses the currently open instance variable containing the 
     * socket resource. If this is not open the method returns immediately 
     * and prints a warning to the logs. In this case NULL is returned.
     *
     * @return boolean true or false based on whether apns reported an error or not
     **/
    private function _disconnect_socket()
    {
        curl_close($this->curl_handler);
        $this->curl_handler = NULL;
        // padding
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        
        return TRUE;
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
     * @return mixed A array with "code" and "text"
    **/
    public function get_last_error($clear = FALSE, $all_errors = FALSE)
    {
        // check if an error appeared
        if(count($this->get_all_errors()) >= 1)
        {
            if($all_errors === TRUE)
            {
                $error = parent::get_all_errors();
            }
            else
            {
            	//
                $error = parent::get_last_error();
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
            $this->reset_errors();
            return $error;
        }
    }
    
    /**
     * Returns the last error reported by the API.
     * 
     * Returns a standardized error according to which error was reported in the
     * last send_push_message() function call. When the send_push_message returns
     * FALSE and the last error number for the class is an API error then this
     * method can be used to return the error which was reported by the API.
     * 
     * See class error numbers for details on API errors.
     * 
     * @see send_push_message()
     * @return integer the last API error which occured
    **/
    public function get_api_last_error()
    {
        return $this->api_last_error;
    }
    
    
    /**
     * This function frees the resources acquired by this object.
    **/
    public function __destruct()
    {
        if($this->curl_handler)
        {
            $this->_disconnect_socket();
        }
    }
    
    
    
    /**
     * Attach a helper to use.
     * 
     * This method allows to specify an external helper to use internally
     * in the class. If it's not possible to use the specified helper an error
     * will be triggered.
     * 
     * @param string $class_name       The class name the helper belongs to
     * @param object &$class_reference The instance of the helper to use.
     */
    public function set_helper($class_name, &$class_reference)
    {
        // check if we can use this helper (this could be checked against a 'registry' check in the future)
        if (is_string($class_name) === TRUE && $class_name === "XML_Manager" && get_class($class_reference) === $class_name)
        {
            // check if an error happened of type xmlmanager_error, if yes remove it, we are no ok
            if (parent::error_occured(112) === TRUE)
            {
                parent::delete_error(112);
            }
            $this->_xml_manager = $class_reference;
            return;
        }

        $error_info = array('code' => 112, 'text' => 'helper class was not usable: classname:\''.$class_name.'\'.');
        parent::error($error_info['code'].': '.$error_info['text']);
        parent::report_error($error_info['code'],$error_info['text']);
    }

    /**
     * Encode parameters in an correct xmlrpc structure
     * 
     * This function converts an array to the correct xmlrpc array structure but doesn't
     * create the complete xmlrpc string needed yet. The method is recursively generating the
     * needed structure and returns the array representation of it to be converted by the
     * XML_Manager class.
     * The data types handled so far are:
     *     NULL, string, integer, double, boolean, array (associattive, indexed)
     * The indexed arrays aren't checked currently for gaps.
     * 
     * @link http://www.xmlrpc.org
     * @link http://en.wikipedia.org/wiki/XML-RPC
     * 
     * @param  array $param an array of parameters to encode in an xmlrpc request structure
     * 
     * @return array        the resulting xmlrpc structure as an array
     */
    private function _xmlrpc_param_encode($param)
    {
        switch (gettype($param))
        {
            case "NULL":
                $result['value']['nil']['@value'] = NULL;
                return $result;
                break;
            case "string":
                $result['value']['string']['@value'] = $param;
                return $result;
                break;
            case "integer":
                $result['value']['int']['@value'] = $param;
                return $result;
                break;
            case "double":
                $result['value']['double']['@value'] = $param;
                return $result;
                break;
            case "boolean":
                $result['value']['double']['@value'] = $param;
                return $result;
                break;
            case "array":
                // assoc are arrays with at least one key NOT of type integer
                //  phase 2: if the integer keys not starting with 0 and not continually counting up after sorting
                if (count(array_filter(array_keys($param),'is_numeric')) === count($param))
                {
                    $i = 0;
                    foreach($param as $key => $value)
                    {
                        switch (gettype($value))
                        {
                            case "NULL":
                                $result['value']['array']['data']['value'][$i]['nil']['@value'] = NULL;
                                break;
                            case "string":
                                $result['value']['array']['data']['value'][$i]['string']['@value'] = $value;
                                break;
                            case "integer":
                                $result['value']['array']['data']['value'][$i]['int']['@value'] = $value;
                                break;
                            case "double":
                                $result['value']['array']['data']['value'][$i]['double']['@value'] = $value;
                                break;
                            case "boolean":
                                $result['value']['array']['data']['value'][$i]['boolean']['@value'] = $value;
                                break;
                            case "array":
                                $temp_array = call_user_func(array($this, __FUNCTION__), $value);
                                $result['value']['array']['data']['value'][$i] = $temp_array['value'];
                            break;
                            // just in case something could not be encoded correctly write to log
                            default:
                                parent::debug("uncaught case: type 2:".gettype($param)." content:".print_r($param, TRUE));
                            break;
                        }
                        ++$i;
                    }
                    return $result;
                }
                else
                {
                    // echo "is associative".PHP_EOL;
                    $i = 0;
                    foreach($param as $key => $value)
                    {
                        $result['value']['struct']['member']['value'][$i]['name']['@value'] = $key;
                        // try guessing the types
                        switch (gettype($value))
                        {
                            case "NULL":
                                $result['value']['struct']['member']['value'][$i]['nil']['@value'] = NULL;
                                break;
                            case "string":
                                $result['value']['struct']['member']['value'][$i]['string']['@value'] = $value;
                                break;
                            case "integer":
                                $result['value']['struct']['member']['value'][$i]['int']['@value'] = $value;
                                break;
                            case "double":
                                $result['value']['struct']['member']['value'][$i]['double']['@value'] = $value;
                                break;
                            case "boolean":
                                $result['value']['struct']['member']['value'][$i]['boolean']['@value'] = $value;
                                break;
                            case "array":
                                $temp_array = call_user_func(array($this, __FUNCTION__), $value);
                                foreach ($temp_array as $temp_key => $temp_value)
                                {
                                    $result['value']['struct']['member']['value'][$i][$temp_key] = $temp_array[$temp_key];
                                }
                            break;
                            // just in case something could not be encoded correctly, write to log
                            default:
                                parent::debug("uncaught case 3: type:".gettype($param)." content:".print_r($param, TRUE));
                            break;
                        }
                        ++$i;
                    }
                    return $result;
                }
            break;
            // just in case something could not be encoded correctly write to log
            default:
                parent::debug("uncaught case 1: type:".gettype($param)." content:".print_r($param, TRUE));
            break;
        }
    }
    
    
}



?>