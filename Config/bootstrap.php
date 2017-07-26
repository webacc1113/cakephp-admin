<?php
/**
 * This file is loaded automatically by the app/webroot/index.php file after core.php
 *
 * This file should load/create any application wide configuration settings, such as
 * Caching, Logging, loading additional configuration files.
 *
 * You should also use this file to include any files that provide global functions/constants
 * that your application uses.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Config
 * @since         CakePHP(tm) v 0.10.8.2117
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

// Setup a 'default' cache configuration for use in the application.
Cache::config('default', array('engine' => 'File'));

/**
 * The settings below can be used to set additional paths to models, views and controllers.
 *
 * App::build(array(
 *     'Model'                     => array('/path/to/models', '/next/path/to/models'),
 *     'Model/Behavior'            => array('/path/to/behaviors', '/next/path/to/behaviors'),
 *     'Model/Datasource'          => array('/path/to/datasources', '/next/path/to/datasources'),
 *     'Model/Datasource/Database' => array('/path/to/databases', '/next/path/to/database'),
 *     'Model/Datasource/Session'  => array('/path/to/sessions', '/next/path/to/sessions'),
 *     'Controller'                => array('/path/to/controllers', '/next/path/to/controllers'),
 *     'Controller/Component'      => array('/path/to/components', '/next/path/to/components'),
 *     'Controller/Component/Auth' => array('/path/to/auths', '/next/path/to/auths'),
 *     'Controller/Component/Acl'  => array('/path/to/acls', '/next/path/to/acls'),
 *     'View'                      => array('/path/to/views', '/next/path/to/views'),
 *     'View/Helper'               => array('/path/to/helpers', '/next/path/to/helpers'),
 *     'Console'                   => array('/path/to/consoles', '/next/path/to/consoles'),
 *     'Console/Command'           => array('/path/to/commands', '/next/path/to/commands'),
 *     'Console/Command/Task'      => array('/path/to/tasks', '/next/path/to/tasks'),
 *     'Lib'                       => array('/path/to/libs', '/next/path/to/libs'),
 *     'Locale'                    => array('/path/to/locales', '/next/path/to/locales'),
 *     'Vendor'                    => array('/path/to/vendors', '/next/path/to/vendors'),
 *     'Plugin'                    => array('/path/to/plugins', '/next/path/to/plugins'),
 * ));
 *
 */

/**
 * Custom Inflector rules, can be set to correctly pluralize or singularize table, model, controller names or whatever other
 * string is passed to the inflection functions
 *
 * Inflector::rules('singular', array('rules' => array(), 'irregular' => array(), 'uninflected' => array()));
 * Inflector::rules('plural', array('rules' => array(), 'irregular' => array(), 'uninflected' => array()));
 *
 */

/**
 * Plugins need to be loaded manually, you can either load them one by one or all of them in a single call
 * Uncomment one of the lines below, as you need. make sure you read the documentation on CakePlugin to use more
 * advanced ways of loading plugins
 *
 * CakePlugin::loadAll(); // Loads all plugins at once
 * CakePlugin::load('DebugKit'); //Loads a single plugin named DebugKit
 *
 */

/**
 * You can attach event listeners to the request lifecycle as Dispatcher Filter . By Default CakePHP bundles two filters:
 *
 * - AssetDispatcher filter will serve your asset files (css, images, js, etc) from your themes and plugins
 * - CacheDispatcher filter will read the Cache.check configure variable and try to serve cached content generated from controllers
 *
 * Feel free to remove or add filters as you see fit for your application. A few examples:
 *
 * Configure::write('Dispatcher.filters', array(
 *		'MyCacheFilter', //  will use MyCacheFilter class from the Routing/Filter package in your app.
 *		'MyPlugin.MyFilter', // will use MyFilter class from the Routing/Filter package in MyPlugin plugin.
 * 		array('callable' => $aFunction, 'on' => 'before', 'priority' => 9), // A valid PHP callback type to be called on beforeDispatch
 *		array('callable' => $anotherMethod, 'on' => 'after'), // A valid PHP callback type to be called on afterDispatch
 *
 * ));
 */
Configure::write('Dispatcher.filters', array(
	'AssetDispatcher',
	'CacheDispatcher'
));

// this also loads
require_once('site_constants.php');
if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
	$logEngine = 'FileLog';
}
else {
	$logEngine = 'StackifyLog';
}

/**
 * Configures default file logging options
 */
App::uses('CakeLog', 'Log');

