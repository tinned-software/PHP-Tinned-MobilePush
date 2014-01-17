<?php
/**
 * Mobile_Push interface class
 *
 * This interface provides the common methods which will be shared and 
 * implemented in every Mobile Push service which is included in this package
 *
 * @author Tyler Ashton
 * @copyright Copyright (c) 2014
 * @version 1.0.0
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 * @package framework
 * @subpackage mobile-service
 **/

interface Mobile_Push
{

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
     * @param string $parameter1 A unused parameter for compatibility
     * @param string $parameter2 A unused parameter for compatibility
     * @return boolean TRUE is returned on success and FALSE otherwhise
     **/
    public function set_api_credentials($type, $certificate_file, $parameter1, $parameter2);



    /**
     * Enables or disables keepalive connections.
     *
     * Keep alive can speed up performance if you send more than one message at once.
     *
     * @param bool $enabled TRUE if keep alive should be enabled, FALSE if it should be disabled.
     *
     * @return boolean TRUE is returned on success and FALSE otherwise
     **/
    public function set_keepalive($enabled);



    /**
     * Sets the socket timeout time in seconds.
     *
     * @param int $timeout_time The timeout time in seconds
     *
     * @return boolean TRUE is returned on success and FALSE otherwise
     **/
    public function set_timeout_time($timeout_time);



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
    public function get_api_credentials($type);



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
    public function get_keepalive();



    /**
     * Returns the socket timeout time in seconds
     *
     * @return int representing the timeout time in seconds
     **/
    public function get_timeout_time();



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
    public function set_message_recipient($recipient, $recipient_os);



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
    public function set_message_alert($alert_text, $action_key, $launch_image);



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
     * @access public
     *
     * @param string $localise_key The localise-key for the alert text
     * @param string $localise_args Additional values for the localised string
     * @param string $action_key The lable for the action key (or NULL to hide the button)
     * @param string $launch_image The launch image filename
     * @return boolean TRUE is returned on success and FALSE otherwhise
     **/
    public function set_message_localised($localise_key, $localise_args, $action_key, $launch_image);



    /**
     * Set message dabge
     *
     * This method is used to set the badge number for the App icon on the
     * mobile device. The badge number is shown at the app ichon after the push
     * message is received.
     *
     * @access public
     *
     * @param badge_number
     * @return boolean TRUE is returned on success and FALSE otherwhise
     **/
    public function set_message_badge($badge_number);



    /**
     * Set message sound
     *
     * This method is used to set the sound file for the push message. The
     * sound file is played when the push message is received.
     *
     * @access public
     *
     * @param string $sound_file The soundfile within the mobile device application
     * @return boolean TRUE is returned on success and FALSE otherwhise
     **/
    public function set_message_sound($sound_file);



    /**
     * Set the custom payload.
     *
     * This method attaches a custom payload to the push message. The
     * custom payload must be an array, but there are no requirements
     * as to which fields it may or may not contain. Variable types for
     * subelements are limited to: string, array, integer.
     *
     * @param $payload mixed the custom payload which should be attached to the push notification
     * @return boolean TRUE if successful, FALSE otherwise
     **/
    public function set_custom_payload($payload);



    /**
     * Send the push message
     *
     * This method is used to send the defined push message. The message is
     * immediately sent to the API system. The message content and recipint
     * information is cleared after the message was sent.
     *
     * @access public
     *
     * @return boolean TRUE is returned on success and FALSE otherwhise
     **/
    public function send_push_message();


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
    public function get_last_error($clear, $all_errors);

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
    public function get_api_last_error();



}
?>