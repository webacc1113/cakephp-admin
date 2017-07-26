<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
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
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('Controller', 'Controller');
App::uses('CakeEmail', 'Network/Email');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
CakePlugin::load('Mailgun');
App::uses('HttpSocket', 'Network/Http');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {
	
	public $uses = array('User', 'Event', 'Admin', 'Setting', 'ProjectLog', 'Group');
	public $helpers = array('App');
	
	public $components = array(
		'Admins',
		'Session',
		'Cookie',
		'Auth'=> array(
        	'authenticate' => array(
            	'Form' => array(
                	'fields' => array(
                		'admin_user' => 'admin_user',
                		'admin_pass' => 'admin_pass'
                	),
                	'userModel' => 'Admin',
                	'scope' => array('Admin.active' => 1),
                	'passwordHasher' => 'Blowfish'
            	)
        	),
        	'authorize' => array('Controller'),
            'loginAction' => array('controller' => 'admins', 'action' => 'login'),
			'loginRedirect' => array('controller' => 'statistics', 'action' => 'index'),
			'logoutRedirect' => array('controller' => 'admins', 'action' => 'login'),
    	)
	);
	
	public function beforeFilter() {
		$user = $this->Auth->user();
		$user = $this->Admins->admin($user['Admin']['id']);
		if ($user) {
			$this->Auth->allow('logout');
			$this->Auth->unauthorizedRedirect = array('controller' => 'statistics', 'action' => 'index');
		}
		
		$settings = $this->Setting->find('all', array(
			'conditions' => array(
				'Setting.name' => array('hostname.redirect', 'hostname.www', 'hostname.web', 'hostname.api', 'hostname.survey', 'site.email_sender', 'site.reply_to_email'),
				'Setting.deleted' => false
			)
		));		
		
		if ($settings) {			
			foreach ($settings as $setting) {
				if (!defined('EMAIL_SENDER') && $setting['Setting']['name'] == 'site.email_sender') {
					define('EMAIL_SENDER', $setting['Setting']['value']);
				}
				elseif (!defined('REPLYTO_EMAIL') && $setting['Setting']['name'] == 'site.reply_to_email') {
					define('REPLYTO_EMAIL', $setting['Setting']['value']);
				}
				elseif (!defined('HOSTNAME_REDIRECT') && $setting['Setting']['name'] == 'hostname.redirect') {
					define('HOSTNAME_REDIRECT', $setting['Setting']['value']);
				}
				elseif (!defined('HOSTNAME_WWW') && $setting['Setting']['name'] == 'hostname.www') {
					define('HOSTNAME_WWW', $setting['Setting']['value']);
				}
				elseif (!defined('HOSTNAME_WEB') && $setting['Setting']['name'] == 'hostname.web') {
					define('HOSTNAME_WEB', $setting['Setting']['value']);
				}
				elseif (!defined('HOSTNAME_API') && $setting['Setting']['name'] == 'hostname.api') {
					define('HOSTNAME_API', $setting['Setting']['value']);
				}
				elseif (!defined('HOSTNAME_VIPER') && $setting['Setting']['name'] == 'hostname.survey') {
					define('HOSTNAME_VIPER', $setting['Setting']['value']);
				}
			}
		}
		$admin_menu = $this->build_menu($user);
		$this->set('admin_menu', $admin_menu); 
		$this->set('current_user', $user); 
		$this->current_user = $user;
		$this->set('controller', $this->params['controller']); 
		$this->set('action', $this->params['action']); 
		if (!empty($user['Admin']['timezone'])) {
			$this->set('timezone', $user['Admin']['timezone']);
		}
		else {
			$this->set('timezone', 'America/Los_Angeles');
		}
	}
	
	function isAuthorized($user) {
		if ($this->current_user['AdminRole']['admin'] == true) {
			return true;
		}
		
		// default unauthorized pages
		$allowed = array(
			'projects.*',
			'surveys.*',
			'queries.*',
			'statistics.*',
			'admins.preferences',
			'users.ajax_pooled_points'
		);
		
		// external pms are not authorized for these default tabs (but only internal pms are authorized)
		if (!in_array('external_pms', $this->current_user['permission_group_keys'])) {
			$allowed = array_merge($allowed, array(
				'clients.*',
				'partners.*',
				'invoices.*',
			));
		}
		
		if ($this->current_user['AdminRole']['projects'] == true) {
			$allowed = array_merge($allowed, array(
				'reports.ajax_check_project_id',
				'reports.ajax_partners',
				'reports.generate',
				'reports.raw',
				'reports.extended',
				'reports.index',
				'reports.download',
				'reports.queries',
				'reports.security',
				'reports.socialglimpz',
				'reports.status',
				'router_logs.index',
				'qualifications.*'
			));
		}
		
		if ($this->current_user['AdminRole']['users'] == true) {
			$allowed = array_merge($allowed, array(
				'users.*', 
				'addresses.*', 
				'reports.user_sources', 
				'reports.rejected_transactions',
				'history_requests.*',
				'panelist_histories.*',
				'partner_logs.*',
				'notification_schedules.*'
			));
		}
		
		if ($this->current_user['AdminRole']['reports'] == true) {
			$allowed = array_merge($allowed, array(
				'reports.*',
				'polls.*',
				'router_logs.*', 
				'project_invites.*', 
				'toluna_logs.*', 
				'precision_logs.*', 
				'cint_logs.*', 
				'user_router_logs.*', 
				'analytics.*',
				'offers.revenues',
				'daily_analysis.*',
				'daily_analysis_properties.*',
				'aytm_logs.*',
				'bad_uid_logs.*',
				'balance_mismatches.*'
			));
		}
		
		if ($this->current_user['AdminRole']['transactions'] == true) {
			$allowed = array_merge($allowed, array(
				'transactions.*', 
				'tangocard_orders.*',
				'tangocards.*',
				'codes.*',
				'offers.groupon',
				'reconciliations.*',
				'questions.*'
			));
		}
		
		if ($this->current_user['AdminRole']['campaigns'] == true) {
			$allowed = array_merge($allowed, array(
				'acquisition_partners.*',
				'sources.*',
				'lander_urls.*',
				'source_mappings.*',
				'acquisition_alerts.*',
				'advertising_spends.*'
			));
		}
		
		if ($this->current_user['AdminRole']['guest'] == true) {
			$allowed = array(
				'statistics.index',
				'admins.preferences',
			);
			$urls = json_decode($this->current_user['Admin']['limit_access'], true);
			if (!empty($urls)) {
				$allowed_controllers = array();
				foreach ($urls as $url) {
					if (strpos($url, "/") !== false) {
						$allowed[] = str_replace('/', '.', $url);
						$controller = explode('/', $url); 
						$allowed_controllers[] = $controller[0]; 						
					}
					else {
						$allowed_controllers[] = $url; 
					}
				}
			}			
			if (substr($this->params['action'], 0, 5) == 'ajax_' && in_array($this->params['controller'], $allowed_controllers)) {
				return true;
			}
		}
		
		if (in_array($this->params['controller'].'.*', $allowed)) {
			return true;
		}

		if (in_array($this->params['controller'].'.'.$this->params['action'], $allowed)) {
			return true;
		}
				
		return false;
	}
	
	function build_menu($user) {
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$lucid_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$cint_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'cint'
			)
		));
		$toluna_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		$precision_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'precision'
			)
		));
		$points2shop_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'points2shop'
			)
		));
		$menu = array(
			'projects' => array(
				'name' => 'Projects', 
				'url' => '#',
				'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns'),
				'children' => array(
					array(
						'name' => 'My Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $mintvine_group['Group']['id'], 'admin_id' => $user['Admin']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns')
					),
					array(
						'name' => 'Ad hoc Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $mintvine_group['Group']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns'),
						'divider' => true
					),
					array(
						'name' => 'Lucid Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $lucid_group['Group']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns')
					),
					array(
						'name' => 'Cint Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $cint_group['Group']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns')
					),
					array(
						'name' => 'Toluna Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $toluna_group['Group']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns')
					),
					array(
						'name' => 'Precision Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $precision_group['Group']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns')
					),
					array(
						'name' => 'Points2Shop Projects', 
						'url' => array(
							'controller' => 'projects', 
							'action' => 'index',
							'?' => array('group_id' => $points2shop_group['Group']['id']) 
						),
						'allowed_roles' => array('admin', 'users', 'projects', 'reports', 'transactions', 'campaigns')
					),
					array(
						'name' => 'Reports', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports', 'projects'),
						'add' => array(
							'url' => array(
								'controller' => 'reports', 
								'action' => 'generate'
							)
						)
					),
					array(
						'name' => 'API Logs', 
						'url' => array(
							'controller' => 'cint_logs', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports')
					)
				)
			),
			'reports' => array(
				'name' => 'Reports', 
				'url' => '#',
				'allowed_roles' => array('reports'),
				'children' => array(
					array(
						'name' => 'Performance By Group', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'statistics'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Projects Perf by Day', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'export_statistics_by_day'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Project Invites', 
						'url' => array(
							'controller' => 'project_invites', 
							'action' => 'report'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Total Inventory Report', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'inventory'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Daily Analysis', 
						'url' => array(
							'controller' => 'daily_analysis', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Long NQ/OQs', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'long_nq_oq'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Complete Outliers', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'complete_outliers'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Panelist vs Survey Terms', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'panelist_vs_survey_visits'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Project Status Exports', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'status'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Survey Router Report', 
						'url' => array(
							'controller' => 'router_logs', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Project Terms Report', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'terming_actions'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Client Analysis Report', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'client_analysis'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Project Exports', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'export_projects'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Router Engagement Report', 
						'url' => array(
							'controller' => 'user_router_logs', 
							'action' => 'engagement'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Bad Uid Logs', 
						'url' => array(
							'controller' => 'bad_uid_logs', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Lucid EPC Statistics', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'lucid_epc_statistics'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Lucid codes', 
						'url' => array(
							'controller' => 'lucid_codes', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'User Notification Report', 
						'url' => array(
							'controller' => 'user_notification_reports', 
							'action' => 'index'
						),
						'allowed_roles' => array('reports')
					)
				)	
			),
			'transactions' => array(
				'name' => 'Transactions', 
				'url' => '#',
				'allowed_roles' => array('transactions'),
				'children' => array(
					array(
						'name' => 'Tango Cards Sent', 
						'url' => array(
							'controller' => 'tangocard_orders', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Manual Send Tangocard', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'send_tangocard'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Pending Withdrawals', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'index',
							'?' => array('type' => 4, 'paid' => 0)
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Withdrawal Results', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'withdrawals'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Withdrawals Requested', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'withdrawals_requested'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Withdrawals Report', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'withdrawals_report'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Gift Points', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'add'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Mass Incentives', 
						'url' => array(
							'controller' => 'transactions', 
							'action' => 'mass_add'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Promo Codes', 
						'url' => array(
							'controller' => 'codes', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions'),
						'add' => array(
							'url' => array(
								'controller' => 'codes', 
								'action' => 'add', 
							)
						)
					),
					array(
						'name' => 'Survey Issues', 
						'url' => array(
							'controller' => 'history_requests', 
							'action' => 'index',
							'?' => array('status' => 0)
						),
						'allowed_roles' => array('transactions'),
						'divider' => true
					),
					array(
						'name' => 'Survey Issues Analytics', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'history_request_analytics'
						),
						'allowed_roles' => array('transactions')
					)
				)	
			),
			'users' => array(
				'name' => 'Users', 
				'url' => '#',
				'allowed_roles' => array('users'),
				'icon_class' => 'icon-user',
				'children' => array(
					array(
						'name' => 'All Users', 
						'url' => array(
							'controller' => 'users', 
							'action' => 'index'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'Unique Users', 
						'url' => array(
							'controller' => 'users', 
							'action' => 'unique_users'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'Rejected Transactions', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'rejected_transactions'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'Hellban Stream', 
						'url' => array(
							'controller' => 'users', 
							'action' => 'hellbans'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'Mass Hellban', 
						'url' => array(
							'controller' => 'users', 
							'action' => 'mass_hellban'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'Export Panelists', 
						'url' => array(
							'controller' => 'users', 
							'action' => 'export'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'User Router Logs', 
						'url' => array(
							'controller' => 'user_router_logs', 
							'action' => 'index'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'User Export Statistics', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'user_export_statistics'
						),
						'allowed_roles' => array('users', 'reports')
					),
					array(
						'name' => 'Notification Templates', 
						'url' => array(
							'controller' => 'notification_schedules', 
							'action' => 'index'
						),
						'allowed_roles' => array('users'),
						'divider' => true
					),
					array(
						'name' => 'Notification Profiles', 
						'url' => array(
							'controller' => 'notification_schedules', 
							'action' => 'user'
						),
						'allowed_roles' => array('users')
					),
					array(
						'name' => 'Segment Debug', 
						'url' => array(
							'controller' => 'user_analytics', 
							'action' => 'index'
						),
						'allowed_roles' => array('users', 'reports')
					)
				)	
			),
			'advertising' => array(
				'name' => 'Advertising', 
				'url' => '#',
				'allowed_roles' => array('campaigns'),
				'children' => array(
					array(
						'name' => 'Acquisition Partners', 
						'url' => array(
							'controller' => 'acquisition_partners', 
							'action' => 'index'
						),
						'allowed_roles' => array('campaigns'),
						'add' => array(
							'url' => array(
								'controller' => 'acquisition_partners', 
								'action' => 'add', 
							)
						)
					),
					array(
						'name' => 'Advertising Spend', 
						'url' => array(
							'controller' => 'advertising_spends', 
							'action' => 'index'
						),
						'allowed_roles' => array('campaigns')
					),
					array(
						'name' => 'Landing Pages', 
						'url' => array(
							'controller' => 'lander_urls', 
							'action' => 'index'
						),
						'allowed_roles' => array('campaigns'),
						'add' => array(
							'url' => array(
								'controller' => 'lander_urls', 
								'action' => 'add', 
							)
						)
					),
					array(
						'name' => 'Partner Data', 
						'url' => array(
							'controller' => 'reports', 
							'action' => 'user_sources'
						),
						'allowed_roles' => array('campaigns')
					),
					array(
						'name' => 'Partner Lead Alerts', 
						'url' => array(
							'controller' => 'acquisition_alerts', 
							'action' => 'index'
						),
						'allowed_roles' => array('campaigns'),
						'add' => array(
							'url' => array(
								'controller' => 'acquisition_alerts', 
								'action' => 'add', 
							)
						)
					),
					array(
						'name' => 'UTM Source Mapping', 
						'url' => array(
							'controller' => 'source_mappings', 
							'action' => 'index'
						),
						'allowed_roles' => array('campaigns'),
						'add' => array(
							'url' => array(
								'controller' => 'source_mappings', 
								'action' => 'add', 
							)
						)
					)
				)	
			),
			'offers' => array(
				'name' => 'Offers', 
				'url' => '#',
				'allowed_roles' => array('transactions', 'reports'),
				'children' => array(
					array(
						'name' => 'Revenues', 
						'url' => array(
							'controller' => 'offers', 
							'action' => 'revenues'
						),
						'allowed_roles' => array('reports')
					),
					array(
						'name' => 'Groupon', 
						'url' => array(
							'controller' => 'offers', 
							'action' => 'groupon'
						),
						'allowed_roles' => array('transactions'),
					)
				)	
			),
			'reconciliations' => array(
				'name' => 'Reconciliations', 
				'url' => '#',
				'allowed_roles' => array('transactions'),
				'children' => array(
					array(
						'name' => 'All reconciliations', 
						'url' => array(
							'controller' => 'reconciliations', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Missing completes', 
						'url' => array(
							'controller' => 'reconciliations', 
							'action' => 'missing_completes'
						),
						'allowed_roles' => array('transactions')
					)
				)	
			),
			'polls' => array(
				'name' => 'Polls', 
				'url' => array(
					'controller' => 'polls', 
					'action' => 'index'
				),
				'allowed_roles' => array('reports')
			),
			'admin' => array(
				'name' => 'Admin', 
				'url' => '#',
				'allowed_roles' => array('transactions', 'projects'),
				'children' => array(
					array(
						'name' => 'Clients', 
						'url' => array(
							'controller' => 'clients', 
							'action' => 'index'
						),
						'allowed_roles' => array('projects')
					),
					array(
						'name' => 'Groups', 
						'url' => array(
							'controller' => 'groups', 
							'action' => 'index'
						),
						'allowed_roles' => array('admin')
					),
					array(
						'name' => 'Filter Panelists', 
						'url' => array(
							'controller' => 'filtered_panelists', 
							'action' => 'index'
						),
						'allowed_roles' => array('admin')
					),
					array(
						'name' => 'Partners', 
						'url' => array(
							'controller' => 'partners', 
							'action' => 'index'
						),
						'allowed_roles' => array('projects')
					),
					array(
						'name' => 'Invoices', 
						'url' => array(
							'controller' => 'invoices', 
							'action' => 'index'
						),
						'allowed_roles' => array('projects')
					),
					array(
						'name' => 'Invoice (By Groups)', 
						'url' => array(
							'controller' => 'invoices', 
							'action' => 'group'
						),
						'allowed_roles' => array('projects')
					),
					array(
						'name' => 'Tango Cards', 
						'url' => array(
							'controller' => 'tangocards', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions'),
						'divider' => true
					),
					array(
						'name' => 'Pages', 
						'url' => array(
							'controller' => 'pages', 
							'action' => 'index'
						),
						'allowed_roles' => array('admin'),
						'divider' => true
					),
					array(
						'name' => 'Profile Surveys', 
						'url' => array(
							'controller' => 'profiles', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Qualification Questions', 
						'url' => array(
							'controller' => 'questions', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'Administrators', 
						'url' => array(
							'controller' => 'admins', 
							'action' => 'index'
						),
						'allowed_roles' => array('admin')
					),
					array(
						'name' => 'API Users', 
						'url' => array(
							'controller' => 'api_users', 
							'action' => 'index'
						),
						'allowed_roles' => array('admin')
					),
					array(
						'name' => 'Settings', 
						'url' => array(
							'controller' => 'settings', 
							'action' => 'index'
						),
						'allowed_roles' => array('admin')
					),
					array(
						'name' => 'Invalidate CloudFront', 
						'url' => array(
							'controller' => 'cloud_front', 
							'action' => 'index'
						),
						'allowed_roles' => array('transactions')
					),
					array(
						'name' => 'SMS Log',
						'url' => array(
							'controller' => 'sms_logs',
							'action' => 'index'
						),
						'allowed_roles' => array('admin')
					)
				)	
			),
		);
		
		// Skip the admin menu for external pms
		if (is_array($user['permission_group_keys']) && in_array('external_pms', $user['permission_group_keys'])) {
			$menu['admin']['allowed_roles'] = array();
		}
		
		return $menu;
	}	
}