CakeLog::config('debug', array(
	'engine' => $logEngine,
	'types' => array('notice', 'info', 'debug'),
	'file' => 'debug',
));

CakeLog::config('pdf_crowd', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('pdf_crowd')
));
CakeLog::config('quickbooks', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('quickbooks')
));
CakeLog::config('mail', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('mail')
));
CakeLog::config('dwolla.withdrawals', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('dwolla.withdrawals')
));
CakeLog::config('dwolla_refresh', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('dwolla_refresh')
));
CakeLog::config('dwolla_refresh_v2', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('dwolla_refresh_v2')
));
CakeLog::config('ssi.users', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('ssi.users')
));
CakeLog::config('ssi.projects', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('ssi.projects')
));
CakeLog::config('ssi.invites', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('ssi.invites')
));
CakeLog::config('query_commands', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('query_commands')
));
CakeLog::config('report_commands', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('report_commands')
));
CakeLog::config('tango.fund', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('tango.fund')
));
CakeLog::config('tango.rewards', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('tango.rewards')
));
CakeLog::config('tango.account', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('tango.account')
));
CakeLog::config('auto.close', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('auto.close')
));
CakeLog::config('referrals.account', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('referrals.account')
));
CakeLog::config('referrals.transaction', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('referrals.transaction')
));
CakeLog::config('fulcrum.auto', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.auto')
));
CakeLog::config('fulcrum.floor', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.floor')
));
CakeLog::config('maintenance.close_projects', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('maintenance.close_projects')
));
CakeLog::config('maintenance.close_inactive', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('maintenance.close_inactive')
));
CakeLog::config('fulcrum.import', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.import')
));
CakeLog::config('fulcrum.import.link', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.import.link')
));
CakeLog::config('fulcrum.qualifications', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.qualifications')
));
CakeLog::config('fulcrum.auto.dryrun', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.auto.dryrun')
));
CakeLog::config('fulcrum.followup_sends', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.followup_sends')
));
CakeLog::config('fulcrum.resend.dryrun', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.resend.dryrun')
));
CakeLog::config('fulcrum.resend', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.resend')
));
CakeLog::config('fulcrum.update', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('fulcrum.update')
));
CakeLog::config('fulcrum.reopen', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('fulcrum.reopen')
));
CakeLog::config('user_balances', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('user_balances')
));
CakeLog::config('reconciliation', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('reconciliation')
));
CakeLog::config('mbd.process', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('mbd.process')
));
CakeLog::config('mbd.invite', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('mbd.process')
));
CakeLog::config('mbd.output', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('mbd.output')
));
CakeLog::config('mbd.process.local.list', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('mbd.process.local.list')
));
CakeLog::config('mbd.process.local', array(
    'engine' => 'FileLog',
    'types' => array('info'),
    'scopes' => array('mbd.process.local')
));
CakeLog::config('payouts', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('payouts')
));
CakeLog::config('payouts.returned', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('payouts.returned')
));
CakeLog::config('payouts.paypal', array(
    'engine' => $logEngine,
    'types' => array('info'),
    'scopes' => array('payouts.paypal')
));
CakeLog::config('payouts.dwolla', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('payouts.dwolla')
));
CakeLog::config('payouts.mvpay', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('payouts.mvpay')
));
CakeLog::config('payouts.tango', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('payouts.tango')
));
CakeLog::config('error', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'file' => 'error',
));
CakeLog::config('cint.import', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('cint.import')
));
CakeLog::config('toluna.users', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('toluna.users')
));
CakeLog::config('toluna.invites', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('toluna.invites')
));
CakeLog::config('aws.sqs', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('aws.sqs')
));
CakeLog::config('aws.sqs', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('aws.sqs')
));
CakeLog::config('maintenance.mailgun.list', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('maintenance.mailgun.list')
));
CakeLog::config('local.lag', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('local.lag')
));
CakeLog::config('notifications.process', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('notifications.process')
));
CakeLog::config('notifications.email', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('notifications.email')
));

// For now Filelog for Spectrum
$logEngineForSpectrum = 'FileLog';
CakeLog::config('spectrum.links', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.links')
));
CakeLog::config('spectrum.process', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.process')
));
CakeLog::config('spectrum.create', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.create')
));
CakeLog::config('spectrum.update', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.update')
));
CakeLog::config('spectrum.sqs', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.sqs')
));
CakeLog::config('spectrum.worker', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.worker')
));
CakeLog::config('spectrum.qualifications', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.qualifications')
));
CakeLog::config('spectrum.auto', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.auto')
));
CakeLog::config('spectrum.query_run', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.query_run')
));
CakeLog::config('spectrum.qe2', array(
	'engine' => $logEngineForSpectrum,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('spectrum.qe2')
));

