<?php

/**
 *      _               _ _
 *   __| |_      _____ | | | __ _
 *  / _` \ \ /\ / / _ \| | |/ _` |
 * | (_| |\ V  V / (_) | | | (_| |
 *  \__,_| \_/\_/ \___/|_|_|\__,_|

 * An official Guzzle based wrapper for the Dwolla API.

 * All recommended user configurable options are available below.
 */

namespace Dwolla;

// Instead of manually setting multiple variables in the main constructor, we just use one big settings class.

class Settings {

    public $client_id = DWOLLA_MASTER_KEY;
    public $client_secret = DWOLLA_MASTER_SECRET;
	public $pin = DWOLLA_MASTER_PIN;

    public $oauth_scope = "Send|Transactions|Balance|Request|Contacts|AccountInfoFull|Funding|ManageAccount";
    public $oauth_token = "OAUTH TOKENS GO HERE";
    public $refresh_token = "REFRESH TOKENS GO HERE";

    // Hostnames, endpoints
    public $production_host = 'https://www.dwolla.com/';
    public $sandbox_host = 'https://uat.dwolla.com/';
    public $default_postfix = 'oauth/rest';

    // Client behavior
    public $sandbox = false;
    public $debug = false;
    public $browserMessages = false;
    public $logfilePath = '';
    public $rest_timeout = 15;
    public $proxy = false;

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

}
