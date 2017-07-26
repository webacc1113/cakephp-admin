<?php

/**
 *      _               _ _
 *   __| |_      _____ | | | __ _
 *  / _` \ \ /\ / / _ \| | |/ _` |
 * | (_| |\ V  V / (_) | | | (_| |
 *  \__,_| \_/\_/ \___/|_|_|\__,_|

 * An official Guzzle based wrapper for the Dwolla API.

 * This class contains methods for MassPay functionality.
 *
 * create(): Creates a MassPay job.
 * getJob(): Gets a MassPay job.
 * getJobItems(): Gets all items for a specific job.
 * getItem(): Gets an item from a specific job.
 * listJobs(): Lists all MassPay jobs.
 */

namespace Dwolla;

include_once('client.php');

class MassPay extends RestClient {

    /**
     * Creates a MassPay job. Must pass in an array of items.
     *
     * @param $fundsSource {String} Funding Source for job.
     * @param $items {Array} Item array.
     * @param $params {Array} Additional parameters.
     *
     * @return null
     */
    public function create($fundsSource, $items, $params = false) {
        if (!$fundsSource) { return self::_error("create() requires `$fundsSource` parameter.\n"); }
        if (!$items) { return self::_error("create() requires `$items` parameter.\n"); }

        $p = [
            'oauth_token' => self::$settings->oauth_token,
            'pin' => self::$settings->pin,
            'fundsSource' => $fundsSource,
            'items' => $items
        ];

        if ($params && is_array($params)) { $p = array_merge($p, $params); }

        return self::_post('/masspay/', $p);
    }

    /**
     * Checks the status of an existing MassPay job and
     * returns additional information.
     *
     * @param $id {String} MassPay job ID.
     *
     * @return null
     */
    public function getJob($id) {
        if (!$id) { return self::_error("getJob() requires `$id` parameter.\n"); }

        return self::_get('/masspay/' . $id,
            [
                'oauth_token' => self::$settings->oauth_token
            ]);
    }

    /**
     * Gets all items from a created MassPay job.
     *
     * @param $id {String} MassPay job ID.
     * @param $params {Array} Additional parameters.
     *
     * @return null
     */
    public function getJobItems($id, $params = false) {
        if (!$id) { return self::_error("getJobItems() requires `$id` parameter.\n"); }

        $p = [
            'oauth_token' => self::$settings->oauth_token
        ];

        if ($params && is_array($params)) { $p = array_merge($p, $params); }

        return self::_get('/masspay/' . $id . '/items', $p);
    }

    /**
     * Gets an item from a created MassPay job.
     *
     * @param $job_id {String} MassPay job ID.
     * @param $item_id {String} Item ID.
     *
     * @return null
     */
    public function getItem($job_id, $item_id) {
        if (!$job_id) { return self::_error("getItem() requires `$job_id` parameter.\n"); }
        if (!$item_id) { return self::_error("getItem() requires `$item_id` parameter.\n"); }

        return self::_get('/masspay/' . $job_id . '/items/' . $item_id,
            [
                'oauth_token' => self::$settings->oauth_token
            ]);
    }

    /**
     * Lists all MassPay jobs for the user under
     * the current OAuth token.
     *
     * @return {Array} MassPay jobs.
     */
    public function listJobs() {
        return self::_get('/masspay/',
            [
                'oauth_token' => self::$settings->oauth_token
            ]);
    }
}