// temporary for lucid
$logEngine = 'FileLog';
CakeLog::config('lucid.worker', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.worker')
));
CakeLog::config('lucid.epc.statistics', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.epc.statistics')
));
CakeLog::config('lucid.sqs', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.sqs')
));
CakeLog::config('lucid.links', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.links')
));
CakeLog::config('lucid.process', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.process')
));
CakeLog::config('lucid.create', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.create')
));
CakeLog::config('lucid.invite', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.invite')
));
CakeLog::config('lucid.sends', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.sends')
));
CakeLog::config('lucid.qualifications', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.qualifications')
));
CakeLog::config('lucid.update', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.update')
));

CakeLog::config('tango.resend', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('tango.resend')
));

CakeLog::config('lucid.log_completes', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.log_completes')
));

CakeLog::config('lucid.missing_queue_ids', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.missing_queue_ids')
));

CakeLog::config('rfg.process', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('rfg.process')
));
CakeLog::config('lucid.qe2', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.qe2')
));
CakeLog::config('lucid.qe2.api', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.qe2.api')
));
CakeLog::config('qe2.test', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('qe2.test')
));
CakeLog::config('qe2.test.json', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('qe2.test.json')
));
CakeLog::config('qe2.test.query', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('qe2.test.query')
));
CakeLog::config('core.qe2.test', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('core.qe2.test')
));
CakeLog::config('lucid.recreate.links', array(
	'engine' => 'FileLog',
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('ucid.recreate.links')
));
CakeLog::config('lucid.update.quota', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('lucid.update.quota')
));
CakeLog::config('precision.create', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('precision.create')
));

CakeLog::config('precision.update', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('precision.update')
));
CakeLog::config('users.rebuild.balances', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('users.rebuild.balances')
));
CakeLog::config('sms', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('sms')
));
CakeLog::config('proxy', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('proxy')
));
CakeLog::config('resurrect_users', array(
	'engine' => 'FileLog',
	'types' => array('info'),
	'scopes' => array('resurrect_users')
));
CakeLog::config('keen_import', array(
	'engine' => 'FileLog',
	'types' => array('info'),
	'scopes' => array('keen_import')
));

CakeLog::config('points2shop.worker', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.worker')
));
CakeLog::config('points2shop.process', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.process')
));
CakeLog::config('points2shop.sqs', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.sqs')
));
CakeLog::config('points2shop.create', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.create')
));
CakeLog::config('points2shop.qualifications', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.qualifications')
));
CakeLog::config('points2shop.qe2', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.qe2')
));
CakeLog::config('points2shop.qe2.api', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('points2shop.qe2.api')
));
CakeLog::config('points2shop.auto', array(
	'engine' => $logEngine,
	'types' => array('info'),
	'scopes' => array('points2shop.auto')
));
CakeLog::config('points2shop.update', array(
	'engine' => $logEngine,
	'types' => array('info'),
	'scopes' => array('points2shop.update')
));
CakeLog::config('points2shop.loi', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert'),
	'scopes' => array('points2shop.loi')
));
CakeLog::config('qe.map', array(
	'engine' => $logEngine,
	'types' => array('info'),
	'scopes' => array('qe.map')
));
CakeLog::config('qe.map', array(
	'engine' => $logEngine,
	'types' => array('info'),
	'scopes' => array('refresh.qual')
));
CakeLog::config('precision.email', array(
	'engine' => 'FileLog',
	'types' => array('info'),
	'scopes' => array('precision.email')
));
CakeLog::config('precision.invites', array(
	'engine' => $logEngine,
	'types' => array('warning', 'error', 'critical', 'alert', 'emergency'),
	'scopes' => array('precision.invites')
));
CakeLog::config('export.questions.qe2', array(
	'engine' => 'FileLog',
	'types' => array('info'),
	'scopes' => array('export.questions.qe2')
));
CakeLog::config('usurv.projects', array(
	'engine' => 'FileLog',
	'types' => array('info'),
	'scopes' => array('usurv.projects')
));
spl_autoload_register(function($class) {
    foreach(App::path('Vendor') as $base) {
        $path = $base . str_replace('\\', DS, $class) . '.php';
        if (file_exists($path)) {
            return include $path;
        }
    }
});
