<?php
/**
 *  Copyright 2015 SmartBear Software
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

/**
 * 
 *
 * NOTE: This class is auto generated by the swagger code generator program. Do not edit the class manually.
 *
 */

namespace DwollaSwagger\models;

use \ArrayAccess;

class Webhook implements ArrayAccess {
  static $swaggerTypes = array(
      '_links' => 'map[string,HalLink]',
      '_embedded' => 'object',
      'id' => 'string',
      'topic' => 'string',
      'account_id' => 'string',
      'event_id' => 'string',
      'subscription_id' => 'string',
      'attempts' => 'array[WebhookAttempt]'
  );

  static $attributeMap = array(
      '_links' => '_links',
      '_embedded' => '_embedded',
      'id' => 'id',
      'topic' => 'topic',
      'account_id' => 'accountId',
      'event_id' => 'eventId',
      'subscription_id' => 'subscriptionId',
      'attempts' => 'attempts'
  );

  
  public $_links; /* map[string,HalLink] */
  public $_embedded; /* object */
  public $id; /* string */
  public $topic; /* string */
  public $account_id; /* string */
  public $event_id; /* string */
  public $subscription_id; /* string */
  public $attempts; /* array[WebhookAttempt] */

  public function __construct(array $data = null) {
    $this->_links = $data["_links"];
    $this->_embedded = $data["_embedded"];
    $this->id = $data["id"];
    $this->topic = $data["topic"];
    $this->account_id = $data["account_id"];
    $this->event_id = $data["event_id"];
    $this->subscription_id = $data["subscription_id"];
    $this->attempts = $data["attempts"];
  }

  public function offsetExists($offset) {
    return isset($this->$offset);
  }

  public function offsetGet($offset) {
    return $this->$offset;
  }

  public function offsetSet($offset, $value) {
    $this->$offset = $value;
  }

  public function offsetUnset($offset) {
    unset($this->$offset);
  }
}
