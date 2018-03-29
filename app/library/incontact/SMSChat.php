<?php
namespace Incontact;

use Incontact\Models\Sessions;
use Incontact\Models\Settings;
use Incontact\Models\Keywords;
use Tropo\SMS;

/**
 * InContact SMSChat
 *
 * send SMS from tropo.com to InContact Chat Application and viceversa.
 * - InContact API: Patron API
 * - Grant Type: Client
 *
 * @author Oscar Romero
 */

class SMSChat
{
    private $headers=array();
    private $entries=array();
    private $endpoint="";
    public $http_code="";

    public $auth_code;
    public $apicredentials="";
    public $auth_code_url = "https://api.incontact.com/InContactAuthorizationServer/Token";

    public $application_id="";
    public $application = "";
    public $vendor = "";
    public $businessunit = "";
    public $point_of_contact = "";

    public $from_address = "";
    public $chat_room_id = "";
    public $chat_session = "";
    public $phone_number = "";

    /**
     * Get the InContact authentication code
     */
    public function getAuthCode()
    {
        //grant type = client, application_id should be used instead of BU
        $this->auth_code = base64_encode("{$this->application}@{$this->vendor}:{$this->application_id}");
    }

    /**
     * Get the InContact access token
     */
    public function getAccessToken()
    {
        //Setting necessary header options
        $this->headers[] = 'Content-type: application/json; charset=utf-8';
        $this->headers[] = 'Authorization: basic '.$this->auth_code;

        //parameters
        $this->entries=array(
                "grant_type"=>"client_credentials",
                "scope"=>"PatronApi"
            );

        //url to call with curl
        $this->endpoint = $this->auth_code_url;

        $this->apicredentials = $this->executeURL();
    }

    /**
     * Start the chat session
     */
    public function startChatSession()
    {
        if ($this->apicredentials->access_token != "") {
            //Setting necessary header options
            $this->headers[] = 'Content-type: application/json; charset=utf-8';
            $this->headers[] = 'Authorization: bearer ' . $this->apicredentials->access_token;

            //parameters
            $this->entries=array("bu"=>$this->businessunit);

            //point_of_contact is a required field
            $param=rawurlencode($this->point_of_contact);

            //from_address, chat_room_id, parameters are optional fields
            $param1=rawurlencode($this->from_address);
            $param2=rawurlencode($this->chat_room_id);

            //Creating the endpoint for the request
            //Appending api uri with the base uri obtained from the successful token request
            $api_URL="services/v3.0/contacts/chats?pointOfContact=".$param."&fromAddress=".$param1."&chatRoomID=".$param2;

            $this->endpoint = $this->apicredentials->resource_server_base_uri . $api_URL;
            $json = $this->executeURL();

            $this->chat_session = $json->chatSession;
        } else {
            error_log("No access Token");
        }
    }

    /**
     * Send text message to the InContact chat
     *
     * This method send the first chat to an agent, allowing to start the chat session if (the
     * agent accepted the chat in the incontact central)
     *
     * @param  string $message
     * @param  boolean $liveSession liveSession = true is to send text after session has been started
     */
    public function sendTextToChat($message, $liveSession = true)
    {
        if ($this->apicredentials->access_token != "") {
            // chat_session, label and message are required fields
            $param=rawurlencode($this->chat_session);
            $param1=rawurlencode($this->phone_number);
            $param2=rawurlencode($message);
            $api_URL="services/v3.0/contacts/chats/".$param."/send-text?label=".$param1."&message=".$param2;
            //Setting necessary header options
            $this->headers[] = 'Content-length: 0';
            $this->headers[] = 'Content-type: application/x-www-form-urlencoded; charset=utf-8';
            $this->headers[] = 'Authorization: bearer '. $this->apicredentials->access_token;

            // appending api uri with the base uri obtained from the successful token request
            $this->endpoint = $this->apicredentials->resource_server_base_uri .$api_URL;

            $json = $this->executeURL();

            if (!$liveSession) {
                $this->updateLiveSession();
            }
        } else {
            error_log("No access Token");
        }
    }

