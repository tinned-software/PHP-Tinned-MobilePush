<?php
/**
 * Android Push Notification Class (Urban Airship)
 * 
 * This class provides access to the third party Android Push Notification 
 * Service from Urban Airship. The service is available for the Android 
 * operating system version 1.5 and higher. Starting with Android 2.2 google 
 * Android offers a built in push service called C2DM.
 * 
 * @author Gerhard Steinbeis (info [at] tinned-software [dot] net)
 * @copyright Copyright (c) 2010
 * @version 2.0.1
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 * 
 * @todo test to see if custom_payload works and arrives on the destination device
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/main.class.php');
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/functions/json_encode.php');



/**
 * Android Push Notification Class (Urban Airship)
 * 
 * This class provides access to the third party Android Push Notification 
 * Service from Urban Airship. The service is available for the Android 
 * operating system version 1.5 and higher. Starting with Android 2.2 google 
 * Android offers a built in push service called C2DM.
 * 
 * 
 * ERROR CODES:<br>
 *    101 ... initialised class with wrong system type.<br>
 *    102 ... Certificate file does not exist or is not readable. (unused)<br>
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
class Urban_Airship extends Main
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
    
    // variable to hold the system type (producton or developer)
    private $system_type                = NULL;
    
    // variable to hold last error message
    private $last_error                 = array();
    
    // variable to hold database object
    private $db_object                  = NULL;
    private $db_table                   = NULL;
    
    // variables to hold certificate file paths
    private $security_api_key           = NULL;
    private $security_api_master_secret = NULL;
    
    // variable for the service authentication
    //
    //
    
    // variables to hold API gateway addresses
    private $api_gateway_developer      = 'https://go.urbanairship.com/api/push/';
    private $api_gateway_production     = 'https://go.urbanairship.com/api/push/';
    
    //
    //
    //
    
    // variable to hold the API feedback service url
    private $api_feedback_developer     = 'https://go.urbanairship.com/api/device_tokens/feedback/';
    private $api_feedback_production    = 'https://go.urbanairship.com/api/device_tokens/feedback/';
    
    // define if the table should be searched (and cleaned if required) for douplicate entries.
    private $check_unique_devices       = FALSE;
    
    // define variable to store message details
    private $message_content            = NULL;
    private $message_recipient          = NULL;
    private $message_target_os          = 'android';
    
    // set the maximum message length
    private $message_max_length         = 1024;
    private $message_truncate           = TRUE;
    
    // keep connections open between requests
    //
    //
    
    // Timeout time in seconds
    private $timeout_time               = 10;
    
    
    
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
     * The $api_key is used to set the Urban Airship "Application Key".
     * The $api_master_secret is used to set the Urban Airship "Application 
     * Master Secret".
     * 
     * @access public
     * 
     * @param string $type The type of certificate (production, developer)
     * @param string $api_key The Urban Airship "Application Key"
     * @param string $api_master_secret The Urban Airship "Application Master Secret"
     * @param string $unused_parameter2 A unused parameter for compatibility
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_api_credentials($type, $api_key, $api_master_secret, $unused_parameter2 = NULL)
    {
        parent::debug2(__FUNCTION__.' called with type='.$type.', unused_parameter2='.$unused_parameter2);
        
        // check the type
        if($type === 'production' || $type === 'developer')
        {
            if(is_string($api_key) === TRUE && is_string($api_master_secret) === TRUE)
            {
                // Check credentials for authentication
                // 
                // Not required
                // 
                // 
                // 
                //
                //
                //
                //
                
                // save the credentials on the class
                $this->security_api_key = $api_key;
                $this->security_api_master_secret = $api_master_secret;
                parent::debug('Defined credentials Application Key (length'.strlen($this->security_api_key).')');
                parent::debug('Defined credentials Application Master Secret (length'.strlen($this->security_api_master_secret).')');
                
                // return true if certificate could be set correctly
                return TRUE;
            }
            else
            {
                $error_info = array('code' => 105, 'text' => __FUNCTION__.' parameter of unexpected types.');
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
     * THIS IS A DUMMY FUNCTION FOR COMPATIBILTY WITH OTHER PUSH CLASSES.
     *
     * @param bool $enabled TRUE if keep alive should be enabled, FALSE if it should be disabled.
     *
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_keepalive($enabled)
    {
        parent::debug2("Method called with enabled=".$enabled);
        parent::debug("Keep alive not yet supported by this class.");
        
        return TRUE;
    }
    
    /**
     * Sets the socket timeout time in seconds.
     *
     * @param int $timeout_time The timeout time in seconds
     *
     * @return boolean TRUE is returned on success and FALSE otherwhise
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
     *
     * @return string The certificate filename and path
    **/
    public function get_api_credentials($type = 'production')
    {
        parent::debug("Getting credentials for type: ".$type);
        
        // Return credentials according to the type
        // 
        // Not required
        // 
        // 
        // 
        // 
        
        
        // return the credentials
        return array('api_key' => $this->security_api_key, 'api_master_secret' => $this->security_api_master_secret);
    }
    
    /**
     * Find out if keep alive is enabled.
     *
     * Keep alive means that the connection to the Urban Airship push service is kept
     * open until it is explicitly closed. (Or implicitly on script end, of course.)
     *
     * @return bool indicating if keep alive is enabled
     *
     * @see set_keepalive()
    **/
    public function get_keepalive()
    {
        // not used
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
        // calculate the amount of chars of the message payload
        $payload_size = mb_strlen($this->_build_message_payload());
        $payload_diff = $payload_size - $this->message_max_length;
        $remaining_chars = $payload_size - $payload_diff;
        parent::debug2("Payload size of message is $payload_size / ".$this->message_max_length." characters.");
        
        if($payload_diff >= 1)
        {
            parent::debug2("Payload exceeded by $payload_diff, remaining characters of message $remaining_chars.");
            
            // Check if truncating is is possible
            if($this->message_truncate === TRUE && $remaining_chars >= 1)
            {
                parent::debug2("Truncate payload by $payload_diff is not possible, remaining message length shorter then 0 characters.");
            }
            
            // Check if the message should be truncated and the message structure
            if($this->message_truncate === TRUE && $remaining_chars >= 1 && isset($this->message_content['alert']) === TRUE)
            {
                $this->message_content['alert'] = substr($this->message_content['alert'], 0, $payload_size - $payload_diff * -1);
                parent::debug2("Truncated payload size is: ".mb_strlen($this->_build_message_payload()));
            }
            // Check if the message should be truncated and the message structure (localised)
            //
            //
            //
            //
            //
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
     * @param string $recipient_os The OS of the recipient device ("android", "aps", "blackberry")
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_message_recipient($recipient, $recipient_os = 'android')
    {
        if(preg_match('/^[a-zA-Z0-9]{64}$/', $recipient) >= 1 || ($recipient_os !== 'android' || $recipient_os !== 'aps' || $recipient_os !== 'blackberry'))
        {
            // store recipient in class
            $this->message_recipient = $recipient;
            $this->message_target_os = $recipient_os;
            
            //
            // Set the recipeint according to the target os
            //
            if($this->message_target_os === 'aps')
            {
                parent::debug("Set message content ... device_tokens: $recipient");
                
                // define message content
                $this->message_content['device_tokens'][0] = $recipient;
                
            }
            else if($this->message_target_os === 'android')
            {
                parent::debug("Set message content ... apids: $recipient");
                
                // define message content
                $this->message_content['apids'][0] = $recipient;
            }
            else if($this->message_target_os === 'blackberry')
            {
                parent::debug("Set message content ... device_pins: $recipient");
                
                // define message content
                $this->message_content['device_pins'][0] = $recipient;
            }
            else                      
            {
                // set error text and code
                $error_info = array('code' => 109, 'text' => 'Recipient OS not supported or not set.');
                parent::warning($this->last_error['code'].': '.$this->last_error['text'].' - launch_image');
                return FALSE;
            }
            
            
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
        // Set the recipeint according to the target os
        // 
        if($this->message_target_os === 'aps')
        {
            // define message content
            parent::debug("Set message content ... alert: $alert_text");
            $this->message_content['aps']['alert'] = $alert_text;
        }
        else if($this->message_target_os === 'android')
        {
            // define message content
            parent::debug("Set message content ... alert: $alert_text");
            $this->message_content['android']['alert'] = $alert_text;
        }
        else if($this->message_target_os === 'blackberry')
        {
            // define message content (blackberry)
            parent::debug("Set message content ... content-type: text/plain");
            $this->message_content['blackberry']['content-type'] = 'text/plain';
            parent::debug("Set message content ... body: $alert_text");
            $this->message_content['blackberry']['body'] = $alert_text;
            
            
        } 
        else
        {
            // set error text and code
            $error_info = array('code' => 109, 'text' => 'Recipient OS not supported or not set.');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        
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
        //
        // Not supported
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        
        // Call non localised method
        return $this->set_message_alert($localise_key, $action_key, $launch_image);
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
        if($this->message_target_os === 'aps')
        {
            // define message content
            parent::debug("Set message content ... badge: $badge_number");
            $this->message_content['aps']['badge'] = $badge_number;
        }
        else if($this->message_target_os === 'android')
        {
            parent::debug("Set message content ... badge: <not supported>");
            
            // define message content
            // Not supported for android
        }
        else if($this->message_target_os === 'blackberry')
        {
            parent::debug("Set message content ... badge: <not supported>");
            
            // define message content
            // not supported for blackberry
        }
        else
        {
            // set error text and code
            $error_info = array('code' => 109, 'text' => 'Recipient OS not supported or not set.');
            parent::warning($this->last_error['code'].': '.$this->last_error['text'].' - launch_image');
            return FALSE;
        }
        // 
        // 
        
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
        if($this->message_target_os === 'aps')
        {
            parent::debug("Set message content ... sound: $sound_file");
            
            // define message content
            $this->message_content['aps']['sound'] = $sound_file;
            
        }
        else if($this->message_target_os === 'android')
        {
            parent::debug("Set message content ... sound (extra): $sound_file");
            
            // define message content
            $this->message_content['android']['extra'] = $sound_file; // used extra field for it
        }
        else if($this->message_target_os === 'blackberry')
        {
            parent::debug("Set message content ... sound: <not supported>");
            
            // define message content
            // not supported by blackberry
        }
        else
        {
            // set error text and code
            $error_info = array('code' => 109, 'text' => 'Recipient OS not supported or not set.');
            parent::warning($this->last_error['code'].': '.$this->last_error['text'].' - launch_image');
            return FALSE;
        }
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Set the custom payload.
     * 
     * This method attaches a custom payload to the push message. The
     * custom payload must be an array, but there are no requirements 
     * as to which fields it may or may not contain. Variable types  for
     * subelements are limited to: string, array, integer.
     * 
     * @param $payload mixed the custom payload which should be attached to the push notification
     * @return boolean TRUE if successful, FALSE otherwise
     **/
    public function set_custom_payload($payload = NULL)
    {
        parent::info('WARNING: custom payload functionality has not been tested if it arrives on the client');
        
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
     * @return boolean TRUE is returned on success and FALSE otherwise
    **/
    public function send_push_message()
    {
        if($this->message_content === NULL || $this->message_recipient === NULL)
        {
            // set error text and code
            $error_info = array('code' => 106, 'text' => 'Message data not defined or recipient missing.');
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
        
        // prepare parameter for connecting to system service
        $gateway  = $this->api_gateway_production;
        $username = $this->security_api_key;
        $password = $this->security_api_master_secret;
        
        
        // Prepare for connecting to the server
        //
        // Not Required
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        
        parent::debug("API URL        : ".$gateway);
        parent::debug("API Credentials: "."$username:$password");
        
        
        
        // Connect to API server
        // 
        // Send the HTTP Basic Authentication request
        $curl_connect = curl_init();
        curl_setopt($curl_connect, CURLOPT_URL, $gateway);
        curl_setopt($curl_connect, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_connect, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($curl_connect, CURLOPT_POST, TRUE);
        curl_setopt($curl_connect, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl_connect, CURLOPT_HEADER, TRUE);
        curl_setopt($curl_connect, CURLOPT_POSTFIELDS, $message_string );
        curl_setopt($curl_connect, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl_connect, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl_connect, CURLOPT_SSL_VERIFYPEER, FALSE);
        
        curl_setopt($curl_connect, CURLOPT_CONNECTTIMEOUT, $this->timeout_time);
        curl_setopt($curl_connect, CURLOPT_TIMEOUT, $this->timeout_time);
        
        // execute the curl request
        $result = curl_exec($curl_connect);
        $curl_info = curl_getinfo($curl_connect);
        // log the result
        parent::debug2("Curl result:".$result);
        parent::debug2("Curl result:".print_r($curl_info, TRUE));
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        //
        
        // check if connecting was successful
        if($curl_info['http_code'] !== 200)
        {
            // set error text and code
            $error_info = array('code' => 107, 'text' => 'Error connecting to API server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            
            // close connection to the API server
            curl_close($curl_connect);
            //
            
            // return with error
            return FALSE;
        }
        // Sending data to server connection
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
        
        // close connection to the API server
        curl_close($curl_connect);
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
}



?>