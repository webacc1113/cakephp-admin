<?php

/**
 *      _               _ _
 *   __| |_      _____ | | | __ _
 *  / _` \ \ /\ / / _ \| | |/ _` |
 * | (_| |\ V  V / (_) | | | (_| |
 *  \__,_| \_/\_/ \___/|_|_|\__,_|

 * An official Guzzle based wrapper for the Dwolla API.

 * Support is available on our forums at: https://discuss.dwolla.com/category/api-support

 * @package Dwolla
 * @author Dwolla (David Stancu): api@dwolla.com, david@dwolla.com
 * @copyright Copyright (C) 2014 Dwolla Inc.
 * @license  MIT (http://opensource.org/licenses/MIT)
 * @version 2.0.9
 * @link http://developers.dwolla.com
 */

namespace Dwolla;

require_once '_settings.php';

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Exception\RequestException;

class RestClient {

    /**
     * @var $settings
     *
     * Settings object.
     */
    public static $settings;

    /**
     * @var $client
     *
     * Placeholder for Guzzle REST client.
     */
    public static $client;

    /**
     * PHP "magic" getter.
     *
     * @param $name
     * @return $value
     */
    public function __get($name) {
        return $this->$name;
    }

   /**
     * PHP "magic" setter.
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value) {
        $this->$name = $value;
    }

    /**
     * Logs console messages to file for convenience.
     * (Thank you, @redzarf for your contribution)
     *
     * @param $data {???} Can be anything.
     */
    protected function _logtofile($data) {
        if (!empty(self::$settings->logfilePath) && file_exists(self::$settings->logfilePath . "/")) {
            /*
            $t             = microtime(true);
            $micro         = sprintf("%06d", ($t - floor($t)) * 1000000);
            $d             = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));
            $logDate       =;
            $logEntry      = date("Y/m/d H:i:s") . "  $message\n";
            */
            file_put_contents(
                self::$settings->logfilePath . "/" . date("Y-m-d") . ".log",
                date("Y-m-d H:i:s") . '  ' . (is_array($data) ? print_r($data) : trim($data)) . "\n",
                FILE_APPEND
            );
        }
    }

    /**
     * Echos output and logs to console (and js console to make browser debugging easier).
     *
     * @param $data {???} Can be anything.
     */
    protected function _console($data)
    {
        if (self::$settings->debug) {
            if (self::$settings->browserMessages) {
                print("<script>console.log(");
                is_array($data) ? print_r($data) : print($data);
                print(");</script>\n\n");
            }
            if (!empty(self::$settings->logfilePath)) {
                $this->_logtofile($data);
            }
            is_array($data) ? (print_r($data) && print("\n")) : print($data . "\n");
        }
    }

    /**
     * Small error message wrapper for missing parameters, etc.
     *
     * @param $message {String} Error message.
     * @return bool
     */
    protected function _error($message) {
        print("DwollaPHP: " . $message);
        $this->_console("DwollaPHP: " . $message);
        return false;
    }

    /**
     * Parses API response out of envelope and informs user of issues if they arise.
     *
     * @param $response {Array} Response body
     *
     * @return {Array} Data from API
     */
    private function _dwollaparse($response)
    {
        if ($response['Success'] != true)
        {
            $this->_console("DwollaPHP: An API error was encountered.\nServer Message:\n");
            $this->_console($response['Message']);
            if ($response['Response']) {
                $this->_console("Server Response:\n");
                $this->_console($response['Response']);
            }
            return $response['Message'];
        }
        return $response['Response'];
    }


    /**
     * Returns default host URL dependent on sandbox flag.
     *
     * @return {String} Host
     */
    protected function _host() {
        return self::$settings->sandbox ? self::$settings->sandbox_host : self::$settings->production_host;
    }

    /**
     * Wrapper around Guzzle POST request.
     *
     * @param $endpoint {String} API endpoint string
     * @param $request {Array} Request body. JSON encoding is optional.
     * @param $customPostfix {Bool} Use default REST postfix?
     * @param $dwollaParse {Bool} Parse out of message envelope?
     *
     * @return null {Array} Response body.
     */
    protected function _post($endpoint, $request, $customPostfix = false, $dwollaParse = true) {
        // First, we try to catch any errors as the request "goes out the door"
        try {
            $response = $this->client->post($this->_host() . ($customPostfix ? $customPostfix : self::$settings->default_postfix) . $endpoint, ['json' => $request]);
        }
        catch (RequestException $exception) {
            $response = false;
            if (self::$settings->debug){
                $this->_console("DwollaPHP: An error has occurred during a POST request.\nRequest Body:\n");
                $this->_console($exception->getRequest());
                if ($exception->hasResponse()) {
                    $this->_console("Server Response:\n");
                    $this->_console($exception->getResponse());
                }
            }
        }
        if ($response) {
            if ($response->getBody()) {
                // If we get a response, we parse it out of the Dwolla envelope and catch API errors.
                return $dwollaParse ? $this->_dwollaparse($response->json()) : $response->json();
            }
        }
        else {
            if (self::$settings->debug) {
                $this->_console("DwollaPHP: An error has occurred; the response body is empty");
            }
            return null;
        }
    }

    /**
     * Wrapper around Guzzle GET request.
     *
     * @param $endpoint {String} API endpoint string
     * @param $query {Array} Array of URLEncoded query items in key-value pairs.
     * @param $customPostfix {Bool} Use default REST postfix?
     * @param $dwollaParse {Bool} Parse out of message envelope?
     *
     * @return {Array} Response body.
     */
    protected function _get($endpoint, $query, $customPostfix = false, $dwollaParse = true) {
        // First, we try to catch any errors as the request "goes out the door"
        try {
            $response = $this->client->get($this->_host() . ($customPostfix ? $customPostfix : self::$settings->default_postfix) . $endpoint, ['query' => $query]);
        }
        catch (RequestException $exception) {
            $response = false;
            if (self::$settings->debug){
                $this->_console("DwollaPHP: An error has occurred during a GET request.\nRequest Body:\n");
                $this->_console($exception->getRequest());
                if ($exception->hasResponse()) {
                    $this->_console("Server Response:\n");
                    $this->_console($exception->getResponse());
                }
            }
        }
        if ($response) {
            if ($response->getBody()) {
                // If we get a response, we parse it out of the Dwolla envelope and catch API errors.
                return $dwollaParse ? $this->_dwollaparse($response->json()) : $response->json();
            }
        }
        else {
            if (self::$settings->debug) {
                $this->_console("DwollaPHP: An error has occurred; the response body is empty");
            }
            return null;
        }
    }

    /**
     * Constructor. Takes no arguments.
     */
    public function __construct() {

        self::$settings = new Settings();
        self::$settings->host = self::$settings->sandbox ?  self::$settings->sandbox_host : self::$settings->production_host;

        $this->settings = self::$settings;

        $p = [
            'defaults' => [
                'headers' =>
                            [
                                'Content-Type' => 'application/json',
                                'User-Agent' => 'dwolla-php/2'
                            ],
                'timeout' => self::$settings->rest_timeout
            ]
        ];

        if (self::$settings->proxy) { $p['proxy'] = self::$settings->proxy; }

        $this->client = new Client($p);
    }
}

