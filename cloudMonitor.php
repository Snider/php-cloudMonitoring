<?php
/**
 * NOTE: THE API OF THE create_domain() FUNCTION HAS BEEN UPDATED AND YOU WILL NEED TO UPDATE ITS USE IN YOUR CODE
 *
 *
 * Rackspace DNS PHP API ...
 *
 * This PHP Libary is supported by Original Webware Limited
 *  please register any issues to: https://github.com/snider/php-cloudDNS/issues/
 *
 * Copyright (C) 2011  Original Webware Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 *
 * @author      Paul Lashbrook - OriginalWebware.com
 *
 * @contributor Alon Ben David @ CoolGeex.com
 * @contributor zeut @ GitHub - reported a fix for limiting... totally forgot that bit!
 * @contributor idfbobby @ github - updated the create_domain() function to include comments and ttl
 */ 

class cloudMonitor
{

    private $apiEndpoint;
    private $authEndpoint;
    private $serverUrl;
    private $account_id;
    private $authToken;
    private $authUser;
    private $authKey;
    private $lastResponseStatus;
    private $callbacks = array();

    private $cacert = null;

    /**
     * Timeout in seconds for an API call to respond
     *
     * @var integer
     */
    const TIMEOUT = 20;

    /**
     * Timeout in micro_seconds between API calls
     *
     * @var integer
     */

    const SLEEPTIME = 500000; //500000 micro_seconds = 0.5 seconds


    /**
     *
     * rackspace api version ...
     *
     * @var string
     */
    const DEFAULT_MONITOR_API_VERSION = '1.0';

    /**
     *
     * user agent ...
     *
     * @var string
     */
    const USER_AGENT = 'Cloud Monitor PHP Lib';

    /**
     *
     * usa auth endpoint ...
     *
     * @var string
     */
    const US_AUTHURL = 'https://auth.api.rackspacecloud.com';

    /**
     *
     * uk auth endpoint...
     *
     * @var string
     */
    const UK_AUTHURL = 'https://lon.auth.api.rackspacecloud.com';

    /**
     *
     * uk dns endpoint ...
     *
     * @var string
     */
    const UK_MONITOR_ENDPOINT = 'https://lon.dns.api.rackspacecloud.com';

    /**
     *
     * us dns endpoint ...
     *
     * @var string
     */
    const US_MONITOR_ENDPOINT = 'https://dns.api.rackspacecloud.com';

    /**
     * Creates a new Rackspace Cloud Servers API object to make calls with
     *
     * Your API key needs to be generated using the Rackspace Cloud Management
     * Console. You can do this under Cloud Files (not Cloud Servers).
     *
     * Authentication is done automatically when making the first API call
     * using this object.
     *
     * @todo add a callback function so if one wanted they could call back minutes or
     *       hours later as result is saved for 24 hours... could internally use it with an
     *       option to just return the callback url for the programmer to use later
     *
     *
     * @param string $user The username of the account to use
     * @param string $key  The API key to use
     * @param string $endpoint
     */
    public function __construct($user, $key, $endpoint = 'UK')
    {
        $this->authUser = $user;
        $this->authKey = $key;
        $this->authEndpoint = $endpoint == 'US' ? self::US_AUTHURL : self::UK_AUTHURL;
        $this->apiEndpoint = $endpoint == 'US' ? self::US_MONITOR_ENDPOINT : self::UK_MONITOR_ENDPOINT;
        $this->authToken = NULL;
    }



    /**
     *
     * list domain data by domainID ...
     * List details for a specific domain, using the showRecords and showSubdomains parameters that specify
     * whether to request information for records and subdomains.
     *
     * @param bool|int $domainID
     * @param bool     $showRecords
     * @param bool     $showSubdomains
     *
     * @param int      $limit
     * @param int      $offset
     *
     * @return array|bool
     */
    public function list_domain_details(
        $domainID = false, $showRecords = false, $showSubdomains = false, $limit = 100, $offset = 0
    )
    {
        if ($domainID == false || !is_numeric($domainID)) {
            return false;
        }

        $showRecords = ($showRecords == false) ? 'false' : 'true';
        $showRecords = ($showSubdomains == false) ? 'false' : 'true';

        $url = "/domains/$domainID?showRecords=$showRecords&showSubdomains=$showSubdomains&limit=$limit&offset=$offset";

        return $this->makeApiCall($url);
    }

    /**
     * exports a domain as a BIND9 format ...
     *
     * @param bool|int $domainID
     *
     * @return boolean|array
     */
    public function domain_export($domainID = false)
    {
        if ($domainID == false || !is_numeric($domainID)) {
            return false;
        }

        $url = "/domains/$domainID/export";
        $call = $this->makeApiCall($url);

        $timeout = time() + self::TIMEOUT;

        while ($call ['status'] == 'RUNNING' && $timeout > time()) {

            $this->callbacks [] = $call;
            usleep(self::SLEEPTIME);

            $url = explode('status', $call ['callbackUrl']);

            $call = $this->makeApiCall('/status' . array_pop($url));
        }
        return $call;
    }






    /**
     * creates a new record on a existing zone ...
     *
     * @param bool|int           $domainID
     * @param bool               $records
     * @param bool               $showDetails
     *
     * @return boolean|Ambigous <multitype:, NULL, mixed>
     */
    public function create_domain_record($domainID = false, $records = false, $showDetails = true)
    {
        if (!$domainID || !is_array($records)) {
            return false;
        }

        $postData = array(
            'records' => $records
        );

        $url = "/domains/$domainID/records";

        $call = $this->makeApiCall($url, $postData, 'POST');

        //@todo make callback function to cycle through registered call backs
        $timeout = time() + self::TIMEOUT;

        while ($call ['status'] == 'RUNNING' && $timeout > time()) {
            $this->callbacks [] = $call;
            usleep(self::SLEEPTIME);

            $url = explode('status', $call ['callbackUrl']);
            $url = array_pop($url);
            $url .= $showDetails == true ? '?showDetails=true' : '';

            $call = $this->makeApiCall('/status' . $url);

        }
        return $call;
    }




