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
 * @copyright Copyright (c) 2010
 * @version 0.4
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 *
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../Submodules/PHP-Tinned-Core/classes/main.class.php');



/**
 * Apple Push Notification Feedback Class (Apple APNS)
 * 
 * This class provides access to the Apple Push Feedback Service (APNs).
 * From the feedback service it is possible to receive a list of devices where 
 * the app was uninstalled. The devices are identified by there push token.
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
 *    
 * 
 * 
 * 
 * @package framework
 * @subpackage mobile-service
 * 
**/
class APNS_Feedback extends Main
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
    
    // variables to hold certificate file paths
    private $security_cert_developer    = NULL;
    private $security_cert_production   = NULL;
    
    // variable for the service authentication
    // 
    // 
    
    // variable to hold the API feedback service url
    private $api_feedback_developer     = 'ssl://feedback.sandbox.push.apple.com:2196';
    private $api_feedback_production    = 'ssl://feedback.push.apple.com:2196';
    
    // variables for decoding the feedback entries
    private $device_token_length        = 32;
    private $timestamp_binary_length    = 4;
    private $token_binary_length        = 2;
    
    // variable to store the feedback entries
    private $feedback_list              = NULL;
    private $feedback_current_item      = NULL;
    
    
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
                    parent::debug('Certificate permission (expected: 644, current: '.$certificate_permission.') - Use it anyway!');
                }
                
                // save the credentials on the class
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
     * Receive feedback Informations
     * 
     * This method is used to receive feedback informations from the API server.
     * The method returns the complete list as well as it saves the list to the
     * class. The items can be retrieved one by one using the class method 
     * get_feedback_entry().
     * 
     * @see get_feedback_entry()
     * @link http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/CommunicatingWIthAPS/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW1
     * 
     * @access public
     * 
     * @return array List of feedback items received from the API server.
    **/
    public function receive_feedback_informations()
    {
        
        // Define variables
        $error = $error_string = NULL;
        
        // connect to system service
        $certificate = $this->security_cert_production;
        $gateway     = $this->api_feedback_production;
        
        // set the development / production informations
        if($this->system_type === 'developer')
        {
            $certificate = $this->security_cert_developer;
            $gateway     = $this->api_feedback_developer;
        }
        
        parent::debug("API URL        : ".$gateway);
        parent::debug("API Credentials: "."$certificate");
        
        
        // Connect to API server
        // 
        // Connect to the server using the certificate
        $ssl_connect = stream_context_create();
        @stream_context_set_option($ssl_connect, 'ssl', 'local_cert', $certificate);
        $socket_connect = stream_socket_client($gateway, $error, $error_string, 20, STREAM_CLIENT_CONNECT, $ssl_connect);
        
        
        // check if connecting was successful
        if($socket_connect === FALSE)
        {
            // set error text and code
            $error_info = array('code' => 107, 'text' => 'Error connecting to API server.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            
            // close connection to the API server
            //@fclose($socket_connect);
            
            // return with error
            return FALSE;
        }
        // Sending data to server connection
        else
        {
            // The API imidiatly starts to send response after the connect
            $read_buffer  = '';
            $entry_length = $this->timestamp_binary_length + $this->token_binary_length + $this->device_token_length;
            parent::debug2('Entry length: '.$entry_length);
            
            // Reading the list from the server
            while(feof($socket_connect) === FALSE)
            {
                $read_buffer .= fread($socket_connect, 8192);
            }
            parent::debug2('Received '.strlen($read_buffer).' Bytes from API.');
            parent::debug2('Received Data: '.$read_buffer);
            
            // calculate the number of entries received
            $entry_count = floor(strlen($read_buffer) / $entry_length);
            
            // decode the received entries and save them to the class
            for ($i = 0; $i < $entry_count; $i++)
            {
                // read one entry from the string
                $entry_data = substr($read_buffer, 0, $entry_length);
                // delete the entry from the string
                $read_buffer = substr($read_buffer, $entry_length);
                
                // parse the binary entry
                $this->feedback_list[$i] = unpack('Ntimestamp/ntokenLength/H*deviceToken', $entry_data);
                
                parent::debug2(sprintf("Entry decoded: timestamp=%d (%s), tokenLength=%d, deviceToken=%s.",
                    $this->feedback_list[$i]['timestamp'], date('Y-m-d H:i:s', $this->feedback_list[$i]['timestamp']),
                    $this->feedback_list[$i]['tokenLength'], $this->feedback_list[$i]['deviceToken']
                ));
                
            }
            
            
        }
        $this->feedback_current_item = 0;
        
        // close connection to the API server
        @fclose($socket_connect);
        
        // return success
        return $this->feedback_list;
    }
    
    
    
    /**
     * get next feedback entry
     * 
     * This method returns the next feedback entry from the list received when 
     * the method receive_feedback_informations() was called.
     * 
     * @param string Parameter description
     * @return string Result value description
    **/
    function get_feedback_entry()
    {
        if($this->feedback_current_item === NULL || isset($this->feedback_list[$this->feedback_current_item]) === FALSE)
        {
            // return NULL counter is NULL (list not loaded) or the end of the list is reached
            return NULL;
        }
        
        return $this->feedback_list[$this->feedback_current_item++];
        
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