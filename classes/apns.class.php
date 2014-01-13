<?php
/**
 * Apple Push Notification Class (Apple APNS)
 * 
 * This class provides access to the Apple Push Notification Service (APNs). 
 * The service is available for Apple mobile deviced with iOS (IPhoneOS) 3.0 or 
 * higher. Not all features are available in iOS 3.0. Full set of features is 
 * available with iOS 4.0 and higher.
 * 
 * @author Gerhard Steinbeis (info [at] tinned-software [dot] net)
 * @author Tyler Ashton (tdashton [at] gmail [dot] com)
 * @copyright Copyright (c) 2010
 * @version 2.0.7
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 *
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/main.class.php');
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/functions/json_encode.php');



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
 *    102 ... Certificate file does not exist or is not readable.<br>
 *    103 ... Wrong type parameter value.<br>
 *    104 ... Certificate has wrong permission (644 expected)<br>
 *    105 ... Method parameter of unexpected types.<br>
 *    106 ... Message data not defined or recipient missing.<br>
 *    107 ... Error connecting to API server.<br>
 *    108 ... Error sending data to API server.<br>
 *    109 ... Recipient OS not supported or not set.<br>
 *    110 ... Maximum allowed message size exceeded (exceeded by X bytes)<br>
 *    111 ... API reported an error, see get_api_last_error() and the 2xx error codes<br>
 * PUSH API Error Codes<br>
 *    200 ... Push Gateway reports a general error, not yet specified in this class<br>
 *    201 ... Push Gateway reports that the device is no longer active<br>
 * 
 * 
 * 
 * @package framework
 * @subpackage mobile-service
 * 
**/
class APNS extends Main
{
    ////////////////////////////////////////////////////////////////////////////
    // CONSTANTS of the class
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * @type integer APNS Payload command.
     **/
    const COMMAND_PUSH = 1; 
    /**
     * @type integer APNS Error-response packet size.
     **/
    const ERROR_RESPONSE_SIZE = 6;
     /**
      * @type integer APNS Error-response command code
      **/
    const ERROR_RESPONSE_COMMAND = 8;
    /**
     * @type integer use this factor when calculating decimal places to retain using utime.
     **/
    const UTIME_PRECISION = 1000; 

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
    
    // variable to hold last error message
    private $last_error                 = array();
    
    // variable to hold database object
    private $db_object                  = NULL;
    private $db_table                   = NULL;
    
    // variables to hold certificate file paths
    private $security_cert_developer    = NULL;
    private $security_cert_production   = NULL;
    
    // variable for the service authentication
    // 
    // 
    
    // variables to hold API gateway addresses
    private $api_gateway_developer      = 'ssl://gateway.sandbox.push.apple.com:2195';
    private $api_gateway_production     = 'ssl://gateway.push.apple.com:2195';
    
    // 
    // 
    // 
    
    // variable to hold the API feedback service url
    private $api_feedback_developer     = 'ssl://feedback.sandbox.push.apple.com:2196';
    private $api_feedback_production    = 'ssl://feedback.push.apple.com:2196';
    
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
    private $keepalive                  = TRUE;
    private $socket                     = NULL;
    
    // Socket timeout time in seconds
    private $timeout_time               = 10;
    
    // Variables for gateway
    private $apns_identifier            = 0;
    private $apns_expires               = 0;
    
    // Variables for caching sent messages
    //
    
    /**< @type array an array to cache sent payloads in. */
    private $_cache_payload           =  array();
    /**< @type array a mapping of array indexes to payload ids. */
    private $_cache_payload_id_map    =  array();
    /**< @type float seconds before cached payloads are removed. */
    private $_cache_payload_timeout   =  3.5;
    // used internally to avoid count operations on the cache array
    private $_cache_array_beg         =  0;
    // used internally to avoid count operations on the cache array
    private $_cache_array_end         = -1;
    