    public function set_cabundle($path = null)
    {

        if (!is_null($path)) {
            $this->cacert = $path;
        }
    }

    /**
     * Makes a call to an API
     *
     * @param string $url      The relative URL to call (example: "/server")
     * @param string $postData (Optional) The JSON string to send
     * @param string $method   (Optional) The HTTP method to use
     *
     * @return string The Jsonparsed response, or NULL if there was an error
     */
    private function makeApiCall($url, $postData = NULL, $method = NULL)
    {
        // Authenticate if necessary
        if (!$this->isAuthenticated()) {
            if (!$this->authenticate()) {
                return NULL;
            }
        }

        $this->lastResponseStatus = NULL;

        $urlParts = explode('?', $url);

        $url = $urlParts [0] . ".json";

        if (isset ($urlParts [1])) {
            $url .= '?' . $urlParts [1];
        }

        $jsonUrl
            = $this->apiEndpoint . '/' . rawurlencode("v" . self::DEFAULT_MONITOR_API_VERSION) . '/' . $this->account_id
            . $url;

        $httpHeaders = array(
            "X-Auth-Token: {$this->authToken}"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $jsonUrl);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            $httpHeaders [] = "Content-Type: application/json";
        }
        if ($method) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt(
            $ch, CURLOPT_HEADERFUNCTION, array(
                                              &$this,
                                              'parseHeader'
                                         )
        );
        if (!is_null($this->cacert)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_CAINFO, dirname(dirname(__FILE__)) . "/share/cacert.pem");
        }
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $jsonResponse = curl_exec($ch);
        curl_close($ch);

        return json_decode($jsonResponse, true);
    }

    /**
     * Curl call back method to parse header values one by one (there will be
     * many)
     *
     * @param resource $ch     The Curl handler
     * @param string   $header The HTTP header line to parse
     *
     * @return integer The number of bytes in the header line
     */
    private function parseHeader($ch, $header)
    {

        preg_match("/^HTTP\/1\.[01] (\d{3}) (.*)/", $header, $matches);
        if (isset ($matches [1])) {
            $this->lastResponseStatus = $matches [1];
        }

        return strlen($header);
    }

    /**
     * Determines if authentication has been complete
     *
     * @return boolean TRUE if authentication is complete, FALSE if it needs to
     * be done
     */
    private function isAuthenticated()
    {
        return ($this->serverUrl && $this->authToken);
    }

    /**
     * Authenticates with the API
     *
     * @return boolean TRUE if the authentication was successful
     */
    private function authenticate()
    {
        $authHeaders = array(
            "X-Auth-User: {$this->authUser}",
            "X-Auth-Key: {$this->authKey}"
        );

        $ch = curl_init();
        $url = $this->authEndpoint . '/' . rawurlencode("v" . self::DEFAULT_MONITOR_API_VERSION);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, True);
        if (!is_null($this->cacert)) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_CAINFO, dirname(dirname(__FILE__)) . "/share/cacert.pem");
                }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeaders);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        curl_close($ch);

        preg_match("/^HTTP\/1\.[01] (\d{3}) (.*)/", $response, $matches);
        if (isset ($matches [1])) {
            $this->lastResponseStatus = $matches [1];
            if ($this->lastResponseStatus == "204") {
                preg_match("/X-Server-Management-Url: (.*)/", $response, $matches);
                $this->serverUrl = trim($matches [1]);

                $account = explode('/', $this->serverUrl);
                $this->account_id = array_pop($account);
                // TODO Replace this with parsing the correct one once the Load Balancer API goes public
                //$this->apiEndpoint = self::UK_DNS_ENDPOINT; // "https://ord.loadbalancers.api.rackspacecloud.com/v1.0/425464";


                preg_match("/X-Auth-Token: (.*)/", $response, $matches);
                $this->authToken = trim($matches [1]);

                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Translates the HTTP response status from the last API call to a human
     * friendly message
     *
     * @return string The response message from the last call
     */
    public function getLastResponseMessage()
    {
        $map = array(
            "200" => "Successful informational response",
            "202" => "Successful action response",
            "203" => "Successful informational response from the cache",
            "204" => "Authentication successful",
            "400" => "Bad request (check the validity of input values)",
            "401" => "Unauthorized (check username and API key)",
            "403" => "Resize not allowed",
            "404" => "Item not found",
            "409" => "Item already exists",
            "413" => "Over API limit (check limits())",
            "415" => "Bad media type",
            "500" => "Cloud server issue",
            "503" => "API service in unavailable, or capacity is not available"
        );

        $status = $this->getLastResponseStatus();
        if ($status) {
            return $map [$status];
        }

        return "UNKNOWN - Probably a timeout on the connection";
    }

    /**
     * Gets the HTTP response status from the last API call
     *
     * - 200 - successful informational response
     * - 202 - successful action response
     * - 203 - successful informational response from the cache
     * - 400 - bad request (possibly because the input values were invalid)
     * - 401 - unauthorized (check username and API key)
     * - 403 - resize not allowed
     * - 404 - item not found
     * - 409 - build, backup or resize in process
     * - 413 - over API limit (check limits())
     * - 415 - bad media type
     * - 500 - cloud server issue
     * - 503 - API service in unavailable, or capacity is not available
     *
     * @return integer The 3 digit HTTP response status, or NULL if the call had
     * issues
     */
    public function getLastResponseStatus()
    {
        return $this->lastResponseStatus;
    }

}
