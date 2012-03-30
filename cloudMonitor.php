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

    /**
     * @var string
     */
    private $apiEndpoint;
    /**
     * @var string
     */
    private $authEndpoint;
    /**
     * @var string
     */
    private $serverUrl;
    /**
     * @var string
     */
    private $account_id;
    /**
     * @var null
     */
    private $authToken;
    /**
     * @var string
     */
    private $authUser;
    /**
     * @var string
     */
    private $authKey;
    /**
     * @var int
     */
    private $lastResponseStatus;
    /**
     * @var array
     */
    private $callbacks = array();

    /**
     * @var array
     */
    private $check_types
        = array(
            'dns'        => 'remote.dns',
            'ftp'        => 'remote.ftp-banner',
            'imap'       => 'remote.imap-banner',
            'pop3'       => 'remote.pop3-banner',
            'smtp'       => 'remote.smtp-banner',
            'postgresql' => 'remote.postgresql-banner',
            'telnet'     => 'remote.telnet-banner',
            'mysql'      => 'remote.mysql-banner',
            'mssql'      => 'remote.mssql-banner',
            'ssh'        => 'remote.ssh',
            'smtp'       => 'remote.smtp',
            'http'       => 'remote.http',
            'tcp'        => 'remote.tcp',
            'ping'       => 'remote.ping'
        );

    /**
     * @var null
     */
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
    const UK_MONITOR_ENDPOINT = 'https://lon.monitoring.api.rackspacecloud.com';

    /**
     *
     * us dns endpoint ...
     *
     * @var string
     */
    const US_MONITOR_ENDPOINT = 'https://monitoring.api.rackspacecloud.com';

    /**
     * Creates a new Rackspace Cloud Servers API object to make calls with
     *
     * Your API key needs to be generated using the Rackspace Cloud Management
     * Console. You can do this under Cloud Files (not Cloud Servers).
     *
     * Authentication is done automatically when making the first API call
     * using this object.
     *
     *
     * @param string $user The username of the account to use
     * @param string $key  The API key to use
     * @param string $endpoint
     */
    public function __construct($user, $key, $endpoint = 'US')
    {
        $this->authUser = $user;
        $this->authKey = $key;
        $this->authEndpoint = $endpoint == 'US' ? self::US_AUTHURL : self::UK_AUTHURL;
        $this->apiEndpoint = $endpoint == 'US' ? self::US_MONITOR_ENDPOINT : self::UK_MONITOR_ENDPOINT;
        $this->authToken = NULL;
    }


    /**
     * does a api call to get the account api limits
     *
     * @return array|null
     */
    public function get_limits()
    {
        return $this->makeApiCall("/limits");
    }


    /**
     * does a get_account api call
     *
     * @return array|null
     */
    public function get_account()
    {

        return $this->makeApiCall("/account");
    }

    /**
     * @param bool $entity_id
     *
     *
     * @return array|bool|null
     */
    public function get_entity($entity_id = false)
    {
        if (!$entity_id || !is_numeric($entity_id)) {
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id");
    }

    /**
     * @param bool $entity_id
     * @param bool $check_id
     *
     * @return array|bool|null
     */
    public function get_check($entity_id = false, $check_id = false)
    {
        if (!$entity_id || !$check_id) {
            return false;
        }
        return $this->makeApiCall("/entities/$entity_id/checks/$check_id");


    }

    /**
     * @param bool $type
     *
     * @return array|bool|null
     */
    public function get_check_type($type = false)
    {
        if (!array_key_exists($type, $this->check_types)) {
            return false;
        }

        return $this->makeApiCall("/check_types/{$this->check_types[$type]}");
    }

    /**
     * @param bool $zone_id
     *
     * @return array|bool|null
     */
    public function get_zone($zone_id = false)
    {
        if (!$zone_id) {
            return false;
        }

        return $this->makeApiCall("/monitoring_zones/$zone_id");

    }

    /**
     * @param bool $entity_id
     * @param bool $alarm_id
     * @return array|bool|null
     */
    public function get_alarm($entity_id = false, $alarm_id = false)
    {
        if (!$entity_id || !$alarm_id) {
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id/alarms/$alarm_id");

    }

    /**
     * @param bool $entity_id
     * @param bool $alarm_id
     * @param bool $check_id
     * @param bool $uuid
     * @return array|bool|null
     */
    public function get_alarm_notification_history($entity_id = false, $alarm_id = false, $check_id = false, $uuid = false){
            if(!$entity_id || !$alarm_id || $check_id || !$uuid){
                return false;
            }

            return $this->makeApiCall("/entities/$entity_id/alarms/$alarm_id/notification_history/$check_id/$uuid");
        }

    /**
     * @param bool $plan_id
     * @return array|bool|null
     */
    public function get_notification_plan($plan_id = false){
            if(!$plan_id ){
                return false;
            }

            return $this->makeApiCall("/notification_plans/$plan_id");
        }

    /**
     * @param bool $notification_id
     * @return array|bool|null
     */
    public function get_notifications($notification_id = false){
            if(!$notification_id ){
                return false;
            }

            return $this->makeApiCall("/notifications/$notification_id");
        }

    /**
     * @return array|null
     */
    public function get_overview(){
        return $this->makeApiCall('/views/overview');
    }

    /**
     * @return array|null
     */
    public function list_audits()
    {
        return $this->makeApiCall("/audits");
    }

    /**
     * @return array|null
     */
    public function list_entities()
    {
        return $this->makeApiCall("/entities");
    }

    /**
     * @return array|null
     */
    public function list_zones()
    {
        return $this->makeApiCall("/monitoring_zones");
    }

    /**
     * @param bool $entity_id
     *
     * @return array|bool|null
     */
    public function list_checks($entity_id = false)
    {
        if (!$entity_id) {
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id/checks");
    }

    /**
     * @param bool $entity_id
     *
     * @return array|bool|null
     */
    public function list_alarms($entity_id = false)
    {
        if (!$entity_id) {
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id/alarms");
    }

    /**
     * @return array|null
     */
    public function list_alarm_changelogs(){
        return $this->makeApiCall("/changelogs/alarms");
    }

    /**
     * @return array|null
     */
    public function list_check_types()
    {
        return $this->makeApiCall('/check_types');
    }

    /**
     * @return array|null
     */
    public function list_notification_plans()
    {
        return $this->makeApiCall('/notification_plans');
    }

    /**
     * @return array|null
     */
    public function list_notification_types()
    {
        return $this->makeApiCall('/notification_types');
    }

    /**
     * @param bool $notification_id
     * @return array|bool|null
     */
    public function list_notification_type($notification_id = false)
    {

        if(!$notification_id){
            return false;
        }
        return $this->makeApiCall("/notification_types/$notification_id");
    }

    /**
     * @return array|null
     */
    public function list_notifications()
    {
        return $this->makeApiCall('	/notifications');
    }

    /**
     * @param bool $entity_id
     * @param bool $alarm_id
     * @param bool $check_id
     * @return array|bool|null
     */
    public function list_alarm_notification_history($entity_id = false, $alarm_id = false, $check_id = false){
        if(!$entity_id || !$alarm_id || $check_id){
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id/alarms/$alarm_id/notification_history/$check_id");
    }

    /**
     * @param bool $entity_id
     * @param bool $alarm_id
     * @return array|bool|null
     */
    public function discover_alarm_notification_history($entity_id = false, $alarm_id = false){
        if(!$entity_id || !$alarm_id){
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id/alarms/$alarm_id/notification_history");
    }

    /**
     * @param bool  $entity_id
     * @param array $updates
     *
     * @return bool
     */
    public function update_entity($entity_id = false, $updates = array())
    {

        if (!$entity_id || empty($updates)) {
            return false;
        }

        $this->makeApiCall("/entities/$entity_id", $updates, 'PUT');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool  $entity_id
     * @param bool  $check_id
     * @param array $updates
     *
     * @return bool
     */
    public function update_check($entity_id = false, $check_id = false, $updates = array())
    {

        if (!$entity_id || !$check_id || empty($updates)) {
            return false;
        }

        $this->makeApiCall("/entities/$entity_id/checks/$check_id", $updates, 'PUT');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $entity_id
     * @param bool $alarm_id
     * @param array $updates
     * @return bool
     */
    public function update_alarm($entity_id = false, $alarm_id = false, $updates = array())
    {

        if (!$entity_id || !$alarm_id || empty($updates)) {
            return false;
        }

        $this->makeApiCall("/entities/$entity_id/alarms/$alarm_id", $updates, 'PUT');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $webhook_token
     * @param bool $metadata
     *
     * @return array|bool|null
     */
    public function update_account($webhook_token = false, $metadata = false)
    {

        $update = array();
        if ($webhook_token) {
            $update['metadata'] = $metadata;
        }

        if ($metadata) {
            $update['metadata'] = $metadata;
        }

        if (empty($update)) {
            return false;
        }

        $ret = $this->makeApiCall("/account", $update, 'PUT');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;
    }

    /**
     * @param bool $plan_id
     * @param array $options
     * @return array|bool|null
     */
    public function update_notification_plan($plan_id = false, $options = array())
    {


        if (empty($options)) {
            return false;
        }

        $ret = $this->makeApiCall("/notification_plans/$plan_id", $options, 'PUT');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;
    }

    /**
     * @param bool $notification_id
     * @param array $options
     * @return array|bool|null
     */
    public function update_notification($notification_id = false, $options = array())
    {


        if (empty($options)) {
            return false;
        }

        $ret = $this->makeApiCall("/notifications/$notification_id", $options, 'PUT');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;
    }

    /**
     * @param bool $label
     * @param bool $agent_id
     * @param bool $ip_addresses
     * @param bool $metadata
     *
     * @return array|bool|null
     */
    public function create_entity($label = false, $agent_id = false, $ip_addresses = false, $metadata = false)
    {
        if (!$label) {
            return false;
        }
        $insert = array();

        if ($agent_id) {
            $insert['agent_id'] = $agent_id;
        }

        if ($ip_addresses) {
            $insert['ip_addresses'] = $ip_addresses;
        }

        if ($metadata) {
            $insert['metadata'] = $metadata;
        }
        $ret = $this->makeApiCall("/entities", $insert, 'POST');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;


    }


    /**
     * @param bool  $entity_id
     * @param bool  $type
     * @param array $options details|disabled|label|monitoring_zones_poll|period|target_alias|target_hostname|target_resolver|timeout
     *
     * @return array|bool|null
     */
    public function create_check($entity_id = false, $type = false, $options = array())
    {
        if (!$entity_id || !$type || !in_array($type, $this->check_types)) {
            return false;
        }

        if (empty($options)) {
            return false;
        }


        $ret = $this->makeApiCall("/entities/$entity_id/checks", $options, 'POST');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;

    }

    /**
     * @param bool  $entity_id
     * @param array $options
     *
     * @return array|bool|null
     */
    public function create_alarm($entity_id = false, $options = array())
    {
        if (!$entity_id) {
            return false;
        }

        if (isset($options['check_type']) && !in_array($options['check_type'], $this->check_types)) {

        }

        if (empty($options)) {
            return false;
        }


        $ret = $this->makeApiCall("/entities/$entity_id/alarms", $options, 'POST');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;

    }

    /**
     * @param bool $label
     * @param array $options
     * @return array|bool|null
     */
    public function create_notification_plan($label = false, $options = array())
    {
        if (!$label) {
            return false;
        }


        if (empty($options)) {
            return false;
        }


        $ret = $this->makeApiCall("/notification_plans", $options, 'POST');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;

    }

    /**
     * @param bool $details
     * @param bool $label
     * @param bool $type
     * @return array|bool|null
     */
    public function create_notification($details = false, $label = false, $type = false)
    {
        if (!$label || !$details || $type) {
            return false;
        }


        if (empty($options)) {
            return false;
        }


        $ret = $this->makeApiCall("/notifications", $options, 'POST');

        if ($this->callFailed()) {
            return false;
        }

        return $ret;

    }


    /**
     * @param bool   $entity_id
     * @param string $debug
     *
     * @return array|null
     */
    public function test_check($entity_id = false, $debug = '')
    {
        if ($debug != '') {
            $debug = "?debug=true";
        }

        return $this->makeApiCall("/entities/$entity_id/test-check$debug");
    }

    /**
     * @param bool $entity_id
     *
     * @return array|bool|null
     */
    public function test_alarm($entity_id = false)
    {
        if (!$entity_id) {
            return false;
        }

        return $this->makeApiCall("/entities/$entity_id/test-alarm");
    }


    /**
     * @param bool $details
     * @param bool $label
     * @param bool $type
     * @return array|bool|null
     */
    public function test_notification($details = false, $label = false, $type = false)
        {
            if (!$label || !$details || $type) {
                return false;
            }


            if (empty($options)) {
                return false;
            }


            $ret = $this->makeApiCall("/test-notification", $options, 'POST');

            if ($this->callFailed()) {
                return false;
            }

            return $ret;

    }

    /**
     * @param bool $notification_id
     * @return array|bool|null
     */
    public function test_existing_notification($notification_id = false)
        {
            if (!$notification_id ) {
                return false;
            }


            if (empty($options)) {
                return false;
            }


            $ret = $this->makeApiCall("/notifications/$notification_id/test", $options, 'POST');

            if ($this->callFailed()) {
                return false;
            }

            return $ret;

    }

    /**
     * @param bool $entity_id
     *
     * @return bool
     */
    public function delete_entity($entity_id = false)
    {
        if (!$entity_id) {
            return false;
        }

        $this->makeApiCall("/entities/$entity_id", false, 'DELETE');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $notification_id
     * @return bool
     */
    public function delete_notifications($notification_id = false)
    {
        if (!$notification_id) {
            return false;
        }

        $this->makeApiCall("/notifications/$notification_id", false, 'DELETE');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $entity_id
     * @param bool $check_id
     *
     * @return bool
     */
    public function delete_check($entity_id = false, $check_id = false)
    {
        if (!$entity_id  || !$check_id) {
            return false;
        }

        $this->makeApiCall("/entities/$entity_id/checks/$check_id", false, 'DELETE');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $entity_id
     * @param bool $alarm_id
     *
     * @return bool
     */
    public function delete_alarm($entity_id = false, $alarm_id = false)
    {
        if (!$entity_id || !$alarm_id) {
            return false;
        }

        $this->makeApiCall("/entities/$entity_id/alarms/$alarm_id", false, 'DELETE');

        if ($this->callFailed()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $plan_id
     * @return bool
     */
    public function delete_plan($plan_id = false)
    {
        if (!$plan_id) {
            return false;
        }

        $this->makeApiCall("/notification_plans/$plan_id", false, 'DELETE');

        if ($this->callFailed()) {
            return false;
        }

        return true;
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
     * @param string $path
     */
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
     * @return array|null The Jsonparsed response, or NULL if there was an error
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


        $jsonUrl
            = $this->apiEndpoint . '/' . rawurlencode("v" . self::DEFAULT_MONITOR_API_VERSION) . '/' . $this->account_id
            . $url;

        $httpHeaders = array(
            "X-Auth-Token: {$this->authToken}"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $jsonUrl);
        $httpHeaders [] = "Content-Type: application/json";
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

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
    public function isAuthenticated()
    {
        return ($this->serverUrl && $this->authToken);
    }

    /**
     * Authenticates with the API
     *
     * @return boolean TRUE if the authentication was successful
     */
    public function authenticate()
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

                return true;
            }
        }

        return false;
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

    /**
     * @param array $statuses
     *
     * @return bool
     */public function callFailed($statuses = array(400, 401, 403, 404, 500, 503))
    {
        if (in_array($this->getLastResponseStatus(), $statuses)) {
            return true;
        }
        return false;
    }

}