    /**
     * Get in-bond chat messages from InContact
     *
     * @param  integer $current_chat_session_id  The chat session id
     */
    public function getInbondChatMessages($current_chat_session_id)
    {
        if ($this->apicredentials->access_token != "") {
            //chat_session and timeout are required fields
            $param=rawurlencode($current_chat_session_id);
            $param1=rawurlencode(60);
            $api_URL="services/v3.0/contacts/chats/".$param."?timeout=".$param1;

            // appending api uri with the base uri obtained from the successful token request
            $endpoint = $this->apicredentials->resource_server_base_uri . $api_URL;

            // creating a HTTP GET request to the api
            $handle = curl_init($endpoint);
            curl_setopt($handle, CURLOPT_HEADER, true);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

            // setting necessary header options
            $headers=array();
            $headers[] = 'Content-type: application/x-www-form-urlencoded; charset=utf-8';
            $headers[] = 'Authorization: bearer ' . $this->apicredentials->access_token;
            $headers[] = 'Accept: application/json, text/javascript, */*; q=0.01';
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

            // make the request
            $response = curl_exec($handle);

            // handling valid response
            if ($response!=false) {
                $this->http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                // parsing the response
                $parts = explode("\r\n\r\nHTTP/", $response);

                // getting the final response header using array_pop
                $parts = (count($parts) > 1 ? 'HTTP/' : '').array_pop($parts);
                list($response_headers, $response_body) = explode("\r\n\r\n", $parts, 2);

                if (!empty($response_body)) {
                    // json_decode converts a json string to a PHP variable
                    $parsed_json=json_decode($response_body);

                    if (property_exists($parsed_json, 'error')) {
                        if ($parsed_json->error_description == "SessionEnded") {
                            $this->deleteLiveSession();
                            $this->sendSMS("Session ended. Thanks for contacting 211LA");
                        } else {
                            error_log("{$parsed_json->error_description}");
                        }
                    } else {
                        if (property_exists($parsed_json, 'chatSession')) {
                            // use the response data
                            $next_chat_session_id = $parsed_json->chatSession;

                            if ($next_chat_session_id != "") {
                                // send chat (answer) from agent to tropo
                                $this->sendSMS($parsed_json->messages[0]->Text);
                                $this->getInbondChatMessages($next_chat_session_id);
                            }
                        } else {
                            error_log(print_r($parsed_json, 1));
                            error_log("Something failed...");
                        }
                    }
                } else {
                    // means that user agent did not reply, start the process again
                    $this->getInbondChatMessages($current_chat_session_id);
                }
            }

            curl_close($handle);
        }
    }

    /**
     * Helper function to execute http calls
     * @return json
     */
    private function executeURL()
    {
        /**
         * Creating the endpoint for the request
         * Appending api uri with the base uri obtained from the successful token request
         */
        $parsed_json = "";

        // creating HTTP POST request to the resource
        $handle = curl_init($this->endpoint);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_POST, true);

