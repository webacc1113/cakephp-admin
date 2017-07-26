<?php

/**
 *      _               _ _
 *   __| |_      _____ | | | __ _
 *  / _` \ \ /\ / / _ \| | |/ _` |
 * | (_| |\ V  V / (_) | | | (_| |
 *  \__,_| \_/\_/ \___/|_|_|\__,_|

 * An official Guzzle based wrapper for the Dwolla API.

 * This class contains methods for all exposed contact related endpoints.
 *
 * get(): Retrieve a user's contacts.
 * nearby(): Get spots near a location.
 */

namespace Dwolla;

require_once('client.php');

class Contacts extends RestClient {

    /**
     * Get contacts from user associated with OAuth token.
     *
     * @param $params {Array} Additional parameters.
     *
     * @return {Array} Contacts.
     */
    public function get($params = false) {
        $p = [
            'oauth_token' => self::$settings->oauth_token
        ];

        if ($params && is_array($params)) { $p = array_merge($p, $params); }

        return self::_get('/contacts', $p);
    }

    /**
     * Returns Dwolla spots near the specified geographical location.
     *
     * @param $lat {Double} Latitudinal coordinates.
     * @param $lon {Double} Longitudinal coordinates.
     * @param bool $params {Array} Additional parameters.
     *
     * @return {Array} Returned spots.
     */
    public function nearby($lat, $lon, $params = false) {
        if (!$lat) { return self::_error("nearby() requires `$lat` parameter.\n"); }
        if (!$lon) { return self::_error("nearby() requires `$lon` parameter.\n"); }

        $p = [
            'client_id' => self::$settings->client_id,
            'client_secret' => self::$settings->client_secret,
            'latitude' => $lat,
            'longitude' => $lon
        ];

        if ($params && is_array($params)) { $p = array_merge($p, $params); }

        return self::_get('/contacts/nearby', $p);
    }
}