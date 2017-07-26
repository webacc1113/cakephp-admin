dwolla-php
=========

[![Build Status](https://travis-ci.org/Dwolla/dwolla-php.svg?branch=master)](https://travis-ci.org/Dwolla/dwolla-php)

The new and improved Dwolla library based off of the Guzzle REST client. `dwolla-php` includes support for all API endpoints, and is the new library officially supported by Dwolla. 

## Version

2.0.9

## Installation

`dwolla-php` is available on [Packagist](https://packagist.org/packages/dwolla/dwolla-php), and therefore can be installed automagically via [Composer](http://getcomposer.org).

**The PHP JSON and CURL extensions are required for `dwolla-php` to operate.** 

*To install without adding to `composer.json`:*

```
composer require dwolla/dwolla-php
```

*To add to `composer.json` and make this a permanent dependency of your package:*
```
composer require "dwolla/dwolla-php=2.*"
composer update && composer install
```

## Quickstart

`dwolla-php` makes it easy for developers to hit the ground running with our API. Before attempting the following, you should ideally create [an application key and secret](https://www.dwolla.com/applications).

* Set any variables in `_settings.php` or the `Settings` class. All fields are public.
* Instantiate `dwolla-php` with the class that contains the endpoints you require.
* Use at will!

### Use variables in `_settings.php`

```php
require '../lib/account.php';
$Account = new Dwolla\Account();

/**
 * Example 1: Get basic information for
 * a Dwolla user using their Dwolla ID.
 */
print_r($Account->basic('812-121-7199'));
```

### Set your own

**See `accountInst.php` for an example of the following.**

```php
require '../lib/account.php';
$Account = new Dwolla\Account();

$Account->settings->client_id = "MY KEY";
$Account->settings->client_secret = "MY SECRET";
$Account->settings->sandbox = true;

/**
 * Example 1: Get basic information for
 * a Dwolla user using their Dwolla ID.
 */
print_r($Account->basic('812-121-7199'));
```

---

There are 8 quickstart files which will walk you through working with `dwolla-php`'s classes/endpoint groupings. 

* `account.php`: Retrieve account information, such as balance.
* `checkouts.php`: Offsite-gateway endpoints, server-to-server checkout example.
* `contacts.php`: Retrieve/sort through user contacts.
* `fundingSources.php`: Modify and get information with regards to funding sources.
* `masspay.php`: Create and retrieve jobs/data regarding MassPay jobs. 
* `oauth.php`: Examples on retrieving OAuth access/refresh token pairs.
* `requests.php`: Create and retrieve money requests/information regarding money requests.
* `transactions.php`: Send money, get transaction info by ID, etc.

## Structure

`dwolla-php` is a conglomerate of multiple classes; each file in the `lib/` directory contains a class which contains all the endpoints for that certain category ([similar to Dwolla's developer documentation](https://developers.dwolla.com/dev/docs)). 

### Endpoint Classes / Methods

Each endpoint class extends `RestClient` located in `client.php` (e.g. `RestClient::Account()`).

* `Account()`:
 * `basic()`: Retrieves basic account information
 * `full()`: Retrieve full account information
 * `balance()`: Get user balance
 * `nearby()`: Get nearby users
 * `getAutoWithdrawal()`: Get auto-withdrawal status
 * `toggleAutoWithdrawal()`: Toggle auto-withdrawal
* `Checkouts()`:
 * `resetCart()`: Clears out item cart.
 * `addToCart()`: Adds item to cart.
 * `create()`: Creates a checkout session.
 * `get()`: Gets status of existing checkout session.
 * `complete()`: Completes a checkout session.
 * `verify()`: Verifies a checkout session.
* `Contacts()`:
 * `get()`: Retrieve a user's contacts.
 * `nearby()`: Get spots near a location.
* `FundingSources()`:
 * `info()`: Retrieve information regarding a funding source via ID.
 * `get()`: List all funding sources.
 * `add()`: Add a funding source.
 * `verify()`: Verify a funding source.
 * `withdraw()`: Withdraw from Dwolla into funding source.
 * `deposit()`: Deposit to Dwolla from funding source.
* `MassPay()`:
 * `create()`: Creates a MassPay job.
 * `getJob()`: Gets a MassPay job.
 * `getJobItems()`: Gets all items for a specific job.
 * `getItem()`: Gets an item from a specific job.
 * `listJobs()`: Lists all MassPay jobs.
* `OAuth()`:
 * `genAuthUrl()`: Generates OAuth permission link URL
 * `get()`: Retrieves OAuth + Refresh token pair from Dwolla servers.
 * `refresh()`: Retrieves OAuth + Refresh pair with refresh token.
* `Requests()`:
 * `create()`: Request money from user.
 * `get()`: Lists all pending money requests.
 * `info()`: Retrieves info for a pending money request.
 * `cancel()`: Cancels a money request.
 * `fulfill()`: Fulfills a money request.
* `Transactions()`:
 * `send()`: Sends money
 * `refund()`: Refunds money
 * `get()`: Lists transactions for user
 * `info()`: Get information for transaction by ID.
 * `stats()`: Get transaction statistics for current user.

### Internal Use

`client.php/RestClient()` is the base class for all of the aforementioned classes, `_settings.php/Settings()` does not inherit from anything and only contains configuration parameters. 

## Unit Testing

`dwolla-php` uses [PHPUnit](https://phpunit.de/) for unit testing. These tests do not test integration and will occassionally show console API errors due to 'dummy' data being used. Integration testing is planned sometime in the future. 

To run the tests, install `require\dev` from `composer.json` and run:

```
cd tests
../vendor/bin/phpunit
```

## Changelog

2.0.9
* Fixed `number_format` bug to allow amounts greater than 999.99. 
* Fixed use of `$this` accessor vs. `self::` in checkouts `verify()` module. Thanks, @tylermenezes!

2.0.8
* Changed `production_host` variable to `http://www.dwolla.com` to mitigate HTTP 500 on some requests (namely OAuth).

2.0.7
* OAuth->get() bug has been fixed.
* _dwollaparse() now returns error messages from API if any exist.

2.0.6
* Log to file feature added (thank you @redzarf for your pull request).
* Improved error handling, `getBody()` called on non-object error has been resolved for failing requests.
* Fix for ISE with OAuth token retrieval.

2.0.5
* Fixed "stuck hostname" bug.
* Fixed improper class resolution (thanks @redzarf!).
* Added PHP magic methods `__get()` and `__set()` for compliance with PHP strict (`E_STRICT`).
* All tests are now set to also test against *all* PHP errors with `error_reporting(-1)`.

2.0.4
* Fixed `Checkouts->Create`.  It now requires `total` to be included in `purchaseOrder` instead of `items` (`orderItems`).
* Revise `composer.json` keywords
* Fix README library installation command.  Composer command is `require` instead of `install`.

2.0.3
* Changed token retrieval methods to POST to avoid querystring errors with GET and invalid access tokens/code/etc.

2.0.2
* Fixed fundsSource parameter in refund function, thanks @echodreamz.

2.0.1
* Fixed settings class inheritance issue,  made unit tests use autoload file. 

2.0.0
* Initial release.

## Credits

This wrapper is based on [Guzzle](https://github.com/guzzle/guzzle) for REST capability and uses [PHPUnit](https://phpunit.de/) for unit testing and [Travis](https://travis-ci.org/) for automagical build verification. 

Version `2.x` initially written by [David Stancu](http://davidstancu.me) (david@dwolla.com).

Versions `1.x` written by:
* Michael Schonfeld
* Jeremy Kendall <http://about.me/jeremykendall>

## License

Copyright (c) 2014 Dwolla Inc, David Stancu

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