        // set to TRUE to return the output of curl_exec as a string
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        // setting POST data
        if (!empty($this->entries)) {
            $data_string = json_encode($this->entries);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $data_string);
            $this->headers[] = 'Content-length: '.strlen($data_string);
        }

        // by default, cURL is setup to not trust any CAs
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        // setting necessary header options
        curl_setopt($handle, CURLOPT_HTTPHEADER, $this->headers);

        // make the request
        $response = curl_exec($handle);

        // handling valid response
        if ($response!=false) {
            $this->http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

            // each property in header is a line by itself.
            // the header and the web page content sent together are separated by \r\n\r\n
            $parts = explode("\r\n\r\nHTTP/", $response);

            // getting the final response header using array_pop
            $parts = (count($parts) > 1 ? 'HTTP/' : '').array_pop($parts);
            list($response_headers, $response_body) = explode("\r\n\r\n", $parts, 2);

            if (!empty($response_body)) {
                // json_decode converts a json string to a PHP variable
                $parsed_json=json_decode($response_body);
            }
        }

        // close the curl session
        curl_close($handle);

        // clear headers
        $this->headers=array();
        $this->entries=array();
        $this->endpoint="";

        return $parsed_json;
    }

    /**
     * Update live InContact chat session
     */
    public function updateLiveSession()
    {
        if ($this->apicredentials->access_token != "") {
            $session = Sessions::findFirst("phone_number = '{$this->phone_number}'");

            if (!$session) {
                $session = new Sessions();

                $session->phone_number = $this->phone_number;
                $session->current_chat_session_id = $this->chat_session;
                $session->point_of_contact = $this->point_of_contact;
                $session->resource_server_base_uri = $this->apicredentials->resource_server_base_uri;
                $session->access_token = $this->apicredentials->access_token;
                $session->create();
            }
        }
    }

    /**
     * Delete live session
     */
    public function deleteLiveSession()
    {
        $session = Sessions::findFirst("phone_number = '{$this->phone_number}'");

        if ($session) {
            $session->delete();
        }
    }

    /**
     * Check if a chat session still alive
     *
     * @return boolean
     */
    public function isLiveSession()
    {
        $session = Sessions::findFirst("phone_number = '{$this->phone_number}'");

        if ($session) {
            $this->chat_session = $session->current_chat_session_id;
            $this->point_of_contact = $session->point_of_contact;
            $this->apicredentials->access_token = $session->access_token;
            $this->apicredentials->resource_server_base_uri = $session->resource_server_base_uri;

            $result=true;
        } else {
            $result=false;
        }


        return $result;
    }

    /**
     * Send the text message
     *
     * @param  string $text The text content to be sent
     */
    public function sendSMS($text)
    {
        $sms = new SMS();

        if (strlen($text) > 100) {
            $messages = $sms->splitSMS($text);
            $c=0;
            $total_sms = count($messages);
            $txt="";

            foreach ($messages as $message) {
                $c++;
                $txt =  $sms->_sms_header ."\n". $message ."\n". $sms->_sms_footer ." ($c/$total_sms)";
                $sms->send($this->phone_number, $txt);
                sleep(1);
            }
        } else {
            $formatted_message = $sms->_sms_header ."\n". $text ."\n". $sms->_sms_footer;
            $sms->send($this->phone_number, $formatted_message);
        }
    }

    /**
     * Check if a keyword is valid
     *
     * If the keyword is valid then start chat according to the
     * application settings.
     *
     * @param  string  $text The keyword
     * @return boolean
     */
    public function isValidKeyWord($text)
    {
        $keyword = strtolower(strip_tags($text));

        if ($keyword != '' && (strlen($keyword) <= '15')) {
            //check if keyword is allowed for smschat
            $keyword = Keywords::findFirst("label = '{$keyword}'");

            if ($keyword) {
                $status = 'success';
                $reason =  array('setting_id' => $keyword->setting_id);
            } else {
                $status = 'failure';
                $reason =  'keyword was not found';
            }
        } else {
            $status = 'failure';
            $reason =  'keyword is empty or too long';
        }

        return json_encode(array('status'=>$status, 'reason'=>$reason));
    }

    /**
     * Request a chat session
     *
     * @param  integer $phone      The phone number to send the text
     * @param  string $txt         The message to send
     * @param  integer $setting_id The application setting unique identifier
     * @return json
     */
    public function request($phone, $txt, $setting_id)
    {
        if ($phone != '' && $txt != '' && $setting_id != '') {
            if ($this->isValidPhone($phone)) {
                // get application settings
                $settings = Settings::findFirst($setting_id);

                if ($settings) {
                    $this->application_id = $settings->application_id;
                    $this->application = $settings->application;
                    $this->vendor = $settings->vendor;
                    $this->businessunit = $settings->businnes_unit;
                    $this->point_of_contact = $settings->point_of_contact;
                    $this->phone_number = $phone;

                    $this->getAuthCode();
                    $this->getAccessToken();
                    $this->startChatSession();

                    $this->sendTextToChat($txt, false);
                    $this->getInbondChatMessages($smschat->chat_session);

                    $status = 'success';
                    $reason =  'chat session was started';
                } else {
                    $status = 'failure';
                    $reason =  'Application settings were not found';
                }
            } else {
                $status = 'failure';
                $reason =  'phone number is invalid';
            }
        } else {
            $status = 'failure';
            $reason =  array('phone' => $phone, 'txt'=>$txt, 'settingid' => $setting_id);
        }

        return json_encode(array("status"=>$status, "reason"=>$reason));
    }

    /**
     * Helper method to check if phone is valid
     *
     * @param  integer  $raw_number The raw phone number
     * @return boolean
     */
    public function isValidPhone($raw_number)
    {
        $phonenumber = preg_replace('/[^0-9]/', '', $raw_number);
        return strlen($phonenumber) == 11 ? true : false;
    }
}
