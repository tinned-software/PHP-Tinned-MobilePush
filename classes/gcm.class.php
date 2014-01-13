<?php
/**
 * Google Cloud Messaging Class (GCM)
 * 
 * This class provides access to the Google Cloud Messaging Push Notification (GCM)<br>
 * Service which is available for Android OS system version 2.2 and higher.
 * 
 * @link https://support.google.com/googleplay/android-developer/support/bin/answer.py?hl=en&answer=2663268
 * @link http://developer.android.com/guide/google/gcm/gs.html
 * 
 * 
 * @author Apostolos Karakousis
 * @copyright Copyright (c) 2012
 * @version 0.0.2
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 *
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/main.class.php');



/**
 * Google Cloud Messaging Push Notification Class (GCM)
 * 
 * This class provides access to the Google Cloud Messaging Push Notification (GCM)
 * Service.
 * 
 * 
 * 
 * 
 * ERROR CODES:<br>
 *    101 ... initialised class with wrong system type.<br>
 *    102 ... Authentication with account details failed<br>
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
class GCM extends Main
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
    private $last_error                 = NULL;
    
    // variable to hold database object
    // private $db_object                  = NULL;
    // private $db_table                   = NULL;
    
    // variables to hold certificate file paths
    // private $certificate_developer      = NULL;
    // private $certificate_production     = NULL;
    
    // variable for the service authentication
    // private $service_auth_url           = 'https://www.google.com/accounts/ClientLogin';
    private $service_auth_token         = NULL;
    
    // variables to hold API gateway addresses
    private $api_gateway_developer      = 'https://android.googleapis.com/gcm/send';
    private $api_gateway_production     = 'https://android.googleapis.com/gcm/send';
    
    // gateway return variables which indicate a deactivated device
    private $api_deactivated_status     = array('InvalidRegistration', 'NotRegistered');
    private $api_last_error             = NULL;
    
    // variable to hold the API feedback service url
    // private $api_feedback_developer     = '';
    // private $api_feedback_production    = '';
    
    // define if the table should be searched (and cleaned if required) for douplicate entries.
    // private $check_unique_devices       = FALSE;
    
    // define variable to store message details
    private $message_content            = NULL;
    private $message_recipient          = NULL;
    private $message_target_os          = 'android';
    
    // set the maximum message length
    private $message_max_length         = 4096;
    private $message_truncate           = TRUE;
    
    // keep connection open
    private $keepalive                  = FALSE;
    private $curl_handler               = NULL;
    
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
        
        
        $required_functions = array();
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
     * The $auth_username and $auth_password is used to authenticate against 
     * google services. The $auth_source contains the android application 
     * package name.
     * 
     * @access public
     * 
     * @param string $type The type of certificate (production, developer)
     * @param string $api_key The "Simple API Key" from google.
     * @param string $auth_password The developers google account password
     * @param string $auth_source The application package name
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_api_credentials($type, $api_key, $unused_param1, $unused_param2)
    {
        parent::debug2(__FUNCTION__.' called with type='.$type.' , api_key='.$api_key.' , unused_param1='.$unused_param1.' , unused_param2='.$unused_param2);
        
        // check the type
        if($type === 'production' || $type === 'developer')
        {
            if(is_string($api_key) === TRUE)
            {
                // Check credentials for authentication
                // if($this->_service_authenticate($auth_username, $auth_password, $auth_source, $service = 'ac2dm') === FALSE)
                //{ 
                //    $this->last_error = array('code' => 102, 'text' => 'Authentication with account details failed.');
                //    parent::error($this->last_error['code'].': '.$this->last_error['text']);
                //} 
                //
                //
                //
                //
                
                // save the credentials on the class
                $this->service_auth_token = $api_key;
                //$this->credentials_password = $auth_password;
                //$this->credentials_source   = $auth_source;
                parent::debug('Authenticate credentials: api_key = '.$api_key);
                
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
        // 
        // Not required
        // 
        // 
        // 
        // 
        
        
        // return the credentials
        return array('api_key' => $this->service_auth_token);
    }
    
    
    
    /**
     * Find out if keep alive is enabled.
     *
     * Keep alive means that the connection to the GCM push service is kept
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
        //
        //
        //
        //
        //
        //
        //
        $text_len = mb_strlen($this->message_content['alert']);
        parent::debug2("Text-length (NL): $text_len");
        //
        
        
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
                parent::debug2("PUSH MESSAGE CONTENT: ".print_r($this->message_content, TRUE));
                
                // check if it is a LOCALISED message
                //
                //
                //
                //
                //
                // check if it is a NON LOCALISED message
                //
                //
                $this->message_content['alert'] = substr($this->message_content['alert'], 0, $remaining_chars - 4).' ...';
                parent::debug2("Truncated payload (NL): original-size: $text_len, truncate-size: $payload_diff, remaining-size: $remaining_chars (".mb_strlen($this->message_content['alert'])."), payload-size: ".mb_strlen($this->_build_message_payload()));
                //
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
        $payload = 'registration_id='.urlencode($this->message_content['registration_id']).'&collapse_key=new';
        if(isset($this->message_content['alert']) === TRUE)
        {
            $payload .='&data.alert='.urlencode($this->message_content['alert']);
        }
        if(isset($this->message_content['sound']) === TRUE)
        {
            $payload .='&data.sound='.urlencode($this->message_content['sound']);
        }
        if(isset($this->message_content['custom']) === TRUE)
        {
            foreach($this->message_content['custom'] as $key => $value)
            {
                $payload .="&data.$key=".urlencode($this->message_content['custom'][$key]);
            }
        }
        
        // return the payload
        return $payload;
    }
    
    
    
    /**
     * Service Authentication
     * 
     * This is a PLACEHOLDER method, it's not used in this class.
     * This method uses the given parameter to authenticate with google.com to 
     * receive a authentication token. This token can be used to access the google 
     * API afterwards.
     * 
     * @access public
     * 
     * @param string $email_address Email address of google account
     * @param string $password Password for google email account
     * @param string $source name of the calling application
     * @param string $service name of the Google service to call (defaults to ac2dm)
     * @return mixed An authentication token, or false on failure
    **/
    function _service_authenticate($email_address, $password, $source, $service = 'ac2dm')
    {
        // // prepare POST data
        // $auth_post['Email']       = $email_address;
        // $auth_post['Passwd']      = $password;
        // $auth_post['Source']      = $source;
        // $auth_post['service']     = $service;
        // $auth_post['accountType'] = 'GOOGLE';
        
        
        // // create and configure CURL
        // $auth_ch = curl_init();
        // curl_setopt($auth_ch, CURLOPT_URL, $this->service_auth_url);
        // curl_setopt($auth_ch, CURLOPT_POST, TRUE);
        // curl_setopt($auth_ch, CURLOPT_HEADER, FALSE);
        // curl_setopt($auth_ch, CURLOPT_POSTFIELDS, $auth_post);
        // curl_setopt($auth_ch, CURLOPT_RETURNTRANSFER, TRUE);
        // curl_setopt($auth_ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // curl_setopt($auth_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        
        // // execute curl and get the result
        // $auth_response = curl_exec($auth_ch);
        // $auth_http_code = curl_getinfo($auth_ch, CURLINFO_HTTP_CODE);
        
        // parent::debug2('AUTH HTTP CODE: '.$auth_http_code);
        // parent::debug2('AUTH RESPONSE: ', print_r($auth_response, TRUE));
        
        // curl_close($auth_ch);
        
        // // check return http code
        // if (intval($auth_http_code) !== 200)
        // {
        //     $error_info = array('code' => 102, 'text' => 'Authentication with account details failed.');
        //     parent::warning($error_info['code'].': '.$error_info['text']);
        //     $this->last_error[] = $error_info;
        //     return FALSE;
        // }
        
        // // search for the auth token
        // $auth_matches = NULL;
        // preg_match('/(Auth=)([\w|-]+)/', $auth_response, $auth_matches);
        
        // // check if authentication was successfull
        // if (isset($auth_matches[2]) === FALSE)
        // {
        //     $error_info = array('code' => 102, 'text' => 'Authentication with account details failed.');
        //     parent::warning($error_info['code'].': '.$error_info['text']);
        //     $this->last_error[] = $error_info;
        //     return FALSE;
        // }
        
        // $this->service_auth_token = $auth_matches[2];
        // parent::debug2('AUTH Token: '.$this->service_auth_token);
        // // return the auth code
        // return $auth_matches[2];
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
            //
            // define message content
            $this->message_content['registration_id'] = $recipient;
            
            
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
        // Set the recipeint according to the target os
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
        // 
        // define message content
        parent::debug("Set message content ... alert: $alert_text");
        $this->message_content['alert'] = $alert_text;
        
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
        // 
        // 
        // 
        // 
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
        parent::debug("Set message content ... badge: <not supported>");
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
        // define message content
        parent::debug("Set message content ... sound (extra): $sound_file");
        $this->message_content['sound'] = $sound_file; // used sound field for it
        
        // return success
        return TRUE;
    }
    
    
    
    /**
     * Set the custom payload.
     * 
     * This method attaches a custom payload to the push message. The
     * custom payload should be a single dimension array, but there are 
     * no requirements, they are simply forwarded to the gateway as defined.
     * Variable types are limited to: string, integer, double.
     *
     * Multi demensional arrays are supported, but they are converted to single
     * demenisonal key value pairs, separated by an underscore "_".
     * Example: 
     *
     * <code>array('level1' => array('level2' => 'test'))</code>
     * will be coverted to a key called "level1_level2", with value 'test'.<br/>
     *
     * PLEASE NOTE: Key names with a period "." cannot be transmitted over the
     * GCM gateway, will NOT arrive on the destination device, and may cause
     * an otherwise mangled push message.
     * 
     * @param $payload mixed the custom payload which should be attached to the push notification
     * @return boolean TRUE if successful, FALSE otherwise
     **/
    public function set_custom_payload($payload = NULL)
    {
        // content changed, reset cached payload
        $this->_cached_payload = NULL;
        
        if(is_null($payload) === FALSE)
        {
            foreach($payload as $key => $value)
            {
                if(is_array($value) === TRUE)
                {
                    // if value is detected to be an array process it recursively
                    // 
                    foreach($value as $key2 => $value2)
                    {
                        $key = $key.'_'.$key2;
                        $value3[$key] = $value2;
                    }
                    $this->set_custom_payload($value3);
                    continue;
                }
                else
                {
                    // if value not an array assign it directly
                    // 
                    $this->message_content['custom'][$key] = $value;
                }
            }
        }
        else
        {
            return FALSE;
        }
        
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
            $push_post = $this->_build_message_payload();
        }
        else
        {
            return FALSE;
        }
        // 
        parent::debug("Send message content ... \n".print_r($this->message_content, TRUE));
        
        
        // Define variables
        $error = $error_string = NULL;
        
        // prepare parameter for connecting to system service
        $gateway  = $this->api_gateway_production;
        //
        //
        
        
        // Prepare for connecting to the server
        // if($this->service_auth_token === NULL)
        // { 
        //     // authenticate and check success
        //     if($this->_service_authenticate($this->credentials_username, $this->credentials_password, $this->credentials_source, 'ac2dm') === FALSE)
        //     {
        //         $this->last_error = array('code' => 102, 'text' => 'Authentication with account details failed.');
        //         parent::error($this->last_error['code'].': '.$this->last_error['text']);
                
        //         // stop sending message
        //         return FALSE;
        //     }
        // } 
        $push_post_length = strlen($push_post);
        $push_header = array('Content-type: application/x-www-form-urlencoded;charset=UTF-8', 'Content-Length: '.$push_post_length, 'Authorization: key='.$this->service_auth_token);
        
        parent::debug2("API request header: ".print_r($push_header, TRUE));
        parent::debug2("API POST data: ".$push_post);
        
        // reset the API error code
        $this->api_last_error = NULL;
        
        // Connect to API server
        // 
        // Send the HTTP Basic Authentication request
        if(empty($this->curl_handler))
        {
            $this->curl_handler = curl_init();
            curl_setopt($this->curl_handler, CURLOPT_URL, $gateway);
            // 
            // 
            curl_setopt($this->curl_handler, CURLOPT_POST, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_HEADER, FALSE); 
            curl_setopt($this->curl_handler, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($this->curl_handler, CURLOPT_SSL_VERIFYPEER, FALSE);
            
            curl_setopt($this->curl_handler, CURLOPT_CONNECTTIMEOUT, $this->timeout_time);
            curl_setopt($this->curl_handler, CURLOPT_TIMEOUT, $this->timeout_time);
        }
        curl_setopt($this->curl_handler, CURLOPT_HTTPHEADER, $push_header);
        curl_setopt($this->curl_handler, CURLOPT_POSTFIELDS, $push_post);
        
        // execute the curl request
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
            //
            //
        }
        
        // check if connecting was successful
        if($curl_info['http_code'] !== 200)
        {
            // set error text and code
            $error_info = array('code' => 107, 'text' => 'Error connecting to API server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            
            if($curl_info['http_code'] == 503)
            {
                parent::info('Returned HTTP code 503 / gcm reports that server is temporarily unavailable');
            }
            elseif($curl_info['http_code'] == 401)
            {
                parent::debug('Returned HTTP code 401 / gcm reported that there was an error authenticating the sender account');
                // $this->service_auth_token = NULL;
            }
            elseif($curl_info['http_code'] == 500)
            {
                parent::debug('Returned HTTP code 500 / gcm reported that an internal error occured');
                // $this->service_auth_token = NULL;
            }
            
            // return with error
            return FALSE;
        }
        
        // check to see if the gateway returned an error
        // see http://developer.android.com/guide/google/gcm/gcm.html for possible answers
        $matches = array();
        if(preg_match('/^Error=(.+)$/', $result, $matches) === 1)
        {
            $gateway_error = $matches[1];
            // set error text and code
            $error_info = array('code' => 111, 'text' => 'API reported an Error: '.$gateway_error);
            // TODO: moved from error to debug and added WARNING:, could be moved to warn() when implemented
            parent::debug("WARNING: ".$error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            
            if(in_array($gateway_error, $this->api_deactivated_status))
            {
                // set error text and code
                parent::debug('API reports that the device not active.');
                $this->api_last_error = 201;
            }
            else
            {
                parent::debug('gateway returned an error:'.$gateway_error);
                $this->api_last_error = 200;
            }
            return FALSE;
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
     * Frees resources acquired by this object.
    **/
    public function __destruct()
    {
        if($this->curl_handler)
        {
            curl_close($this->curl_handler);
            $this->curl_handler = NULL;
        }
    }
    
    
}



?>