    // array to contain invalid payloads/tokens
    //private $_apns_invalid_payload    = array();
    
    
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
     * @param string $type The Push type "developer" or "production" (default: production)
     * @param integer $dbg_level Debug log level
     * @param mixed $debug_object Debug object to send log messages to
    **/
    public function __construct ($type = 'production', $dbg_level = 0, &$debug_object = null)
    {
        // initialize parent class MainClass
        parent::Main_init($dbg_level, $debug_object);
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
            $this->last_error[] = $error_info;
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
     * @param string $type The type of certificate (production, developer)
     * @param string $certificate_file The path and file name to the certificate file
     * @param string $unused_parameter1 A unused parameter for compatibility
     * @param string $unused_parameter2 A unused parameter for compatibility
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_api_credentials($type, $certificate_file, $unused_parameter1 = NULL, $unused_parameter2 = NULL)
    {
        parent::debug2(__FUNCTION__.' called with type='.$type.', unused_parameter1='.$unused_parameter1.', unused_parameter2='.$unused_parameter2);
        
        // check the type
        if($type === 'production' || $type === 'developer')
        {
            if(is_readable($certificate_file) === TRUE)
            {
                // check the file permission of the certificate
                clearstatcache();
                $certificate_permission = substr(sprintf('%o', fileperms($certificate_file)), -3);
                if($certificate_permission > 644)
                {
                    $error_info = array('code' => 104, 'text' => 'Certificate has wrong permission (644 expected)');
                    parent::warning($error_info['code'].': '.$error_info['text']);
                    $this->last_error[] = $error_info;
                    //return FALSE;
                }
                
                // save the credentials on the class
                parent::debug('Certificate permission (expected: 644, current: '.$certificate_permission.') - Use it anyway!');
                $varname = 'security_cert_'.$type;
                $this->$varname = $certificate_file;
                parent::debug('Defined certificate '.$varname.'='.basename($this->$varname));
                
                // return true if certificate could be set correctly
                return TRUE;
            }
            else
            {
                $error_info = array('code' => 102, 'text' => 'Certificate file does not exist or is not readable.');
                parent::error($error_info['code'].': '.$error_info['text']);
                $this->last_error[] = $error_info;
                return FALSE;
            }
        }
        else
        {
            $error_info = array('code' => 103, 'text' => 'Wrong type parameter value.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
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
                fclose($this->socket);
                $this->socket = NULL;
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
        $payload_size = mb_strlen($this->_build_message_payload());
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
                    parent::debug2("Truncated payload (L): original-size: $text_len, truncate-size: $payload_diff, remaining-size: $remaining_chars (".mb_strlen($this->message_content['aps']['alert']['body'])."), payload-size: ".mb_strlen($this->_build_message_payload()));
                }
                // check if it is a NON LOCALISED message
                else
                {
                    $this->message_content['aps']['alert'] = substr($this->message_content['aps']['alert'], 0, $remaining_chars - 4).' ...';
                    parent::debug2("Truncated payload (NL): original-size: $text_len, truncate-size: $payload_diff, remaining-size: $remaining_chars (".mb_strlen($this->message_content['aps']['alert'])."), payload-size: ".mb_strlen($this->_build_message_payload()));
                }
            }
            // If the message should not be truncated, or can not be truncated, return with error
            else
            {
                // set error text and code
                $error_info = array('code' => 110, 'text' => 'Maximum allowed message size exceeded (exceeded by '.$payload_diff.' bytes)');
                parent::warning($error_info['code'].': '.$error_info['text']);
                $this->last_error[] = $error_info;
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
        // building the message payload
        $payload = json_encode($this->message_content);
        
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
        if(preg_match('/^[a-zA-Z0-9]{64}$/', $recipient) >= 1)
        {
            // store recipient in class
            $this->message_recipient = $recipient;
            $this->message_target_os = $recipient_os;
            parent::debug2("set message recipient : $recipient");
            
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
            
            
            return TRUE;
        }
        else
        {
            parent::debug("push token rejected: result=".preg_match('/^[\d\w]{32-64}$/', $recipient));
            parent::debug("push token rejected: length=".strlen($recipient).' ; recipient='.$recipient);
            
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
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
        if(is_string($alert_text) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
            
        if(is_string($action_key) === FALSE && $action_key !== NULL) 
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        if(is_string($launch_image) === FALSE && $launch_image !== NULL)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
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
        parent::debug2(__FUNCTION__.' called with localise_key='.$localise_key.' , action_key='.$action_key.' , launch_image='.$launch_image);
        parent::debug2(__FUNCTION__.' called with localise_args='.$localise_args);
        
        // Prepare localised push message content
        if(is_string($localise_key) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        // check for the launch image
        if(is_string($launch_image) === FALSE && $launch_image !== NULL)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
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
        if(is_int($badge_number) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
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
        if(is_string($sound_file) === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected type or format.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
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
     * This method attaches a custom payload to the push message. The
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
     * 
     * @param $payload mixed the custom payload which should be attached to the push notification
     * @return boolean TRUE if successful, FALSE otherwise
     **/
    public function set_custom_payload($payload = NULL)
    {

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
            $this->last_error[] = $error_info;
            return FALSE;
        }
        if($this->message_recipient === NULL)
        {
            // set error text and code
            $error_info = array('code' => 106, 'text' => 'Recipient missing.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        
        // prepare message content
        if($this->_check_message_size() == TRUE)
        {
            // get Push message content
            $message_string = $this->_build_message_payload();
        }
        else
        {
            return FALSE;
        }
        parent::debug("Send message content (json)... \n $message_string");
        parent::debug("Send message content ... \n".print_r($this->message_content, TRUE));
        
        
        // Define variables
        $error = $error_string = NULL;
        
        // Connect to API server
        // 
        // Send the HTTP Basic Authentication request
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
        
        $send_time = round(microtime(TRUE) * self::UTIME_PRECISION, 0);
        
        // generate apns id from timestamp and ensure it 
        // is not bigger than what a 32 bit integer can hold.
        $apns_id_str = strval($send_time);
        // parent::debug2("apns_id_str: $apns_id_str");
        $apns_id_substr = substr($apns_id_str, -9);
        // parent::debug2("apns_id_substr: $apns_id_substr");
        $this->apns_identifier = intval($apns_id_substr);
        unset($apns_id_str, $apns_id_substr);
        
        $this->apns_expires = intval(time() + 60 * 60 * 24);
        parent::debug2('sending content with id:'.$this->apns_identifier);
        
        // Connection success, pack message content
        $message_content  = chr(self::COMMAND_PUSH).pack('l',$this->apns_identifier).pack('l',$this->apns_expires);
        $message_content .= pack('n',32).pack('H*',$this->message_recipient).pack('n',strlen($message_string));
        $message_content .= $message_string;
        
        // cache message internally
        $this->_cache_payload_add($this->apns_identifier, $message_content);
        
        // send message content
        $this->_send_message_internal(
            array(
                array('id' => $this->apns_identifier, 'ts' => $send_time, 'data' => $message_content)
                ),
            FALSE);
        
        if($this->keepalive === FALSE)
        {
            $this->_disconnect_socket();
        }
        
        // reset the message data
        $this->message_content = $this->message_recipient = NULL;
        
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Sends the given array of push messages to the socket.
     * 
     * This method enables the class to internally work with lists of messages
     * which should be sent, in contrast to the functionality of the public 
     * send_push_message which must for compatibility reasons only send one
     * message at a time. List must be able to be handled to properly resend
     * messages in the cache in case of a failed message.
     * 
     * @param $msg_list array the list of messages to send (array of arrays with keys: id, ts, data)
     * @param $resend boolean whether this send is a first send or a resend attempt. Only for debugging.
     *
     * @return mixed FALSE when write failed, otherwise integer representing number of bytes written to the socket
     **/
    private function _send_message_internal($msg_list = array(), $resend = FALSE)
    {
        if($resend === TRUE)
        {
            // this is only here for debugging at the moment
            /** @todo remove this if there is no reason to keep it... **/
            parent::debug2('detected a resend');
        }
        // create socket and connect to the server using the certificate.
        // class instance variable $this->socket is set after this call.
        $this->_get_socket();
        
        // check if connecting was successful or socket is already open
        if(empty($this->socket) === FALSE)
        {
            // check for an error in the socket
            $send_error = $this->_check_apns_error();
            
            if($send_error === TRUE)
            {
                // if an error was returned from APNS socket has been
                // reset, so we must re-create it.
                $this->_get_socket();
            }
        }
        
        $result = TRUE;
        foreach($msg_list as $index => $msg)
        {
            $result = $this->_write_to_socket($msg['data']);
            /** @todo react correctly to an error which occurs within this loop **/
        }
        
        return $result;
    }
    
    
    
    /**
     * Adds the given push message to the internal cache.
     * 
     * The class maintains a list of messages sent over the socket internally for
     * a period of time. This enables the class to recover from an error on the
     * socket by resending messages which were lost due to sending them into a socket
     * which was reset on the other side.
     * 
     * Internally the class maintains two arrays containing cached messages: 
     * 1) The data array in format: 
     *       array(25 => array('id' => 123, 'data' => bindata)); 
     * 
     * 2) A lookup array in format: 
     *       array(25 => 123); 
     * 
     * When searching for a specific payload using its id, the lookup array can be 
     * consulted to find the index of the payload in the main array quickly without 
     * having search all the sub elements of the main array.
     * 
     * @param $id integer an id representing the unique id which was sent to APNS to identify this push message 
     * @param $data string a binary string representing the data which should be cached
     *
     * @return NULL;
     **/
    private function _cache_payload_add($id, $data)
    {
        $cached_time = microtime(TRUE) * self::UTIME_PRECISION;
        // get the next index
        $index = $this->_cache_array_end + 1;
        // add the data to the array
        $this->_cache_payload[$index] = array('ts' => $cached_time, 'id' => $id, 'data' => $data);
        // add the apns_id to the id mapping array (internal array position => apns_id) for quick location
        $this->_cache_payload_id_map[$index] = &$id;
        // increment the index
        $this->_cache_array_end++;
        parent::debug2("added apns_id $id at position $index in internal cache, ts:$cached_time");
        $this->_cache_payload_log_output();
        // execute cache management procedure
        $this->_cache_payload_manage();
    }
    
    
    
    /**
     * A function which examines the internal cache and removes expired entries.
     *
     * Stale entries which are older than the time specified in the cache
     * timeout are removed from the internal message cache when this method is
     * called.
     *
     * @return NULL;
     **/
    private function _cache_payload_manage()
    {
        // check for stale entries in the array and remove them if necessary
        $stale_timestamp = ((microtime(TRUE) * self::UTIME_PRECISION) - ($this->_cache_payload_timeout * self::UTIME_PRECISION));
        parent::debug2('looking for cached timestamps older than '.$stale_timestamp);
        for($i = $this->_cache_array_beg; $i <= $this->_cache_array_end; $i++)
        {
            //parent::debug2($this->_cache_payload[$i]['ts'].' < '.$stale_timestamp);
            if($this->_cache_payload[$i]['ts'] < $stale_timestamp)
            {
                parent::debug2('unsetting stale cached payload apns_id: '.$this->_cache_payload[$i]['id'].' index: '.$i);
                unset($this->_cache_payload[$i]);
                unset($this->_cache_payload_id_map[$i]);
                $this->_cache_array_beg = $i + 1;
            }
        }
    }
    
    
    
    /**
     * A convenience method to output a properly formatted debug string with current cache contents.
     *
     * This method is not necessary for functionality of the class, only 
     * intended for convenience to print the array of binary payloads in the 
     * logfile in hex form, otherwise the data is unreadable in the log files.
     * 
     * @return NULL
     **/
    private function _cache_payload_log_output()
    {
        $debug_string1 = "current cache pos %d: id:%d ts:%f data:%s";
        foreach($this->_cache_payload as $key => $value)
        {
            parent::debug2(sprintf($debug_string1, $key, $value['id'], $value['ts'], bin2hex($value['data'])));
        }
        parent::debug2("current cache id map:\n".print_r($this->_cache_payload_id_map, TRUE));
    }
    
    
    
    /**
     * A convenience method to re/create an instance variable containing socket connection to APNS
     *
     * This is a convenience method which checks to see if the class already has an open socket
     * connection to the APNS gateway, and if this does not exist creates it. The socket
     * is assigned to the private instance variable $this->socket.
     * 
     * @return mixed a socket resource or FALSE depending on whether creation was successful or not.
     **/
    private function _get_socket()
    {
        // check if the socket exists
        if(empty($this->socket))
        {
            // connect to system service
            $gateway = $this->api_gateway_production;
            $certificate = $this->security_cert_production;
            
            // set the development / production informations
            if($this->system_type === 'developer')
            {
                $certificate = $this->security_cert_developer;
                $gateway = $this->api_gateway_developer;
            }
            
            parent::debug("API URL        : ".$gateway);
            parent::debug("API Credentials: "."$certificate");
            
            $ssl_context = stream_context_create();
            stream_context_set_option($ssl_context, 'ssl', 'local_cert', $certificate);
            $this->socket = stream_socket_client($gateway, $error, $error_string, $this->timeout_time, STREAM_CLIENT_CONNECT, $ssl_context);
            stream_set_blocking($this->socket, 0);
            stream_set_write_buffer($this->socket, 0);
            parent::debug2('created socket: '.strval($this->socket));
        }
        else
        {
            parent::debug2('socket already exists: '.strval($this->socket));
        }
        
        // check if the socket was successfully opened
        if($this->socket === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 107, 'text' => 'Error connecting to API server.');
            // todo: moved from error to debug and added WARNING:, could be moved to warn() when implemented
            parent::debug("WARNING: ".$error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            
            // disconnect and unset the socket
            $this->_disconnect_socket();
            
            // return with error
            return FALSE;
        }
        
        // no error occurred, returning with TRUE
        return $this->socket;
    }
    
    
    
    /**
     * This method writes the given binary content into the open socket in the class.
     * 
     * After a socket has been created with _get_socket data may be written into
     * it using this method. The data is passed as a binary string to this method.
     * If the socket cannot be written to an error is reported in the class and the
     * currently open reference to the socket is disconnected and destroyed.
     * 
     * This method uses the currently open instance variable containing the 
     * socket resource. If this is not open the method returns immediately 
     * and prints a warning to the logs. In this case FALSE is returned.
     *
     * @see _get_socket()
     * 
     * @return mixed FALSE when write failed, otherwise integer representing number of bytes written to the socket
     **/ 
    private function _write_to_socket($content)
    {
        if(empty($this->socket) === TRUE)
        {
            parent::debug('Instance variable socket has been closed or not been initalized.');
            return FALSE;
        }
        
        $result = @fwrite($this->socket, $content);
        
        // $result does not seem to be properly set to FALSE when a openSSL error ocurrs....
        // perhaps we need to use the openssl error function to get errors on the line
        // http://php.net/manual/en/function.openssl-error-string.php
        
        // check if sending was successful
        if($result === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 108, 'text' => 'Error sending data to APNS server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            
            // disconnect and unset the socket
            $this->_disconnect_socket();
            
            // return with error
            return FALSE;
        }
        else
        {
            parent::debug2('wrote '.$result.' bytes to socket '.strval($this->socket));
        }
        
        return $result;
    }
    
    
    
    /**
     * Reads response from APNs to see if an error occurred during sending.
     * 
     * APNs returns data to the the client script ONLY when an error occurred. 
     * When any error occurred APNs will also sever the connection to the client,
     * meaning it MUST be re-created.  This method checks the open connection 
     * for this incoming  message and when it is found it resets the socket connection.
     * 
     * @return boolean TRUE or FALSE based on whether APNS reported an error or not
     **/
    private function _check_apns_error()
    {
        // read any data waiting in the socket
        $raw_response = $this->_read_socket_response();
        
        if(isset($raw_response) === TRUE)
        {
            // unpack response according to APNS documentation specification
            // 
            $response = unpack("cresp/cstatus/lident", $raw_response);
            parent::debug ("WARNING: APNs returned an error: resp:{$response['resp']}, status:{$response['status']}, ident:{$response['ident']}");
            parent::debug2('APNS error data:'.print_r($response, TRUE));
            
            // disconnect and unset the socket
            $this->_disconnect_socket();
            
            // now we collect an array of entries which failed due to be being sent into
            // an empty / closed socket. This includes all entries in the cache starting
            // with the one sent immediately after the one which reported an error.
            
            $invalid_apns_id = intval($response['ident']);
            $invalid_msg_index = array_search($invalid_apns_id, $this->_cache_payload_id_map);
            
            if($invalid_msg_index === FALSE)
            {
                parent::debug("$invalid_apns_id not found in internal cache, not reprocessing");
                return;
            }
            
            // need to find the position, NOT index of the correct entry here for the array slice.
            // the array slice function disregards the given start at index and simply starts
            // counting from the beginning of the array
            
            $start_at_pos = 1;
            foreach($this->_cache_payload as $key => $value)
            {
                //parent::debug2($key . '===' . $invalid_msg_index);
                if($key === $invalid_msg_index)
                {
                    parent::debug2("found $invalid_msg_index at postion $key, setting start position to $start_at_pos");
                    break;
                }
                $start_at_pos++;
            }
            
            $length = ($this->_cache_array_end - $this->_cache_array_beg) + 1;
            parent::debug2("found invalid id: $invalid_apns_id at index $invalid_msg_index, position $start_at_pos, length $length");
            //$this->_apns_invalid_payload[] = $this->_cache_payload[$invalid_msg_index]['data'];
            //parent::debug2(sprintf('added invalid payload to list: %s', bin2hex($this->_cache_payload[$invalid_msg_index]['data'])));
            $reprocess_array = array_slice($this->_cache_payload, $start_at_pos, $length, TRUE);
            
            parent::debug2('reprocessing '.count($reprocess_array).' entries');
            
            // resend messages via internal method
            $this->_send_message_internal($reprocess_array, TRUE);
            
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
    
    
    
    /**
     * A method to read any data which is waiting to be read in the socket.
     * 
     * The method reads any data which is waiting to be processed by the
     * client sent via the APNS socket. Per APNS gateway definition there is
     * only data  waiting to be read on the socket if an error occurred
     * during sending of a message.
     *
     * This method uses the currently open instance variable containing the 
     * socket resource. If this is not open the method returns immediately 
     * and prints a warning to the logs. In this case NULL is returned.
     * 
     * @return mixed NULL if no data is waiting on the socket, or binary string if data is waiting to be read
     **/
    private function _read_socket_response()
    {
        if(empty($this->socket) === TRUE)
        {
            parent::debug('Instance variable socket has not been initalized.');
            return NULL;
        }
        
        // checks the status of the socket to see if it is still open and can
        // be written to
        parent::debug2('check socket status for '.$this->socket);
        
        $raw_response = @fread($this->socket, self::ERROR_RESPONSE_SIZE);
        
        if($raw_response === FALSE)
        {
            // fread returned false, socket connection appears to have been severed
            parent::debug('reading socket '.$this->socket.' failed.');
            // disconnect and unset the socket
            $this->_disconnect_socket();
            return NULL;
        }
        else if(strlen($raw_response) != self::ERROR_RESPONSE_SIZE)
        {
            // socket is still open, but no data was received from it
            parent::debug2('No error content received from APNS');
            return NULL;
        }
        
        parent::debug2('read content from socket "'.strval($this->socket).'" content:"'.bin2hex($raw_response).'"');
        
        return $raw_response;
    }
    
    
    
    /**
     * A convenience method to reset the socket instance variable.
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
        if(empty($this->socket) === TRUE)
        {
            parent::debug('Instance variable socket has not been initalized.');
            return;
        }
        
        // for logging
        $obj_id = strval($this->socket);
        $result = NULL;
        
        // close connection to the API server
        $result = @fclose($this->socket);
        $this->socket = NULL;
        
        // log result of operation
        parent::debug2('closing and resetting socket:'.$obj_id.' result:'.$result);
        
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
    
    
    /**
     * Returns the last error reported by the API.
     * 
     * THIS FUNCTION IS A DUMMY FUNCTION FOR apns TO NOT BREAK COMPATIBILITY WITH OTHER CLASSES
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
        return NULL;
    }
    
    /**
     * This function frees the resources acquired by this object.
    **/
    public function __destruct()
    {
        if($this->socket)
        {
            $this->_disconnect_socket();
        }
    }
    
    
}



?>