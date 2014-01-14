<?php
/**
 * @author Tyler Ashton (tyler.ashton [at] tinned-software [dot] net)
 * @copyright Copyright (c) 2012 - 2014
 * @version 0.8.16
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 *
 * Microsoft Push Notification Service (MPNS)
 * 
 * This class provides an interface to the Microsoft Push Notification Service.
 * 
 * @todo add proper check for header / payload length (1KB, 3KB)
**/


/**
 * Include required files
**/
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/main.class.php');
include_once(dirname(__FILE__).'/../../PHP-Tinned-Core/classes/xml_manager.class.php');


/**
 * Microsoft Push Notification Service (MPNS)
 * 
 * This class provides an interface to the Microsoft Push Notification Service.
 * MPNS Notification work starting at OS version 7.0 with some features of the
 * specific types only being available in OSes greater than 7.0.
 * 
 * MPNS supports three different types of notifications: <br/>
 * 1) Tile: updates app icon / badge. <br/>
 * 2) Toast: shows a text in the status bar of the mobile device, optionally 
 *     opens a specific page in the app. <br/>
 * 3) Raw: sends a raw byte stream to the app. App must be running to 
 *     receive this type of notification, otherwise it is discarded. <br/>
 * @see http://msdn.microsoft.com/en-us/library/hh202945(v=vs.92).aspx
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
class MPNS extends Main
{
    ////////////////////////////////////////////////////////////////////////////
    // PROPERTIES of the class
    ////////////////////////////////////////////////////////////////////////////
    
    // constants as defined by the MPNS API documentation
    const NOTICATION_TYPE_TOAST = 'toast';
    const NOTICATION_TYPE_TILE  = 'token';
    const NOTICATION_TYPE_RAW   = 'raw';

    // constants as defined by the MPNS API documentation
    const NOTICATION_CLASS_TOAST = 2;
    const NOTICATION_CLASS_TILE  = 1;
    const NOTICATION_CLASS_RAW   = 3;

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
    private $db_object                  = NULL;
    private $db_table                   = NULL;
    
    // variables to hold certificate file paths
    private $certificate_file           = NULL;
    private $certificate_key            = NULL;
    private $ca_certificate             = NULL;
    
    // variable for the service authentication
    private $service_auth_url           = NULL;
    private $service_auth_token         = NULL;
    
    // variables to hold API gateway addresses
    private $api_gateway_developer      = NULL;
    private $api_gateway_production     = NULL;
    
    // gateway return variables which indicate a deactivated device
    private $api_deactivated_status     = NULL;
    private $api_last_error             = NULL;
    
    // variable to hold the API feedback service url
    private $api_feedback_developer     = '';
    private $api_feedback_production    = '';
    
    // define if the table should be searched (and cleaned if required) for douplicate entries.
    private $check_unique_devices       = FALSE;
    
    // define variable to store message details
    private $message_content            = NULL;
    private $message_recipient          = NULL;
    private $message_target_os          = '';
    
    // MPNS push message contents
    private $message_uuid               = NULL;
    
    // set the maximum message length
    private $header_max_length          = 1024;
    private $message_max_length         = 3072;
    
    // keep connection open
    private $keepalive                  = FALSE;
    private $curl_handler               = NULL;
    
    // Timeout time in seconds
    private $timeout_time               = 10;
    
    // helper object
    private $_xml_manager               = NULL;
    
    
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
     * The $file_path is used to set the API certificate.
     * The $unused_parameter is not used in this class. It is available for 
     * compatibility mode.
     *
     * Note on Certificate Authority: the parameter is optional. The file 
     * must contain one or more certificate Authority Certificates, saved in PEM
     * format. These are passed to the CURL functions in order to authenticate
     * with the MPNS gateway.
     * 
     * If the proper certificates are not available to the CURL library either
     * in the Operating System's CA list or specified in this file, the gateway
     * will report a HTTP 403 permission denied / unauthorized error. See CURL
     * Documentation for further details.
     * 
     * @see curl_setopt()
     * @access public
     * 
     * @param string $type The type of certificate (production, developer)
     * @param string $certificate_file The path and file name to the certificate file
     * @param string $certificate_key The path and filename to the key used to generate the certificate file
     * @param string $ca_certificate optional The path to the Certificate Authority certificate(s), must be saved in PEM format
     * @return boolean TRUE is returned on success and FALSE otherwhise
    **/
    public function set_api_credentials($type, $certificate_file, $certificate_key, $ca_certificate)
    {
        parent::debug2(__FUNCTION__." set_api_credentials($type, $certificate_file, $certificate_key, $ca_certificate) ");
        
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
                parent::debug2('Certificate permission (expected: 644, current: '.$certificate_permission.') - Use it anyway!');
                $this->certificate_file = $certificate_file;
                parent::debug('Defined certificate file = '.basename($this->certificate_file));
                
            }
            else
            {
                $error_info = array('code' => 102, 'text' => 'Certificate file does not exist or is not readable.');
                parent::error($error_info['code'].': '.$error_info['text']);
                $this->last_error[] = $error_info;
                return FALSE;
            }
            
            if(is_readable($certificate_key) === TRUE)
            {
                // check the file permission of the certificate
                clearstatcache();
                $certificate_permission = substr(sprintf('%o', fileperms($certificate_key)), -3);
                if($certificate_permission > 644)
                {
                    $error_info = array('code' => 104, 'text' => 'Certificate key has wrong permission (644 expected)');
                    parent::warning($error_info['code'].': '.$error_info['text']);
                    $this->last_error[] = $error_info;
                    //return FALSE;
                }
                
                // save the credentials on the class
                parent::debug2('Certificate permission (expected: 644, current: '.$certificate_permission.') - Use it anyway!');
                $this->certificate_key = $certificate_key;
                parent::debug('Defined certificate key = '.basename($this->certificate_key));
                
            }
            else
            {
                $error_info = array('code' => 102, 'text' => 'Certificate file does not exist or is not readable.');
                parent::error($error_info['code'].': '.$error_info['text']);
                $this->last_error[] = $error_info;
                return FALSE;
            }
            
            if(is_readable($ca_certificate) === TRUE)
            {
                // check the file permission of the certificate
                clearstatcache();
                $certificate_permission = substr(sprintf('%o', fileperms($ca_certificate)), -3);
                if($certificate_permission > 644)
                {
                    $error_info = array('code' => 104, 'text' => 'CA certificate has wrong permission (644 expected)');
                    parent::warning($error_info['code'].': '.$error_info['text']);
                    $this->last_error[] = $error_info;
                    //return FALSE;
                }
                
                // save the credentials on the class
                parent::debug2('Certificate permission (expected: 644, current: '.$certificate_permission.') - Use it anyway!');
                $this->ca_certificate = $ca_certificate;
                parent::debug('Defined CA certificate = '.basename($this->ca_certificate));
                
            }
        }
        else
        {
            $error_info = array('code' => 103, 'text' => 'Wrong type parameter value.');
            parent::error($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
        }
        
        // return true 
        return TRUE;
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
     *
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
     * Keep alive means that the connection to the C2DM push service is kept
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
    private function _check_message_size($payload)
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
        //
        
        
        // get the payload size
        $payload_size = mb_strlen($payload);
        // calculate the difference between max-size and payload-size (x chars bigger then allowed)
        $payload_diff = $payload_size - $this->message_max_length;
        // calculate the number of remaining characters after truncating
        $remaining_chars = $payload_size - $payload_diff;
        parent::debug2("Payload size of message is $payload_size / ".$this->message_max_length." characters.");
        parent::debug2("Payload exceeded by $payload_diff, remaining characters of message $remaining_chars.");
        
        if($payload_size > $this->message_max_length)
        {
            // set error text and code
            $error_info = array('code' => 110, 'text' => 'Maximum allowed message size exceeded (exceeded by '.$payload_diff.' bytes)');
            parent::warning($error_info['code'].': '.$error_info['text']);
            $this->last_error[] = $error_info;
            return FALSE;
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
    private function _build_message_payload($type = NULL)
    {
        if(is_object($this->_xml_manager) !== FALSE)
        {
            if(get_class($this->_xml_manager) !== 'XML_Manager')
            {
                parent::debug('WARNING: Helper object is not a XML_Manager object!');
                return FALSE;
            }
        }
        else
        {
            parent::debug('WARNING: XML Manager helper object not set!');
            return FALSE;
        }
        
        $return_string = '';
        
        switch($type)
        {
            case self::NOTICATION_TYPE_TOAST:
                
                $toast_alert = array();
                
                if(isset($this->message_content['alert']) === TRUE)
                {
                    $toast_alert['wp:Notification']['@attributes']['xmlns:wp'] = 'WPNotification';
                    $toast_alert['wp:Notification']['wp:Toast']['wp:Text1']['@value'] = $this->message_content['alert'];
                    if(isset($this->message_content['custom']['summary']) === TRUE)
                    {
                        $toast_alert['wp:Notification']['wp:Toast']['wp:Text2']['@value'] = $this->message_content['custom']['summary'];
                    }
                    //$toast_alert['wp:Notification']['wp:Toast']['wp:Param']['@value'] = ''; // 7.1+ only
                    
                    parent::debug2('Toast Payload Contents:'.print_r($toast_alert, TRUE));
                }
                
                if(isset($this->message_content['sound']) === TRUE)
                {
                    parent::debug('WARNING: sound is not supported by the MPNS gateway.');
                    // not supported until 8.0...?
                    //$toast_alert;
                }
                
                if(count($toast_alert) > 0)
                {
                    parent::debug2("Building XML Payload: $type");
                    $return_string = $this->_xml_manager->array_to_xmlstring($toast_alert);
                }
                
            break;
            
        case self::NOTICATION_TYPE_TILE:
            
            $tile_alert = array();
            
            if(isset($this->message_content['badge']) === TRUE || isset($this->message_content['launch_image']) === TRUE)
            {
                $tile_alert['wp:Notification']['@attributes']['xmlns:wp'] = 'WPNotification';
                //$tile_alert['wp:Notification']['wp:Tile']['@attributes']['Id'] = ''; // 7.1+ only, which "tile" to update (main,secondary,etc...)
                $tile_alert['wp:Notification']['wp:Tile']['wp:BackgroundImage']['@value'] = NULL;
                $tile_alert['wp:Notification']['wp:Tile']['wp:Count']['@value'] = $this->message_content['badge'];
                $tile_alert['wp:Notification']['wp:Tile']['wp:Title']['@value'] = '...';
                if(isset($this->message_content['custom']['tile_background']) === TRUE)
                {
                    if(isset($this->message_content['custom']['tile_background']['BackBackgroundImage']) === TRUE)
                    {
                        $tile_alert['wp:Notification']['wp:Tile']['wp:BackBackgroundImage']['@value'] = $this->message_content['custom']['tile_background']['BackBackgroundImage'];
                    }
                    if(isset($this->message_content['custom']['tile_background']['BackTitle']) === TRUE)
                    {
                        $tile_alert['wp:Notification']['wp:Tile']['wp:BackTitle']['@value'] = $this->message_content['custom']['tile_background']['BackTitle']; // 7.1+ only
                    }
                    if(isset($this->message_content['custom']['tile_background']['BackContent']) === TRUE)
                    {
                        $tile_alert['wp:Notification']['wp:Tile']['wp:BackContent']['@value'] = $this->message_content['custom']['tile_background']['BackContent'];
                    }
                }
                parent::debug2('Tile Payload Contents:'.print_r($tile_alert, TRUE));
            }
            
            if(count($tile_alert) > 0)
            {
                parent::debug2("Building XML Payload: $type");
                $return_string = $this->_xml_manager->array_to_xmlstring($tile_alert);
            }
            
            break;
            
        case self::NOTICATION_TYPE_RAW:
            
            $raw_alert = array();
            
            if(isset($this->message_content['custom']) === TRUE)
            {
                
                foreach($this->message_content['custom'] as $key => $value)
                {
                    $raw_alert['root'][$key]['@value'] = $value;
                }
                
                parent::debug2('Raw Payload Contents:'.print_r($raw_alert, TRUE));
            }
            
            if(count($raw_alert) > 0)
            {
                parent::debug2("Building XML Payload: $type");
                $return_string = $this->_xml_manager->array_to_xmlstring($raw_alert);
            }
            
            break;
        }
        
        // return the payload
        return $return_string;
    }
    
    
    
    /**
     * Private method to build correct CURL headers for each message type.
     *
     * @param $mpns_type string the type of notification for which to build headers
     * @param $uuid_generate boolean force regeneration of a unique identifier for the messages 
     * @return array containing the headers which can be passed to CURL
    **/    
    private function _get_header_payload($mpns_type, $uuid_generate = FALSE)
    {
        $headers   = array();
        $headers[] = 'Content-type: text/xml';
        $headers[] = 'Connection: Keep-Alive';
        $headers[] = 'Keep-Alive: '.$this->timeout_time;
        if($uuid_generate === TRUE || isset($this->message_uuid) === FALSE)
        {
            // generate a UUID for the header
            $r = unpack('v*', fread(fopen('/dev/random', 'r'),16));
            $this->message_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                $r[1], $r[2], $r[3], $r[4], 
                $r[5], $r[6], $r[7], $r[8]);
        }
        $headers[] = 'X-MessageID: '.$this->message_uuid;
        
        switch($mpns_type)
        {
            case self::NOTICATION_TYPE_RAW:
                $headers[] = 'X-WindowsPhone-Target: '.self::NOTICATION_TYPE_RAW;
                $headers[] = 'X-NotificationClass: '.self::NOTICATION_CLASS_RAW;
                
                break;
                
            case self::NOTICATION_TYPE_TILE:
                $headers[] = 'X-WindowsPhone-Target: '.self::NOTICATION_TYPE_TILE;
                $headers[] = 'X-NotificationClass: '.self::NOTICATION_CLASS_TILE;
                
                break;
                
            case self::NOTICATION_TYPE_TOAST:
                $headers[] = 'X-WindowsPhone-Target: '.self::NOTICATION_TYPE_TOAST;
                $headers[] = 'X-NotificationClass: '.self::NOTICATION_CLASS_TOAST;
                
                break;
                
            default:
                
                break;
        }
        
        parent::debug2("headers for $mpns_type: ".print_r($headers, TRUE));
        return $headers;
    }
    
    
    
    /**
     * Check / output any curl_error messages which were detected
     * 
     * @param resource $handler A curl handler to check for an error
     * @return void
     **/
    private function _check_curl_error($handler, $line)
    {
        if(curl_error($handler) != '')
        {
            parent::info('curl_error from line:'.$line.' "'.curl_error($handler).'"');
        }
    }
    
    
    
    /**
     * Checks the response from MPNS to see what the status of the last request was.
     *
     * This method checks a CURL result body / headers to see what the status
     * of the last push request was. The MPNS API documentation describes which
     * variables can be read from the gateway: some of the response states are 
     * returned as HTTP headers, and some are returned directly using the HTTP
     * response code (e.g 200/OK, etc..). This method should be called after 
     * each HTTP request to the MPNS API server.
     * 
     * This method also records any relevant API errors in class variables to
     * report inactive devices.
     * 
     * @see http://msdn.microsoft.com/en-us/library/ff941100(v=vs.92).aspx
     * 
     * @return void     
     **/
    private function _check_http_response($response = NULL, $curl_info = NULL)
    {
        if(isset($response) === FALSE || isset($curl_info) === FALSE)
        {
            return;
        }
        
        parent::debug2("curl_getinfo():\n".print_r($curl_info, TRUE));
        
        if(empty($response) === TRUE)
        {
            parent::debug('WARNING: empty response body received by "'.__FUNCTION__.'" cannot evaluate it accordingly.');
            return;
        }
        
        // break the header and the body apart.
        list($header, $body) = explode("\r\n\r\n", $response, 2);
        
        // parent::debug2("response header '$header'");
        // parent::debug2("response body '$body'");
        
        $header_array = preg_split("/\n/", $header);
        $header_array_count = count($header_array);
        $header_keyed_array = array();
        
        // skip the first line which is per HTTP the response code
        for($i = 1; $i < $header_array_count; $i++)
        {
            list($key, $value) = explode(': ', $header_array[$i], 2);
            $header_keyed_array[$key] = $value;
        }
        
        $notification_status = NULL;
        $device_connection_status = NULL;
        $subscription_status = NULL;
        if(isset($header_keyed_array['X-NotificationStatus']))
        {
            $notification_status = trim($header_keyed_array['X-NotificationStatus']);
        }
        if(isset($header_keyed_array['X-SubscriptionStatus']))
        {
            $subscription_status = trim($header_keyed_array['X-SubscriptionStatus']);
        }
        if(isset($header_keyed_array['X-DeviceConnectionStatus']))
        {
            $device_connection_status = trim($header_keyed_array['X-DeviceConnectionStatus']);
        }
        
        // parent::debug2("notification_status: $notification_status");
        // parent::debug2("subscription_status: $subscription_status");
        // parent::debug2("device_connection_status: $device_connection_status");
        // parent::debug2("http_response_code: {$curl_info['http_code']}");
        
        switch($curl_info['http_code'])
        {
            case 400:
                // malformed XML payload
            case 401:
                // sending of notification not authorized
            case 405:
                // invalid method (PUT, DELETE, CREATE)
            case 406:
                // throttling limit reached
            case 412:
                // device is in an inactive state, try again later
            case 503:
                // MPNS is unable to process the request, try again later
                $error_info = array('code' => 200, 'text' => 'Push Gateway reports a general error, HTTP code: '.$curl_info['http_code']);
                $this->api_last_error = 200;
                $this->last_error[] = $error_info;
                break;
                
            case 404:
                // The subscription is invalid and is not present on the Push Notification Service.
                $error_info = array('code' => 201,  'text' => 'Push Gateway reports that the device is no longer active');
                $this->api_last_error = 201;
            case 200:
                // good response, push successful
                break;
                
            default:
                break;
        }
        
        $dbg_message = "notification_status: $notification_status, ";
        $dbg_message .= "subscription_status: $subscription_status, ";
        $dbg_message .= "device_connection_status: $device_connection_status, ";
        $dbg_message .= "http_response_code: {$curl_info['http_code']}";
        parent::debug($dbg_message);
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
     * @param string $receipent The recipient value or push uri
     * @param string $recipient_os The OS of the recipient device ("android", "aps", "blackberry")
     * @return boolean TRUE is returned on success and FALSE otherwhise
     * @todo rewrite docblock, adapt function
    **/
    public function set_message_recipient($recipient, $recipient_os = 'winphone')
    {
        if(preg_match('/^https?\:\/\/[a-zA-Z0-9-]+[\/a-zA-Z0-9.+-]+$/', $recipient) >= 1 || ($recipient_os != 'winphone' || $recipient_os !== 'android' || $recipient_os !== 'aps' || $recipient_os !== 'blackberry'))
        {
            // store recipient in class
            $this->message_recipient = $recipient;
            $this->message_target_os = $recipient_os;
            
            parent::debug2('set message_recipient '.$recipient);
            parent::debug2('set message_target_os '.$recipient_os);
            
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
            // define message content
            // $this->message_content['device_token'] = $recipient;
            
            
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
     * shown on the mobile device when the message is received. This text is
     * sent instantly using the internal "toast" notification type.
     * 
     * @access public
     * 
     * @param string $alert_text The push message text
     * @param string $action_key The lable for the action key (or NULL to hide the button)
     * @param string $launch_image The launch image filename
     * @return boolean TRUE is returned on success and FALSE otherwhise
     * @todo rewrite docblock, adapt function
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
     * @todo rewrite docblock, adapt function
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
     * @todo rewrite docblock, adapt function
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
        
        $this->message_content['badge'] = $badge_number;
        
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
     * sound file is played when the push message is received. Seems to be
     * supported only in WinPhone OS 8.0 and up. The sound must be one of
     * the windows phone predefined variables according to the following link:
     * 
     * @link http://msdn.microsoft.com/en-us/library/windows/apps/hh761492.aspx#examples
     * 
     * @access public
     * 
     * @param string $sound_file The soundfile within the mobile device application
     * @return boolean TRUE is returned on success and FALSE otherwhise
     * @todo rewrite docblock, adapt function
     * @todo is this function supported...?
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
     * custom payload must be an array, but there are no requirements 
     * as to which fields it may or may not contain. Variable types  for
     * subelements are limited to: string, array, integer.
     * 
     * @param $payload mixed the custom payload which should be attached to the push notification
     * @return boolean TRUE if successful, FALSE otherwise
     * @todo rewrite docblock, adapt function
     **/
    public function set_custom_payload($payload = NULL)
    {
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
                        $value3["$key.$key2"] = $value2;
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
        
        parent::debug("Send message content ... \n".print_r($this->message_content, TRUE));
        
        // generate XML payload
        $tile_xml = $this->_build_message_payload(self::NOTICATION_TYPE_TILE);
        $toast_xml = $this->_build_message_payload(self::NOTICATION_TYPE_TOAST);
        $raw_xml = $this->_build_message_payload(self::NOTICATION_TYPE_RAW);
        
        // check for an appropriate payload length
        $this->_check_message_size($tile_xml);
        $this->_check_message_size($toast_xml);
        $this->_check_message_size($raw_xml);
        
        
        $certificate_file = $this->certificate_file;
        $certificate_key  = $this->certificate_key;
        $ca_certificate   = $this->ca_certificate;

        
        // Define variables
        $error = $error_string = NULL;
        
        // prepare parameter for connecting to system service
        $gateway  = $this->api_gateway_production;
        //
        //
        
        // Connect to API server
        // 
        // Send the HTTP Basic Authentication request
        if(empty($this->curl_handler) === TRUE)
        {
            $this->curl_handler = curl_init();
            curl_setopt($this->curl_handler, CURLOPT_URL, $this->message_recipient);
            
            if(is_null($certificate_file) === FALSE && is_null($certificate_key) === FALSE)
            {
                curl_setopt($this->curl_handler, CURLOPT_SSLCERT, $certificate_file);
                parent::debug2("set certificate:$certificate_file");
                $this->_check_curl_error($this->curl_handler, __LINE__);

                curl_setopt($this->curl_handler, CURLOPT_SSLKEY, $certificate_key);
                parent::debug2("set certificate key:$certificate_key");
                $this->_check_curl_error($this->curl_handler, __LINE__);

                parent::debug("Done setting up SSL/TLS cert and key for 'Authenticated Web Service'");
            }
            if(is_null($ca_certificate) === FALSE)
            {
                curl_setopt($this->curl_handler, CURLOPT_CAINFO, $ca_certificate);
                parent::debug2("set CA certificate:$ca_certificate");
                $this->_check_curl_error($this->curl_handler, __LINE__);

                parent::debug("Done setting up SSL/TLS CA cert for 'Authenticated Web Service'");
            }
            curl_setopt($this->curl_handler, CURLOPT_POST, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_HEADER, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($this->curl_handler, CURLOPT_SSL_VERIFYHOST, 2); // CURL default value
            curl_setopt($this->curl_handler, CURLOPT_SSL_VERIFYPEER, TRUE);
            
            curl_setopt($this->curl_handler, CURLOPT_CONNECTTIMEOUT, $this->timeout_time);
            curl_setopt($this->curl_handler, CURLOPT_TIMEOUT, $this->timeout_time);
        }
        
        //
        // init the array
        $curl_info = NULL;
        
        // 
        // build push messages
        $debug_output = array();
        if(empty($tile_xml) === FALSE)
        {
            $debug_output[] = 'tile';
            parent::debug2('xml:'.$tile_xml);
            curl_setopt($this->curl_handler, CURLOPT_HTTPHEADER, $this->_get_header_payload(self::NOTICATION_TYPE_TILE, TRUE));
            curl_setopt($this->curl_handler, CURLOPT_POSTFIELDS, $tile_xml);
            $result = curl_exec($this->curl_handler);
            $this->_check_curl_error($this->curl_handler, __LINE__);
            $curl_info = curl_getinfo($this->curl_handler);
            //parent::debug2("Tile Curl result:".$result);
            //parent::debug2("Tile Curl result:".print_r($curl_info, TRUE));
            $this->_check_http_response($result, $curl_info);
        }
        if(empty($toast_xml) === FALSE)
        {
            $debug_output[] = 'toast';
            parent::debug2('xml:'.$toast_xml);
            curl_setopt($this->curl_handler, CURLOPT_HTTPHEADER, $this->_get_header_payload(self::NOTICATION_TYPE_TOAST, FALSE));
            curl_setopt($this->curl_handler, CURLOPT_POSTFIELDS, $toast_xml);
            $result = curl_exec($this->curl_handler);
            $this->_check_curl_error($this->curl_handler, __LINE__);
            $curl_info = curl_getinfo($this->curl_handler);
            //parent::debug2("Toast Curl result:".$result);
            //parent::debug2("Toast Curl result:".print_r($curl_info, TRUE));
            $this->_check_http_response($result, $curl_info);
        }
        if(empty($raw_xml) === FALSE)
        {
            $debug_output[] = 'raw';
            parent::debug2('xml:'.$raw_xml);
            curl_setopt($this->curl_handler, CURLOPT_HTTPHEADER, $this->_get_header_payload(self::NOTICATION_TYPE_RAW, FALSE));
            curl_setopt($this->curl_handler, CURLOPT_POSTFIELDS, $raw_xml);
            $result = curl_exec($this->curl_handler);
            $this->_check_curl_error($this->curl_handler, __LINE__);
            $curl_info = curl_getinfo($this->curl_handler);
            //parent::debug2("Raw Curl result:".$result);
            //parent::debug2("Raw Curl result:".print_r($curl_info, TRUE));
            $this->_check_http_response($result, $curl_info);
        }
        
        // close connection to the API server
        if(empty($this->keepalive))
        {
            curl_close($this->curl_handler);
            $this->curl_handler = NULL;
            //
            //
        }
        
        parent::debug('sent types: '.implode(', ', $debug_output));
        
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
            $this->_xml_manager = $class_reference;
            return;
        }
        
        $error_info = array('code' => 112, 'text' => 'helper class was not usable: classname:\''.$class_name.'\'.');
        parent::error($error_info['code'].': '.$error_info['text']);
        parent::report_error($error_info['code'],$error_info['text']);
    }
    
}



?>