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

class FundingSource implements ArrayAccess {
  static $swaggerTypes = array(
      '_links' => 'map[string,HalLink]',
      '_embedded' => 'object',
      'id' => 'string',
      'status' => 'string',
      'type' => 'string',
      'name' => 'string',
      'created' => 'DateTime',
      'balance' => 'object',
      'removed' => 'boolean',
      'channels' => 'array[string]',
      'bank_name' => 'string'
  );

  static $attributeMap = array(
      '_links' => '_links',
      '_embedded' => '_embedded',
      'id' => 'id',
      'status' => 'status',
      'type' => 'type',
      'name' => 'name',
      'created' => 'created',
      'balance' => 'balance',
      'removed' => 'removed',
      'channels' => 'channels',
      'bank_name' => 'bankName'
  );

  
  public $_links; /* map[string,HalLink] */
  public $_embedded; /* object */
  public $id; /* string */
  public $status; /* string */
  public $type; /* string */
  public $name; /* string */
  public $created; /* DateTime */
  public $balance; /* object */
  public $removed; /* boolean */
  public $channels; /* array[string] */
  public $bank_name; /* string */

  public function __construct(array $data = null) {
    $this->_links = $data["_links"];
    $this->_embedded = $data["_embedded"];
    $this->id = $data["id"];
    $this->status = $data["status"];
    $this->type = $data["type"];
    $this->name = $data["name"];
    $this->created = $data["created"];
    $this->balance = $data["balance"];
    $this->removed = $data["removed"];
    $this->channels = $data["channels"];
    $this->bank_name = $data["bank_name"];
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
