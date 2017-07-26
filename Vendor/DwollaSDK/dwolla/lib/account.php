<?php

/**
 *      _               _ _
 *   __| |_      _____ | | | __ _
 *  / _` \ \ /\ / / _ \| | |/ _` |
 * | (_| |\ V  V / (_) | | | (_| |
 *  \__,_| \_/\_/ \___/|_|_|\__,_|

 * An official Guzzle based wrapper for the Dwolla API.

 * This class contains methods for all exposed account related endpoints:
 *
 * basic(): Retrieves basic account information
 * full(): Retrieve full account information
 * balance(): Get user balance
 * nearby(): Get nearby users
 * getAutoWithdrawal(): Get auto-withdrawal status
 * toggleAutoWithdrawal(): Toggle auto-withdrawal
 */

namespace Dwolla;

require_once('client.php');

class Account extends RestClient {

    /**
     * Returns basic account information for the account associated
     * with the passed account ID.
     *
     * @param $account_id {String} Account ID.
     *
     * @return {Array} Array of basic account information.
     */
    public function basic($id) {
        if (!$id) { return self::_error("basic() requires `\$id` parameter.\n"); }

        return self::_get('/users/' . $id,
            [
                'client_id' => self::$settings->client_id,
                'client_secret' => self::$settings->client_secret
            ]);
    }

    /**
     * Returns full account information for the account associated
     * with the current OAuth token.
     *
     * @return {Array} Array of full account information.
     */
    public function full() {
        return self::_get('/users/',
            [
                'oauth_token' => self::$settings->oauth_token
            ]);
    }

    /**
     * Returns balance of the account associated with the current
     * OAuth token.
     *
     * @return {Integer} Balance of account.
     */
    public function balance() {
        return self::_get('/balance/',
            [
                'oauth_token' => self::$settings->oauth_token
            ]);
    }

    /**
     * Returns users and venues near a location.
     *
     * @param $lat {String} Latitudinal coordinates.
     * @param $lon {String} Longitudinal coordinates.
     *
     * @return {Array} Array with users, venues, and relevant info.
     */
    public function nearby($lat, $lon) {
        if (!$lat) { return self::_error("nearby() requires `\$lat` parameter.\n"); }
        if (!$lon) { return self::_error("nearby() requires `\$lon` parameter.\n"); }

        return self::_get('/users/nearby',
            [
                'client_id' => self::$settings->client_id,
                'client_secret' => self::$settings->client_secret,
                'latitude' => $lat,
                'longitude' => $lon
            ]);
    }

    /**
     * Gets auto-withdrawal status of the account associated
     * with the current OAuth token.
     *
     * @return {Array} Status (with funding id if applicable)
     */
    public function getAutoWithdrawalStatus() {
        return self::_get('/accounts/features/auto_withdrawl',
            [
                'oauth_token' => self::$settings->oauth_token
            ]);
    }

    /**
     * Sets auto-withdrawal status of the account associated
     * with the current OAuth token under the specified
     * funding ID.
     *
     * @param $status {Bool} Auto-withdrawal boolean.
     * @param $fundingId {String} Funding ID of target account.
     *
     * @return {String} Either "Enabled" or "Disabled"
     */
    public function toggleAutoWithdrawalStatus($status, $fundingId) {
        if (!$status) { return self::_error("toggleAutoWithdrawalStatus() requires `\$status` parameter.\n"); }
        if (!$fundingId) { return self::_error("toggleAutoWithdrawalStatus() requires `\$fundingId` parameter.\n"); }

        return self::_post('/accounts/features/auto_withdrawl',
            [
                'oauth_token' => self::$settings->oauth_token,
                'enabled' => $status,
                'fundingId' => $fundingId
            ]);
    }
}