<?php

/**
 *      _               _ _
 *   __| |_      _____ | | | __ _
 *  / _` \ \ /\ / / _ \| | |/ _` |
 * | (_| |\ V  V / (_) | | | (_| |
 *  \__,_| \_/\_/ \___/|_|_|\__,_|

 * An official Guzzle based wrapper for the Dwolla API.

 * This class contains methods for offsite-gateway checkout functionality.
 *
 * resetCart(): Clears out item cart.
 * addToCart(): Adds item to cart.
 * create(): Creates a checkout session.
 * get(): Gets status of existing checkout session.
 * complete(): Completes a checkout session.
 * verify(): Verifies a checkout session.
 */

namespace Dwolla;

include_once('client.php');

class Checkouts extends RestClient {

    /**
     * Placeholder for checkout items.
     *
     * @var $cart
     */
    public $cart = false;

    /**
     * Clears out all shopping cart items.
     */
    public function resetCart() { $this->cart = false; }

    /**
     * Adds an item to the checkout session cart.
     *
     * @param $name {String} Product name.
     * @param $desc {String} Product description.
     * @param $cost {Double} Product cost.
     * @param $quantity {Integer} Product quantity.
     */
    public function addToCart($name, $desc, $cost, $quantity) {
        if (!$name) { return self::_error("addToCart() requires `$name` parameter.\n"); }
        if (!$desc) { return self::_error("addToCart() requires `$desc` parameter.\n"); }
        if (!$cost) { return self::_error("addToCart() requires `$cost` parameter.\n"); }
        if (!$quantity) { return self::_error("addToCart() requires `$quantity` parameter.\n"); }

        if (!is_array($this->cart)) { $this->cart = []; }

        array_push($this->cart,
            [
                'name' => $name,
                'description' => $desc,
                'price' => $cost,
                'quantity' => $quantity
            ]);
    }

    /**
     * Creates an offsite-gateway checkout session. If there is a
     * cart session started, the items used will be the items set in
     * the cart regardless of whether 'orderItems' are passed within
     * 'purchaseOrder'. If items are provided, but no total, the library
     * will calculate a total for you.
     *
     * @param $purchaseOrder {Array} 'purchaseOrder' parameters
     * @param $params {Array} Additional parameters.
     *
     * @return null
     */

    public function create($purchaseOrder, $params = false) {
        if (!$purchaseOrder) { return self::_error("create() requires `$purchaseOrder` parameter.\n"); }
        if (is_array($purchaseOrder)) {
            if (!$purchaseOrder['destinationId']) { return self::_error("`$purchaseOrder` has no `destinationId` key."); }
            if (!$this->cart && !$purchaseOrder['total']) { return self::_error("`$purchaseOrder` has no `total` amount. Create a cart or pass in a total order amount."); }
        }
        else { return self::_error("createCheckout() requires `$purchaseOrder` to be of type array."); }

        $p = [
            'client_id' => self::$settings->client_id,
            'client_secret' => self::$settings->client_secret,
            'purchaseOrder' => $purchaseOrder
        ];

        if (is_array($this->cart)) {
            $p['purchaseOrder']['total'] = 0;
            foreach ($this->cart as $item) {
                $p['purchaseOrder']['total'] += ($item['price'] * $item['quantity']);
            }
            $p['purchaseOrder']['orderItems'] = $this->cart;
        }

        if (!$p['purchaseOrder']['total']) {
            foreach ($p['purchaseOrder']['orderItems'] as $item) {
                $p['purchaseOrder']['total'] += ($item['price'] * $item['quantity']);
            }
        }

        if (isset($p['purchaseOrder']['tax'])) { $p['purchaseOrder']['total'] += $p['purchaseOrder']['tax']; }
        if (isset($p['purchaseOrder']['shipping'])) { $p['purchaseOrder']['total'] += $p['purchaseOrder']['shipping']; }
        if (isset($p['purchaseOrder']['discount'])) {
            if ($p['purchaseOrder']['discount'] > 0) { $p['purchaseOrder']['discount'] = -($p['purchaseOrder']['discount']); }
            $p['purchaseOrder']['total'] += $p['purchaseOrder']['discount'];
        }

        $p['purchaseOrder']['total'] = number_format($p['purchaseOrder']['total'], 2, '.', '');
        if ($params && is_array($params)) { $p = array_merge($p, $params); }

        $id = self::_post('/offsitegateway/checkouts', $p);
        if (is_array($id)) {
            return array_merge($id,
                [ 'URL' => self::_host() . "payment/checkout/" . array_key_exists('CheckoutId', $id)? $id['CheckoutId'] : null]);
        }
        else {
            return self::_error("Unable to create checkout due to API error.");
        }
    }

    /**
     * Retrieves information (status, etc.) from an existing
     * checkout.
     *
     * @param $id {String} Checkout ID.
     *
     * @return mixed|null
     */
    public function get($id) {
        if (!$id) { return self::_error("get() requires `$id` parameter.\n"); }

        return self::_get('/offsitegateway/checkouts/'. $id,
            [
                'client_id' => self::$settings->client_id,
                'client_secret' => self::$settings->client_secret
            ]);
    }

    /**
     * Completes an offsite-gateway "Pay Later" checkout session.
     *
     * @param $id {String} Checkout ID.
     *
     * @return null
     */
    public function complete($id) {
        if (!$id) { return self::_error("complete() requires `$id` parameter.\n"); }

        return self::_post('/offsitegateway/checkouts/' . $id . '/complete',
            [
                'client_id' => self::$settings->client_id,
                'client_secret' => self::$settings->client_secret
            ]);
    }

    /**
     * Verifies offsite-gateway signature hash against
     * server-provided hash.
     *
     * @param $sig {String} Server provided signature.
     * @param $id  {String} Checkout ID.
     * @param $amount {Double} Amount of checkout session.
     *
     * @return {Bool} Check success or failure.
     */
    public function verify($sig, $id, $amount) {
        if (!$id) { return self::_error("verify() requires `$id` parameter.\n"); }
        if (!$sig) { return self::_error("verify() requires `$sig` parameter.\n"); }
        if (!$amount) { return self::_error("verify() requires `$amount` parameter.\n"); }

        // Normalize amount
        $amount = number_format($amount, 2, '.', '');

        // Make signature for matching, return comparison
        $proposed = hash_hmac("sha1", $id . "&" . $amount, self::$settings->client_secret);
        return $sig == $proposed;
    }
}
