<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class UsersController extends AppController {

	public $uses = array('User', 'Transaction', 'UserIp', 'IpProxy', 'HellbanLog', 'SourceMapping', 'Admin', 'UserAnalysis', 'GeoCountry', 'UserAddress', 'PaymentMethod', 'SurveyUserVisit', 'UserLog', 'QueryProfile', 'Source', 'TwilioNumber', 'BlockedEmail', 'SurveyVisitCache', 'Question', 'QuestionText', 'Answer', 'AnswerText', 'LucidZip', 'UniqueUser');
	public $helpers = array('Html', 'Time');
	public $components = array('RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('uservoice_profile');
	}
	
	public function export() {
		App::import('Model', 'UserReport');
		$this->UserReport = new UserReport;
		
		$header_values = array(
			'User.id' => 'Panelist ID',
			'User.email' => 'Email', 
			'User.fullname' => 'Name',
			'User.firstname' => 'Name (First)',
			'User.lastname' => 'Name (Last)',
			'QueryProfile.birthdate' => 'Birthdate',
			'User.created' => 'Created', 
			'User.last_touched' => 'Last Active', 
			'QueryProfile.gender' => 'Gender',
			'QueryProfile.hhi' => 'Household Income',
			'QueryProfile.education' => 'Education Level',
			'QueryProfile.children' => 'Has children under age 18', 
			'QueryProfile.employment' => 'Employment Status', 
			'QueryProfile.industry' => 'Employment Industry', 
			'QueryProfile.relationship' => 'Marital Status',
			'QueryProfile.ethnicity' => 'Ethnicity',
			'QueryProfile.housing_own' => 'Home - Rent or own?',
			'QueryProfile.smartphone' => 'Smartphone',
			'QueryProfile.tablet' => 'Tablet',
			'QueryProfile.country' => 'Country',
			'QueryProfile.state' => 'State',
			'QueryProfile.postal_code' => 'ZIP',
			'QueryProfile.postal_code_extended' => 'ZIP Code Extended',
			'QueryProfile.dma_code' => 'DMA',
			'QueryProfile.organization_size' => 'Organization Size',
			'QueryProfile.organization_revenue' => 'Organization Revenue',
			'QueryProfile.job' => 'Job title',
			'QueryProfile.department' => 'Department',
			'QueryProfile.housing_purchased' => 'Own any homes?',
			'QueryProfile.housing_plans' => 'Housing Plans',
			'QueryProfile.airlines' => 'Have traveled?',
			'UserAddress.first_name' => 'First Name (Address)',
			'UserAddress.last_name' => 'Last Name (Address)',
			'UserAddress.country' => 'Country (Address)',
			'UserAddress.postal_code' => 'Postal Code (Address)',
			'UserAddress.postal_code_extended' => 'Postal Code Extended (Address)',
			'UserAddress.state' => 'State (Address)',
			'UserAddress.county' => 'County (Address)',
			'UserAddress.city' => 'City (Address)',
			'UserAddress.address_line1' => 'Address (Line 1)',
			'UserAddress.address_line2' => 'Address (Line 2)',
			'UserAddress.verified' => 'Verified',
			'UserAddress.exact' => 'Address Exact Match',
			'TwilioNumber.number' => 'Phone Number',
			'TwilioNumber.type' => 'Phone Type',
			'TwilioNumber.name' => 'Phone Carrier',
		);

		App::import('Vendor', 'SiteProfile');
		
		$hhi_keys = unserialize(USER_HHI);
		$ethnicity_keys = unserialize(USER_ETHNICITY);
		$marital_keys = unserialize(USER_MARITAL);
		$edu_keys = unserialize(USER_EDU);
		$children_keys = unserialize(USER_CHILDREN);
		$employment_keys = unserialize(USER_EMPLOYMENT);
		$industry_keys = unserialize(USER_INDUSTRY);
		$home_keys = unserialize(USER_HOME);
		$smartphone_keys = unserialize(USER_SMARTPHONE);
		$tablet_keys = unserialize(USER_TABLET);
		$organization_size_keys = unserialize(USER_ORG_SIZE);
		$organization_revenue_keys = unserialize(USER_ORG_REVENUE);
		$job_keys = unserialize(USER_JOB);
		$department_keys = unserialize(USER_DEPARTMENT);
		$housing_purchase_keys = unserialize(USER_HOME_OWNERSHIP);
		$housing_plans_keys = unserialize(USER_HOME_PLANS);
		$airlines_keys = unserialize(USER_TRAVEL);
		
		if ($this->request->is('put') || $this->request->is('post')) {
			if (empty($this->request->data['User']['user_ids'])) {
				$this->Session->setFlash('Panelist ID is required.', 'flash_error');	
			}
			elseif (empty($this->request->data['User']['fields'])) {
				$this->Session->setFlash('Select at least 1 field.', 'flash_error');	
			}
			else {
				$panelist_ids = trim($this->request->data['User']['user_ids']);
				$panelist_ids = explode("\n", $panelist_ids);
				array_walk($panelist_ids, create_function('&$val', '$val = trim($val);')); 
				$panelist_ids = array_unique($panelist_ids); 
				$fields = array();
				foreach ($this->request->data['User']['fields'] as $field) {
					$fields[$field] = $header_values[$field];	
				}
				$userreportSource = $this->UserReport->getDataSource();
				$userreportSource->begin();
				$this->UserReport->create();
				$this->UserReport->save(array('UserReport' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'panelist_ids' => json_encode($panelist_ids),
					'fields' => json_encode($fields),
					'status' => 'queued'
				)));
				$user_report_id = $this->UserReport->getInsertId();
				$userreportSource->commit();

				$query = ROOT . '/app/Console/cake report export_panelist_data ' . $user_report_id;
				$query .= " > /dev/null &"; 
				exec($query, $output);
				CakeLog::write('report_commands', $query);

				$this->Session->setFlash('We are generating your report - check the status below.', 'flash_success');
			}
		}
		$this->set(compact('header_values'));
		
		$this->UserReport->bindModel(array(
			'belongsTo' => array(
				'Admin' => array(
					'foreignKey' => 'user_id',
					'fields' => array('id', 'admin_user')
				)
			)
		));

		$limit = 50;
		$paginate = array(
			'UserReport' => array(
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'UserReport.id DESC',
			)
		);
		
		$this->paginate = $paginate;
		$this->set('reports', $this->paginate('UserReport'));

	}
	
	public function uservoice_profile() {
		$this->layout = 'uservoice';
		$this->User->bindModel(array('hasOne' => array(
			'QueryProfile' => array(
				'foreignKey' => 'user_id'
			)
		)));
		if (isset($this->request->query['guid']) && !empty($this->request->query['guid']) && $this->request->query['guid'] != 'null') {
			$user = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $this->request->query['guid']
				)
			));
		}
		elseif (isset($this->request->query['email'])) {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.email' => urldecode($this->request->query['email'])
				),
				'order' => 'User.id'
			));
			if ($users) {
				foreach ($users as $user) {
					if (is_null($user['User']['deleted_on'])) {
						break;
					}
				}	
			}
		}
		
		if (isset($user) && $user) {
			$user_analysis = $this->UserAnalysis->find('first', array(
				'conditions' => array(
					'UserAnalysis.user_id' => $user['User']['id'],
				),
				'order' => 'UserAnalysis.id DESC'
			));
		
			$active_payment_method = $this->PaymentMethod->find('first', array(
				'conditions' => array(
					'PaymentMethod.user_id' => $user['User']['id'],
					'PaymentMethod.status' => DB_ACTIVE
				)
			));
			
			App::import('Model', 'SurveyUserVisit');
			$this->SurveyUserVisit = new SurveyUserVisit;
			
			$survey_user_visits = $this->SurveyUserVisit->find('all', array(
				'conditions' => array(
					'SurveyUserVisit.user_id' => $user['User']['id'],
				),
				'order' => 'SurveyUserVisit.id DESC',
				'limit' => 3
			));
			App::import('Vendor', 'SiteProfile');
			$this->set(compact('profile_answers', 'user', 'user_analysis', 'active_payment_method', 'survey_user_visits'));
		}
	}

	public function ajax_referrer($user_id = null) {
		if ($this->request->is('put') || $this->request->is('post')) {
			$user = $this->User->findById($this->request->data['id']); 
			if (!$user) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'message' => 'You are trying to set a referrer on an invalid user. Please refresh the page and try again.'
					)), 
					'type' => 'json',
					'status' => '400'
				));	
			}
			
			if (!empty($this->request->data['referrer'])) {
				$referrer = $this->User->find('first', array(
					'conditions' => array(
						'User.email' => $this->request->data['referrer'],
						'User.deleted_on' => null
					)
				)); 
				if (!$referrer) {
					return new CakeResponse(array(
						'body' => json_encode(array(
							'message' => $this->request->data['referrer'].' is not a MintVine user.'
						)), 
						'type' => 'json',
						'status' => '400'
					));	
				}
				if ($referrer['User']['referred_by'] == $user['User']['id']) {
					return new CakeResponse(array(
						'body' => json_encode(array(
							'message' => 'You cannot create a circular referral chain (User A referred User B who referred User A)'
						)), 
						'type' => 'json',
						'status' => '400'
					));	
				}
			}
			
			$existing_user = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $user['User']['id']
				),
			));
			$referred_by = empty($this->request->data['referrer']) ? '0' : $referrer['User']['id'];
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'referred_by' => $referred_by
			)), true, array('referred_by')); 
			
			$this->UserLog->create();
			$this->UserLog->save(array('UserLog' => array(
				'user_id' => $user['User']['id'],
				'type' => 'user.updated',
				'description' => 'referred_by updated from "' . $existing_user['User']['referred_by'] . '" to "' . $referred_by . '"'
			)));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => empty($this->request->data['referrer']) 
						? 'The referrer for this user has been successfully unset. You may close this window.'
						: 'The referrer for this user has been set to '.$referrer['User']['email'].'. You may close this window.'
				)), 
				'type' => 'json',
				'status' => '201'
			));	
		}
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'fields' => array('User.id', 'Referrer.email')
		));
		$this->set(compact('user'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	public function index() {
		$paginate = array();
		$conditions = array();
		$order = 'User.verified DESC'; 
		$showing_hellbanned = false;
		if (isset($this->request->data['User']['options']) && !empty($this->request->data['User']['options'])) {
			$export_options = $this->request->data['User']['options'];
		}
		else {
			$export_options = array();
		}
		if (isset($this->request->data['User']['hashed']) && !empty($this->request->data['User']['hashed'])) {
			$hashed = true;
		}
		else {
			$hashed = false;
		}
		if (isset($this->request->data['User']['twilio_phone']) && !empty($this->request->data['User']['twilio_phone'])) {
			$export_phone = true;
		}
		else {
			$export_phone = false;
		}
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->data)) {
			if (isset($this->data['keyword']) && !empty($this->data['keyword'])) {
				if ($this->data['keyword']{0} == '#') {
					if (strpos($this->data['keyword'], ',') !== false) {
						$user_ids = explode(',', $this->data['keyword']); 
						foreach ($user_ids as $key => $val) {
							$user_ids[$key] = substr($val, 1);
						}
						$conditions['User.id'] = $user_ids;
					}
					else {
						$conditions['User.id'] = substr($this->data['keyword'], 1);
					}
				}
				else {
					
					$conditions['OR'] = array(
						'User.fullname LIKE' => '%'.$this->data['keyword'].'%',
						'User.email LIKE' => '%'.$this->data['keyword'].'%',
						'User.username LIKE' => '%'.$this->data['keyword'].'%',
					);
					
					// find all matching phone numbers
					$mobile_number[] = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '$1-$2-$3', $this->data['keyword']);
					$mobile_number[] = preg_replace('~.*(\d{2})[^\d]*(\d{4})[^\d]*(\d{4}).*~', '$1-$2-$3', $this->data['keyword']);
					$mobile_number = array_unique($mobile_number);
					
					$twilio_number = $this->TwilioNumber->find('list', array(
						'fields' => array('id'),
						'conditions' => array(
							'TwilioNumber.number' => $mobile_number
						)
					));

					if ($twilio_number) {
						$twilio_number = array_unique($twilio_number);
						$conditions['OR']['User.twilio_number_id'] = $twilio_number;
					}

					// find all matching payments
					$user_ids = $this->PaymentMethod->find('list', array(
						'fields' => array('id', 'user_id'),
						'conditions' => array(							
							'PaymentMethod.value' => $this->data['keyword'],
							'PaymentMethod.payment_method' => 'paypal',
							'PaymentMethod.status' => DB_ACTIVE
						),
						'recursive' => -1
					));
					if ($user_ids) {
						$user_ids = array_unique($user_ids);
						$conditions['OR']['User.id'] = $user_ids;
					}
				}
			}
			if (isset($this->data['pubid']) && !empty($this->data['pubid'])) {
				$conditions['User.pub_id'] = $this->data['pubid'];
			}
			if (isset($this->data['country']) && !empty($this->data['country'])) {
				$conditions['QueryProfile.country'] = $this->data['country'];
			}
			if (isset($this->data['checked']) && !empty($this->data['checked'])) {
				$conditions['User.checked'] = $this->data['checked'];
			}
			if (isset($this->data['active']) && !empty($this->data['active'])) {
				if ($this->data['active'] == 1) {
					$conditions['User.active'] = 1;
				}	
				elseif ($this->data['active'] == 2) {
					$conditions['User.active'] = 0;
				}
				elseif ($this->data['active'] == 3) {
					$conditions['User.active'] = 1;
					$conditions['User.hellbanned_on <>'] = null;
					$order = 'User.hellbanned_on DESC';
					$showing_hellbanned = true;
				}
				elseif ($this->data['active'] == 4) {
					$conditions['User.active'] = 0;
					$conditions['User.deleted_on <>'] = null;
				}
				elseif ($this->data['active'] == 5) {
					$conditions['User.active'] = 1;
					$conditions['User.send_email'] = 0;
				}
				elseif ($this->data['active'] == 6) {
					$conditions['User.active'] = 0;
					$conditions['User.extended_registration'] = 1;
				}
			}
			if (isset($this->data['unsub']) && !empty($this->data['unsub'])) {
				$conditions['User.send_email'] = 0;
			}
			if (isset($this->data['source']) && !empty($this->data['source'])) {				
				$db_source = $this->Source->find('first', array(
					'conditions' => array(
						'Source.id' => $this->data['source']
					),
					'fields' => array(
						'id', 'abbr'
					),
					'recursive' => -1
				));
				if ($db_source) {
					$conditions[] = array('User.origin' => $db_source['Source']['abbr']);
				}
				else {
					$source_mapping = $this->SourceMapping->find('first', array(
						'conditions' => array(
							'SourceMapping.utm_source' => $this->data['source']
						)
					));
					if ($source_mapping) {
						$conditions[] = array('User.origin' => $source_mapping['SourceMapping']['utm_source']);
					}
				}
				
				if (!empty($conditions)) {
					$origin_condition = end($conditions);
					$pub_ids = $this->User->find('all', array(
						'recursive' => -1,
						'fields' => array('DISTINCT (User.pub_id) AS pub_id'),
						'conditions' => array(
							key($conditions) => $origin_condition,
							'User.pub_id is not null'
						),
						'order' => 'pub_id ASC'
					));
				
					$publishers = array();
					foreach ($pub_ids as $pub_id) {
						$publishers[$pub_id['User']['pub_id']] = $pub_id['User']['pub_id'];
					}
				}
				else {
					$publishers = false;
				}
				
				$this->set('publishers', $publishers);
			}
			if (isset($this->data['created_from']) && !empty($this->data['created_from'])) {
				if (isset($this->data['created_to']) && !empty($this->data['created_to'])) {
					$conditions['User.created >='] = date(DB_DATE, strtotime($this->data['created_from']));
					$conditions['User.created <='] = date(DB_DATE, strtotime($this->data['created_to']) + 86400);
				}
				else {
					$conditions['User.created >='] = date(DB_DATE, strtotime($this->data['created_from'])).' 00:00:00';
					$conditions['User.created <='] = date(DB_DATE, strtotime($this->data['created_from'])).' 23:59:59';
				}
			}
			if (isset($this->data['hellbanned_from']) && !empty($this->data['hellbanned_to'])) {
				if (isset($this->data['hellbanned_to']) && !empty($this->data['hellbanned_to'])) {
					$conditions['User.hellbanned_on >='] = date(DB_DATE, strtotime($this->data['hellbanned_from']));
					$conditions['User.hellbanned_on <='] = date(DB_DATE, strtotime($this->data['hellbanned_to']) + 86400);
				}
				else {
					$conditions['User.hellbanned_on >='] = date(DB_DATE, strtotime($this->data['hellbanned_from'])).' 00:00:00';
					$conditions['User.hellbanned_on <='] = date(DB_DATE, strtotime($this->data['hellbanned_to'])).' 23:59:59';
				}
			}
			if (isset($this->data['verified_from']) && !empty($this->data['verified_from'])) {
				if (isset($this->data['verified_to']) && !empty($this->data['verified_to'])) {
					$conditions['User.verified >='] = date(DB_DATE, strtotime($this->data['verified_from']));
					$conditions['User.verified <='] = date(DB_DATE, strtotime($this->data['verified_to']) + 86400);
				}
				else {
					$conditions['User.verified >='] = date(DB_DATE, strtotime($this->data['verified_from'])).' 00:00:00';
					$conditions['User.verified <='] = date(DB_DATE, strtotime($this->data['verified_from'])).' 23:59:59';
				}
			}
			
			if (isset($this->data['user_level']) && !empty($this->data['user_level'])) {
				$conditions = array_merge($conditions, MintVineUser::user_level_date($this->data['user_level']));
			}
		}
		
		if (isset($this->data['limit']) && !empty($this->data['limit'])) {
			$limit = $this->data['limit'];
		}
		else {
			$limit = 50;
		}
		
		if (isset($this->data['query_history_id'])) {
			$paginate = array(
				'User' => array(
					'limit' => $limit,
					'order' => $order,
					'joins' => array(
						array('table' => 'survey_users',
							'alias' => 'SurveyUsers',
							'conditions' => array(
								"SurveyUsers.user_id = User.id",
								'SurveyUsers.query_history_id' => $this->data['query_history_id']
							)
						),
					)
				)
			);
		}
		
		if (isset($this->data['export']) && $this->data['export'] == 1) {
			$options = array();
			App::import('Vendor', 'SiteProfile');
			ini_set('memory_limit', '2048M');
			$options['contain'] = array(
				'QueryProfile' => array(
					'fields' => array_merge(array('birthdate', 'gender', 'country', 'postal_code'), $export_options)
				)
			);
			$options['fields'] = array('id', 'fb_id', 'email', 'last_touched');
			if ($export_phone) {
				$this->User->bindModel(array('belongsTo' => array(
					'TwilioNumber' => array(
						'fields' => array('id', 'number', 'type', 'name')
					)
				)));
				$options['contain']['TwilioNumber'] = array(
					'fields' => array('id', 'number', 'type', 'name')
				);
			}
			$options['joins'] = (isset($paginate['User']['joins'])) ? $paginate['User']['joins'] : array();
			$paginate_conditions = (isset($paginate['User']['conditions'])) ? $paginate['User']['conditions'] : array();
			$options['conditions'] = array_merge($paginate_conditions, $conditions);
			
			$users = $this->User->find('all', $options);
			$data = array();

			if ($hashed === true) {
				$data[] = array_merge(array(
					'MintVine ID',
					'Facebook ID',
					'Email',
					'Email (Hashed)',
					'Last Activity',
					'Age',
					'Birthdate',
					'Gender',
					'Country',
					'Postal Code'
				), $export_options);
			}
			else {
				$data[] = array_merge(array(
					'MintVine ID',
					'Facebook ID',
					'Email',
					'Last Activity',
					'Age',
					'Birthdate',
					'Gender',
					'Country',
					'Postal Code'
				), $export_options);
			}
			if ($export_phone) {
				array_push($data[0], 'Phone Number', 'Phone Type', 'Phone Carrier');
			}	

			$qp_data = array(
				'hhi' => unserialize(USER_HHI),
				'education' => unserialize(USER_EDU),
				'ethnicity' => unserialize(USER_ETHNICITY),
				'relationship' => unserialize(USER_MARITAL),
				'employment' => unserialize(USER_EMPLOYMENT),
				'industry' => unserialize(USER_INDUSTRY),
				'department' => unserialize(USER_DEPARTMENT),
				'job' => unserialize(USER_JOB),
				'housing_own' => unserialize(USER_HOME),
				'housing_purchased' => unserialize(USER_HOME_OWNERSHIP),
				'housing_plans' => unserialize(USER_HOME_PLANS),
				'children' => unserialize(USER_CHILDREN),
				'organization_size' => unserialize(USER_ORG_SIZE),
				'organization_revenue' => unserialize(USER_ORG_REVENUE),
				'tablet' => unserialize(USER_TABLET),
				'airlines' => unserialize(USER_TRAVEL)
			);
			foreach ($users as $user) {
				$qp_fields = array();
				foreach ($export_options as $field) {
					switch ($field) {
						case 'hhi':
							$qp_fields[] = (isset($qp_data['hhi'][$user['QueryProfile']['hhi']])) ? $qp_data['hhi'][$user['QueryProfile']['hhi']] : '';
							break;
						case 'education':
							$qp_fields[] = (isset($qp_data['education'][$user['QueryProfile']['education']])) ? $qp_data['education'][$user['QueryProfile']['education']] : '';
							break;
						case 'ethnicity':
							$qp_fields[] = (isset($qp_data['ethnicity'][$user['QueryProfile']['ethnicity']])) ? $qp_data['ethnicity'][$user['QueryProfile']['ethnicity']] : '';
							break;
						case 'relationship':
							$qp_fields[] = (isset($qp_data['relationship'][$user['QueryProfile']['relationship']])) ? $qp_data['relationship'][$user['QueryProfile']['relationship']] : '';
							break;
						case 'employment':
							$qp_fields[] = (isset($qp_data['employment'][$user['QueryProfile']['employment']])) ? $qp_data['employment'][$user['QueryProfile']['employment']] : '';
							break;
						case 'industry':
							$qp_fields[] = (isset($qp_data['industry'][$user['QueryProfile']['industry']])) ? $qp_data['industry'][$user['QueryProfile']['industry']] : '';
							break;
						case 'department':
							$qp_fields[] = (isset($qp_data['department'][$user['QueryProfile']['department']])) ? $qp_data['department'][$user['QueryProfile']['department']] : '';
							break;
						case 'job':
							$qp_fields[] = (isset($qp_data['job'][$user['QueryProfile']['job']])) ? $qp_data['job'][$user['QueryProfile']['job']] : '';
							break;
						case 'housing_own':
							$qp_fields[] = (isset($qp_data['housing_own'][$user['QueryProfile']['housing_own']])) ? $qp_data['housing_own'][$user['QueryProfile']['housing_own']] : '';
							break;
						case 'housing_purchased':
							$qp_fields[] = (isset($qp_data['housing_purchased'][$user['QueryProfile']['housing_purchased']])) ? $qp_data['housing_purchased'][$user['QueryProfile']['housing_purchased']] : '';
							break;
						case 'housing_plans':
							$qp_fields[] = (isset($qp_data['housing_plans'][$user['QueryProfile']['housing_plans']])) ? $qp_data['housing_plans'][$user['QueryProfile']['housing_plans']] : '';
							break;
						case 'children':
							$qp_fields[] = (isset($qp_data['children'][$user['QueryProfile']['children']])) ? $qp_data['children'][$user['QueryProfile']['children']] : '';
							break;
						case 'organization_size':
							$qp_fields[] = (isset($qp_data['organization_size'][$user['QueryProfile']['organization_size']])) ? $qp_data['organization_size'][$user['QueryProfile']['organization_size']] : '';
							break;
						case 'organization_revenue':
							$qp_fields[] = (isset($qp_data['organization_revenue'][$user['QueryProfile']['organization_revenue']])) ? $qp_data['organization_revenue'][$user['QueryProfile']['organization_revenue']] : '';
							break;
						case 'tablet':
							$qp_fields[] = (isset($qp_data['tablet'][$user['QueryProfile']['tablet']])) ? $qp_data['tablet'][$user['QueryProfile']['tablet']] : '';
							break;
						case 'airlines':
							$qp_fields[] = (isset($qp_data['airlines'][$user['QueryProfile']['airlines']])) ? $qp_data['airlines'][$user['QueryProfile']['airlines']] : '';
							break;
						case 'default':
							$qp_fields[] = '';
							break;
					}
				}		
				if ($export_phone) {
					$qp_fields[] = $user['TwilioNumber']['number'];
					$qp_fields[] = $user['TwilioNumber']['type'];
					$qp_fields[] = $user['TwilioNumber']['name'];
				}			
				if ($hashed === true) {
					$data[] = array_merge(array(
						$user['User']['id'],
						$user['User']['fb_id'],
						$user['User']['email'],
						hash('sha256', $user['User']['email']),
						$user['User']['last_touched'],
						date_diff(date_create($user['QueryProfile']['birthdate']), date_create('today'))->y,
						$user['QueryProfile']['birthdate'],
						$user['QueryProfile']['gender'],
						$user['QueryProfile']['country'],
						$user['QueryProfile']['postal_code'],
					), $qp_fields);
				}
				else {
					$data[] = array_merge(array(
						$user['User']['id'],
						$user['User']['fb_id'],
						$user['User']['email'],
						$user['User']['last_touched'],
						date_diff(date_create($user['QueryProfile']['birthdate']), date_create('today'))->y,
						$user['QueryProfile']['birthdate'],
						$user['QueryProfile']['gender'],
						$user['QueryProfile']['country'],
						$user['QueryProfile']['postal_code'],
					), $qp_fields);
				}
			}

			$filename = 'user_export-' . gmdate(DB_DATE, time()) . '.csv';
			$csv_file = fopen('php://output', 'w');
			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			foreach ($data as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}
			fclose($csv_file);

			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
		
		if (!empty($conditions) || !empty($paginate)) {
			if (isset($paginate['User']['conditions'])) {
				$paginate['User']['conditions'] = array_merge($paginate['User']['conditions'], $conditions);
			}
			else {
				$paginate['User']['conditions'] = $conditions;
			}
			
			$this->User->bindModel(array('hasMany' => array('HellbanLog' => array(
				'fields' => array('HellbanLog.automated'),
				'conditions' => array(
					'HellbanLog.type' => 'unhellban'
				),
				'order' => 'HellbanLog.id DESC'
			))));
			$this->paginate = $paginate;		
			$users = $this->paginate();
			
			// for user search, for one result, go directly to the result
			if (isset($this->request->query['keyword']) && !empty($this->request->query['keyword']) && count($users) == 1) {
				return $this->redirect(array('controller' => 'panelist_histories', 'action' => 'user', '?' => array('user_id' => $users[0]['User']['id'])));
			} 
		}
		
		$mappings = array(
			'hhi' => 'Household Income',
			'education' => 'Education Level',
			'ethnicity' => 'Ethnicity',
			'relationship' => 'Marital Status',
			'employment' => 'Employment Status',
			'industry' => 'Job Industry',
			'department' => 'Job Department',
			'job' => 'Job Title',
			'housing_own' => 'Rent or Own?',
			'housing_purchased' => 'Home purchased in 3 years?',
			'housing_plans' => 'Home plans',
			'children' => 'Has Children',
			'organization_size' => 'Organization Size',
			'organization_revenue' => 'Organization Revenue',
			'tablet' => 'Owns Tablet',
			'airlines' => 'Have you traveled by plane?',
		);
		
		$sources = $this->Source->find('list', array(
			'conditions' => array(
				'Source.active' => true
			),
			'fields' => array(
				'id', 'name'
			),
			'order' => array(
				'name' => 'ASC'
			)
		));
		
		$countries = array('US' => 'US', 'GB' => 'GB', 'CA' => 'CA');
		$this->set(compact('users', 'showing_hellbanned', 'countries', 'mappings', 'sources'));
	}
		
	public function referral_tree($user_id) {
		if ($this->request->is('put') || $this->request->is('post')) {
			$i = 0;
			if (isset($this->request->data['user']) && !empty($this->request->data['user'])) {
				if (isset($this->request->data['User']['btn_hellban'])) {
					foreach ($this->request->data['user'] as $hellban_user_id => $value) {
						if ($value == 1) {
							$user = $this->User->find('first', array(
								'conditions' => array(
									'User.hellbanned' => 0,
									'User.id' => $hellban_user_id,
								),
								'recursive' => -1,
								'fields' => array('hellbanned')
							));
							if ($user) {
								MintVineUser::hellban($hellban_user_id, 'Manual hellban from referral tree view', $this->current_user['Admin']['id']);
								$i++;
							}
						}
					}
				}
				elseif(isset($this->request->data['User']['btn_remove_hellban'])) {
					foreach ($this->request->data['user'] as $hellban_user_id => $value) {
						if ($value == 1) {
							$user = $this->User->find('first', array(
								'conditions' => array(
									'User.hellbanned' => 1,
									'User.id' => $hellban_user_id,
								),
								'recursive' => -1,
								'fields' => array('hellbanned')
							));
							if ($user) {
								$this->User->create();
								$this->User->save(array('User' => array(
									'id' => $hellban_user_id,
									'hellbanned' => '0',
									'checked' => '1',
									'hellbanned_on' => null
								)), true, array('hellbanned', 'checked', 'hellbanned_on'));
								
								$query_profile = $this->QueryProfile->find('first', array(
									'conditions' => array(
										'QueryProfile.user_id' => $hellban_user_id
									),
									'recursive' => -1,
									'fields' => array('id')
								));
								if ($query_profile) {
									$this->QueryProfile->create();
									$this->QueryProfile->save(array('QueryProfile' => array(
										'id' => $query_profile['QueryProfile']['id'],
										'ignore' => false
									)), true, array('ignore'));
								}
								
								$this->HellbanLog->create();
								$this->HellbanLog->save(array('HellbanLog' => array(
									'user_id' => $hellban_user_id,
									'admin_id' => $this->current_user['Admin']['id'],
									'type' => 'unhellban',
									'automated' => false
								)));
								$i++;
							}
						}
					}
					
				}
			}
			if ($i > 0) {
				if(isset($this->request->data['User']['btn_remove_hellban'])) {
					$this->Session->setFlash($i.' users have been unhellbanned.', 'flash_success');
				}
				else {
					$this->Session->setFlash($i.' users have been hellbanned.', 'flash_success');
				}
			}
			$this->redirect(array('action' => 'referral_tree', $user_id));
		}
		
		$this->User->bindModel(array('hasOne' => array('PaymentMethod' => array(
			'conditions' => array(
				'PaymentMethod.status' => DB_ACTIVE
			)
		))), false);
		$user = $this->User->findById($user_id);
		$user = $this->__bind_user_analysis($user);
		$referred_users = $this->User->find('all', array(
			'conditions' => array(
				'User.referred_by' => $user_id
			)
		));
		$user_ids = array();
		$grandchildren = false;
		if ($referred_users) {
			foreach ($referred_users as $referred_user) {
				$user_ids[] = $referred_user['User']['id'];
			}
		}
		if (!empty($user_ids)) {
			$grandchildren = array();
			while (true) {				
				$found_grandchildren = $this->User->find('all', array(
					'conditions' => array(
						'User.referred_by' => $user_ids
					)
				));
				if (!$found_grandchildren) {
					break;
				}
				$grandchildren = array_merge($grandchildren, $found_grandchildren);
				$user_ids = array();
				foreach ($found_grandchildren as $found_grandchild) {
					$user_ids[] = $found_grandchild['User']['id'];
				}
			}
			$referred_users = array_merge($referred_users, $grandchildren);
		}
		if ($referred_users) {
			foreach ($referred_users as $key => $referred_user) {
				$referred_user = $this->__bind_user_analysis($referred_user);
				$referred_users[$key] = $referred_user;
			}
		}
		$grandparent = false;
		if (!empty($user['Referrer']['id'])) {
			$parent = $this->User->findById($user['Referrer']['id']); 
			$parent = $this->__bind_user_analysis($parent);
			$user['Referrer'] = $parent;
			$grandparent = $this->User->findById($parent['User']['referred_by']);
			if ($grandparent) {
				$grandparent = $this->__bind_user_analysis($grandparent);
			}
		}
		$title_for_layout = sprintf('Referral Tree - %s', $user['User']['email']);
		$this->set(compact('referred_users', 'user', 'grandparent', 'title_for_layout'));
	}
	
	public function hellbans() {	
		$conditions = array();
		if (isset($this->request->query['user']) && !empty($this->request->query['user'])) {
			$conditions['HellbanLog.user_id'] = $this->request->query['user'];
			// If user id is provided, get email address of that user for page title
			$user = $this->User->find('first', array(
				'recursive' => -1,
				'fields' => 'email',
				'conditions' => array(
					'User.id' => $this->request->query['user']
				)
			));
		}	
		$paginate = array(
			'HellbanLog' => array(
				'conditions' => $conditions,
				'limit' => 50,
				'order' => 'HellbanLog.id DESC'
			)
		);
		$this->paginate = $paginate;
		$this->HellbanLog->bindModel(array('belongsTo' => array('User', 'Admin')));
		$users = $this->paginate('HellbanLog');
		if (isset($user) && !empty($user)) {
			$title_for_layout = sprintf('Hellban Log - %s', $user['User']['email']);
		}
		else {
			$title_for_layout = 'Hellban Log';
		}
		$this->set(compact('users', 'title_for_layout'));
	}
	
	public function quickscore($user_id) {
		App::import('Model', 'UserAnalysis');
		$this->UserAnalysis = new UserAnalysis;
		
		$this->layout = 'ajax';		
		$user_analysis = $this->UserAnalysis->find('first', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user_id
			),
			'order' => 'UserAnalysis.id DESC',
		));
		$weights = unserialize(USER_ANALYSIS_WEIGHTS);
		$this->set(compact('user_analysis', 'weights'));
	}
	
	public function quickscores($user_id) {
		$this->layout = 'ajax';
		$scores = $this->UserAnalysis->find('all', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user_id
			),
			'order' => 'UserAnalysis.id DESC'
		));
		
		$weights = unserialize(USER_ANALYSIS_WEIGHTS);
		$this->set(compact('weights', 'scores'));
	}
	
	public function quickprofile($user_id) {
		$this->layout = 'ajax';
		$this->User->bindModel(array('hasOne' => array(
			'QueryProfile' => array(
				'foreignKey' => 'user_id'
			)
		)));
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			)
		));
		// get QE2 profile
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username',
					'qe.mintvine.password',
					'hostname.qe'
				),
				'Setting.deleted' => false
			)
		));
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		try {
			$return = $http->get($settings['hostname.qe'].'/qualifications/'.$user_id);
			if ($return->code == 200) {
				$user_qualification = json_decode($return->body, true); 
			}
		}
		catch (\HttpException $ex) {
			CakeLog::write('qe.user.qualification', $ex->getMessage());
		}
		catch (\Exception $ex) {
			CakeLog::write('qe.user.qualification', $ex->getMessage());
		}
		$qualifications = array();
		if (!empty($user_qualification['answered']['lucid'])) {
			ksort($user_qualification['answered']['lucid']);
			foreach ($user_qualification['answered']['lucid'] as $question_id => $answer_ids) {
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					)
				))); 
				$qes_conditions = $ans_conditions = array();
				$qes_conditions['QuestionText.country'] = 'US';
				$ans_conditions['AnswerText.country'] = 'US';
				
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'fields' => array('QuestionText.id', 'QuestionText.text'),
							'conditions' => $qes_conditions
						)
					),
					'hasMany' => array(
						'Answer' => array(
							'foreignKey' => 'question_id',
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.question_id' => 'Question.id'
							)
						)
					),
				));
				$questions = $this->Question->find('first', array(
					'fields' => array('Question.question', 'Question.partner_question_id'),
					'conditions' => array(
						'Question.partner_question_id' => $question_id
					),
					'order' => 'Question.partner_question_id asc',
					'contain' => array(
						'QuestionText',
						'Answer' => array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.partner_answer_id' => $answer_ids
							),
							'AnswerText' => array(
								'fields' => array('AnswerText.text'),
								'conditions' => $ans_conditions
							)
						)
					)
				));
			
				if (!empty($questions) && $question_id == 42) {
					$date = new DateTime(date(DB_DATETIME, strtotime($answer_ids[0])));
					$now = new DateTime();
					$interval = $now->diff($date);
					$questions['Answer'][]['AnswerText'] = array('text' => $interval->y);
				}	
				elseif (!empty($questions) && $question_id == 45) {
					$questions['Answer'][]['AnswerText'] = array('text' => $answer_ids[0]);
				}	
				elseif (!empty($questions) && $question_id == 98) {
					$lucid_zips = $this->LucidZip->find('all', array(
						'fields' => array(
							'LucidZip.state_fips', 'LucidZip.county_fips', 'LucidZip.county'
						),
						'conditions' => array(
							'CONCAT(LPAD(LucidZip.state_fips, 2, 0), LPAD(LucidZip.county_fips, 3, 0))' => $answer_ids[0]
						),
						'group' => array('LucidZip.state_fips', 'LucidZip.county_fips'),
						'order' => 'LucidZip.county ASC'
					));
					foreach ($lucid_zips as $lucid_zip) {
						$formatted_county = str_pad($lucid_zip['LucidZip']['state_fips'], 2, '0', STR_PAD_LEFT) . str_pad($lucid_zip['LucidZip']['county_fips'], 3, '0', STR_PAD_LEFT);
						if (!isset($panelists['location']['counties'][$formatted_county])) {
							$questions['Answer'][]['AnswerText'] = array('text' => $lucid_zip['LucidZip']['county']);
						}
					}
				}
				
				$qualifications[] = $questions;
			}	
		}
		
		if ($user['User']['twilio_number_id'] > 0) {
			$twilio_number = $this->TwilioNumber->find('first', array(
				'conditions' => array(
					'TwilioNumber.id' => $user['User']['twilio_number_id']
				),
				'recursive' => -1
			));
			$this->set(compact('twilio_number'));
		}
		
		$user_analysis = $this->UserAnalysis->find('first', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user_id
			),
			'order' => 'UserAnalysis.id DESC'
		));
		
		$user_address = $this->UserAddress->find('first', array(
			'conditions' => array(
				'UserAddress.user_id' => $user_id,
				'UserAddress.deleted' => false
			),
			'order' => 'UserAddress.id DESC'
		));
		
		$active_payment_method = $this->PaymentMethod->find('first', array(
			'conditions' => array(
				'PaymentMethod.user_id' => $user_id,
				'PaymentMethod.status' => DB_ACTIVE
			)
		));
		
		App::import('Vendor', 'SiteProfile');
		$this->set(compact('profile_answers', 'user', 'user_analysis', 'active_payment_method', 'user_address', 'qualifications'));
	}
	
	public function history($user_id) {
		App::import('Vendor', 'geoip/geoipcity'); 	
		App::import('Vendor', 'geoip/geoipregionvars'); 
		$gi = geoip_open(APP."Vendor/geoip/GeoIPRegion-115.dat", GEOIP_STANDARD);
		
		$paginate = array(
			'UserIp' => array(
				'fields' => array('Project.id', 'Project.description', 'Project.survey_name', 'IpProxy.*', 'UserIp.*'),
				'limit' => 1500,
				'order' => 'UserIp.id DESC',
				'joins' => array(
	    		    array(
			            'alias' => 'IpProxy',
			            'table' => 'ip_proxies',
			            'type' => 'LEFT',
			            'conditions' => array(
							'UserIp.ip_address = IpProxy.ip_address',
						)
			        ),
	    		    array(
			            'alias' => 'Project',
			            'table' => 'projects',
			            'type' => 'LEFT',
			            'conditions' => array(
							'UserIp.survey_id = Project.id',
						)
			        )
				)
			)
		);
		$filter = false;
		if (!isset($this->request->query['filter'])) {
			$paginate['UserIp']['conditions'] = array('UserIp.user_id' => $user_id);
		}
		else {
			$paginate['UserIp']['conditions'] = array(
				'UserIp.user_id' => $user_id,
				'UserIp.type' => 'survey'
			);
			$filter = 'surveys';
			$paginate['UserIp']['limit'] = 10000;
		}
		
		$this->paginate = $paginate;
		$user_ips = $this->paginate('UserIp'); 
		if ($user_ips) {
			foreach ($user_ips as $key => $user_ip) {
				if ($user_ip['UserIp']['type'] != 'survey') {
					continue;
				}
				$user_survey_visits = $this->SurveyUserVisit->find('first', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user_id,
						'SurveyUserVisit.survey_id' => $user_ip['Project']['id']
					),
					'order' => 'SurveyUserVisit.id DESC'
				)); 
				if ($user_survey_visits) {
					$user_ips[$key]['SurveyUserVisit'] = $user_survey_visits['SurveyUserVisit'];
				}
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $user_ip['Project']['id']
					)
				));
				if ($survey_visit_cache) {
					$user_ips[$key]['SurveyVisitCache'] = $survey_visit_cache['SurveyVisitCache'];
				}
			}
		}
		
		$this->User->bindModel(array('hasMany' => array('HellbanLog' => array(
			'fields' => array('HellbanLog.automated'),
			'conditions' => array(
				'HellbanLog.type' => 'unhellban'
			),
			'order' => 'HellbanLog.id DESC'
		))));
		$user = $this->User->findById($user_id);
		if ($user['User']['twilio_number_id'] > 0) {
			$twilio_number = $this->TwilioNumber->find('first', array(
				'conditions' => array(
					'TwilioNumber.id' => $user['User']['twilio_number_id']
				),
				'recursive' => -1
			));
			$this->set(compact('twilio_number'));
		}
		$this->loadModel('UserAnalysis');
		$user_analysis = $this->UserAnalysis->find('first', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user_id
			),
			'order' => 'UserAnalysis.id DESC'
		));
		$title_for_layout = sprintf('User History - %s', $user['User']['email']);
		
		// gotta prettify the user agents
		App::import('Model', 'UserAgent');
		$this->UserAgent = new UserAgent;
		
		$user_ip_agents = array();
		if ($user_ips) {
			foreach ($user_ips as $user_ip) {
				$user_ip_agents[] = $user_ip['UserIp']['user_agent'];
			}
		}
		$user_ip_agents = array_unique($user_ip_agents); 
		
		// write missing user agents to DB
		if (!empty($user_ip_agents)) {
			$settings = $this->Setting->find('list', array(
				'conditions' => array(
					'Setting.name' => 'useragent.key',
					'Setting.deleted' => false
				),
				'fields' => array('Setting.name', 'Setting.value')
			));
			if (count($settings) == 1) {
				$user_agents = $this->UserAgent->find('list', array(
					'conditions' => array(
						'UserAgent.user_agent' => $user_ip_agents
					),
					'fields' => array('UserAgent.user_agent', 'UserAgent.id'),
					'recursive' => -1
				));
				
				foreach ($user_ip_agents as $user_ip_agent) {
					// grab and populate data if it exists
					if (!isset($user_agents[$user_ip_agent])) {
						$http = new HttpSocket(array(
							'timeout' => 15,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						)); 
						$results = $http->get('http://useragentapi.com/api/v2/json/'.$settings['useragent.key'].'/'.urlencode($user_ip_agent));
						
						if ($results->code == 200) {
							$data = json_decode($results->body, true);
							if (isset($data) && !empty($data) && is_array($data)) {
								$userAgentSource = $this->UserAgent->getDataSource();
								$userAgentSource->begin();
								$this->UserAgent->create();
								$this->UserAgent->save(array('UserAgent' => array(
									'user_agent' => $user_ip_agent
								)));
								$user_agent_id = $this->UserAgent->getInsertId();
								$userAgentSource->commit();
								foreach ($data as $key => $val) {
									$this->UserAgent->UserAgentValue->create();
									$this->UserAgent->UserAgentValue->save(array(
										'user_agent_id' => $user_agent_id,
										'name' => $key,
										'value' => $val
									));
								}
								$user_agents[$user_ip_agent] = $user_agent_id;
							}
						}
					}
				}
			}
		}
		
		$agents = array();
		if (isset($user_agents) && $user_agents) {
			$agents_untransformed = $this->UserAgent->find('all', array(
				'conditions' => array(
					'UserAgent.id' => $user_agents
				)
			));
			foreach ($agents_untransformed as $agent) {
				if (isset($agent['UserAgentValue'])) {
					$list = array();
					foreach ($agent['UserAgentValue'] as $agent_value) {
						$list[$agent_value['name']] = $agent_value['value'];
					}
					$agent['UserAgentValue'] = $list;
				}
				$agents[$agent['UserAgent']['id']] = $agent;
			}
		}
		$this->set(compact('user', 'user_ips', 'filter', 'user_analysis', 'title_for_layout', 'agents', 'user_agents'));
	}
	
	public function ip_address($ip_address, $export = false) {
		$fields = array('Project.id', 'Project.description', 'Project.survey_name', 'IpProxy.*', 'UserIp.*', 'User.*');
		$conditions = array('UserIp.ip_address' => $ip_address);
		$order = 'UserIp.id DESC';
		$joins = array(
			array(
				'alias' => 'IpProxy',
				'table' => 'ip_proxies',
				'type' => 'LEFT',
				'conditions' => array(
					'UserIp.ip_address = IpProxy.ip_address',
				)
			),
			array(
				'alias' => 'Project',
				'table' => 'projects',
				'type' => 'LEFT',
				'conditions' => array(
					'UserIp.survey_id = Project.id',
				)
			),
			array(
				'alias' => 'User',
				'table' => 'users',
				'type' => 'LEFT',
				'conditions' => array(
					'UserIp.user_id = User.id',
				)
			)
		);
		
		$this->loadModel('Report');
		$report = $this->Report->find('first', array(
			'fields' => array('Report.id', 'Report.status'),
			'conditions' => array(
				'type' => 'ip',
				'ip_address' => $ip_address
			)
		));
		
		if ($export) {
			$user_ips = $this->UserIp->find('count', array(
				'fields' => $fields,
				'order' => $order,
				'joins' => $joins,
				'conditions' => $conditions
			)); 
			
			if (!$user_ips) {
				$this->Session->setFlash('Ip Address not found.', 'flash_error');
				$this->redirect(array('action' => 'ip_address', $ip_address));
			}
			
			if ($report) {
				$data = array(
					'id' => $report['Report']['id'],
					'status' => 'queued'
				);
			}
			else {
				$data = array(
					'ip_address' => $ip_address,
					'type' => 'ip',
					'user_id' => $this->current_user['Admin']['id'],
				);
			}

			$this->Report->create();
			$this->Report->save(array('Report' => $data), true, array_keys($data));
			$query = ROOT . '/app/Console/cake report ip_address ' . $ip_address;
			$query.= " > /dev/null &";
			exec($query, $output);
			CakeLog::write('report_commands', $query);

			$this->Session->setFlash('We are generating your report', 'flash_success');
			$this->redirect(array('action' => 'ip_address', $ip_address));
		}
		else {
			$this->paginate = array(
				'UserIp' => array(
					'fields' => $fields,
					'limit' => 1500,
					'order' => $order,
					'joins' => $joins,
					'conditions' => $conditions
				)
			);
			$user_ips = $this->paginate('UserIp'); 
			
			if ($user_ips) {
				foreach ($user_ips as $key => $user_ip) {
					if ($user_ip['UserIp']['type'] != 'survey') {
						continue;
					}
					$user_survey_visits = $this->SurveyUserVisit->find('first', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyUserVisit.user_id' => $user_ip['UserIp']['user_id'],
							'SurveyUserVisit.survey_id' => $user_ip['Project']['id']
						),
						'order' => 'SurveyUserVisit.id DESC'
					));
					if ($user_survey_visits) {
						$user_ips[$key]['SurveyUserVisit'] = $user_survey_visits['SurveyUserVisit'];
					}
					$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
						'conditions' => array(
							'SurveyVisitCache.survey_id' => $user_ip['Project']['id']
						)
					));
					if ($survey_visit_cache) {
						$user_ips[$key]['SurveyVisitCache'] = $survey_visit_cache['SurveyVisitCache'];
					}
				}
			}
		}
	
		$this->set(compact('user_ips', 'report'));
	}
	
	public function scores($user_id) {
		
		$scores = $this->UserAnalysis->find('all', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user_id
			),
			'order' => 'UserAnalysis.id DESC'
		));		
		$this->set(compact('scores'));
		$this->layout = 'ajax';
	}
	
	public function hellban() {	
		MintVineUser::hellban($this->data['User']['id'], $this->data['User']['hellban_reason'], $this->current_user['Admin']['id']);
				
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => '1'
			)), 
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function delete($user_id) {	
		$this->User->delete($user_id);				
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => '1'
			)), 
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function unhellban() {	
		$user_id = $this->data['User']['id'];
		$reason = $this->data['User']['reason'];
		$this->User->create();
		$this->User->save(array('User' => array(
			'id' => $user_id,
			'hellbanned' => '0',
			'checked' => '1',
			'hellbanned_on' => null
		)), true, array('hellbanned', 'checked', 'hellbanned_on'));
		
		$query_profile = $this->QueryProfile->find('first', array(
			'conditions' => array(
				'QueryProfile.user_id' => $user_id
			),
			'recursive' => -1,
			'fields' => array('id')
		));
		if ($query_profile) {
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'ignore' => false
			)), true, array('ignore'));
		}
		
		$this->HellbanLog->create();
		$this->HellbanLog->save(array('HellbanLog' => array(
			'user_id' => $user_id,
			'admin_id' => $this->current_user['Admin']['id'],
			'type' => 'unhellban',
			'automated' => false,
			'reason' => $reason
		)));
		
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => '1'
			)),
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function test() {
		$ips = Utils::ip_address(); 
		db($ips); 
		exit();
	}
	
	public function login_as_user($user_id) {
		App::import('Model', 'Nonce');
		$this->Nonce = new Nonce;
		
		$nonce = String::uuid();
		
		$nonceDataSource = $this->Nonce->getDataSource();
		$nonceDataSource->begin();
		$this->Nonce->create();
		$saved = $this->Nonce->save(array('Nonce' => array(
			'item_id' => '0',
			'item_type' => 'adminlogin',
			'user_id' => $user_id,
			'nonce' => $nonce
		)));
		$nonce_id = $this->Nonce->getInsertId();
		$nonceDataSource->commit();
		
		return $this->redirect(HOSTNAME_WWW.'/users/nonce/'.$nonce.'?t='.time().'&nonce_id='.$nonce_id);
	}
	
	public function ajax_show_publishers($source = null) {
		if ($this->request->is('ajax')) {
			if (!$source) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'publishers' => array()
					)),
					'type' => 'json',
					'status' => '201'
				));
			}
			$conditions = array();
			$db_source = $this->Source->find('first', array(
				'conditions' => array(
					'Source.id' => $source
				),
				'fields' => array(
					'id', 'abbr'
				),
				'recursive' => -1
			));
			
			
			if ($db_source) {
				$conditions = array('User.origin' => $db_source['Source']['abbr']);
			}
			
			$conditions['not'] = array('User.pub_id' => null);
			$pub_ids = $this->User->find('all', array(
				'recursive' => -1,
				'fields' => array('DISTINCT (User.pub_id)'),
				'conditions' => $conditions,
				'order' => 'pub_id'
			));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'publishers' => $pub_ids
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
		
		return new CakeResponse(array(
			'body' => '',
			'type' => 'json',
			'status' => '400'
		));
	}
	
	public function ajax_pooled_points() {
		$this->layout = 'ajax';
		$this->loadModel('PooledPoint');
		$pooled = $this->PooledPoint->find('first', array(
			'fields' => array(
				'sum(points) as points'
			),
			'conditions' => array(
				'credit' => true
			),
		));
		
		$activated = $this->PooledPoint->find('first', array(
			'fields' => array(
				'sum(points) as points'
			),
			'conditions' => array(
				'active' => true,
				'credit' => true
			),
		));
		
		$paid = $this->PooledPoint->find('first', array(
			'fields' => array(
				'sum(points) as points'
			),
			'conditions' => array(
				'active' => true,
				'credit' => true,
				'paid' => true
			),
		));
		
		$redeemed = $this->PooledPoint->find('first', array(
			'fields' => array(
				'sum(points) as points'
			),
			'conditions' => array('credit' => false),
		));
		
		$this->set(compact('pooled', 'activated', 'paid', 'redeemed'));
	}
	
	public function ajax_save_timezone() {
		if ($this->request->is(array('post', 'put')) && $this->request->is('ajax')) {
			$tz = timezone_name_from_abbr(null, ($this->request->data['offset'] * 60), true); //offset is coming in minutes, we convert into seconds here
			if ($tz === false) { //This check is due to bug in timezone_name_from_abbr() function, if daylight saving is off, the first call above will return false for some timezones.
				$tz = timezone_name_from_abbr(null, ($this->request->data['offset'] * 60), false);
			}

			if ($tz) {
				$this->Admin->create();
				$this->Admin->save(array('Admin' => array(
					'id' => $this->current_user['Admin']['id'],
					'timezone' => $tz
				)), true, array('timezone'));
			}
		}

		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => '1',
				'timezone' => $tz
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	function update($user_id = null) {
		if (!$user_id) {
			throw new NotFoundException();
		}
				
		App::import('Model', 'QueryProfile');
		$this->QueryProfile = new QueryProfile;
		if ($this->request->is(array('POST', 'PUT'))) {
			$this->User->set($this->request->data);			
			$this->QueryProfile->set($this->request->data);
			if ($this->QueryProfile->validates() & $this->User->validates()) {
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $user_id
					),
					'fields' => array(
						'User.id', 'QueryProfile.country'
					)
				));
				
				$settings = $this->Setting->find('list', array(
					'fields' => array('Setting.name', 'Setting.value'),
					'conditions' => array(
						'Setting.name' => array('twilio.account_sid', 'twilio.auth_token', 'twilio.phone_number', 'twilio.verification_template', 'twilio.lookup.endpoint'),
						'Setting.deleted' => false
					)
				));
					
				$this->request->data['User']['phone_number'] = trim(preg_replace("/[^0-9+]+/", "", $this->request->data['User']['phone_number']));
				if (!empty($this->request->data['User']['phone_number'])) {
					if ($user['QueryProfile']['country'] == 'US') {
						$this->User->validate = array(
							'phone_number' => array(
								'phone' => array(
									'rule' => array('phone', null, 'us')
								)
							)
						);
					}	
					elseif ($user['QueryProfile']['country'] == 'CA') {
						$this->User->validate = array(
							'phone_number' => array(
								'phone' => array(
									'rule' => array('phone', null, 'ca')
								)
							)
						);
					}	
					elseif ($user['QueryProfile']['country'] == 'GB') {
						if (strpos($this->request->data['User']['phone_number'], '+44') === false) {
							$this->request->data['User']['phone_number'] = '+44' . $this->request->data['User']['phone_number'];
						}
						$this->User->validate = array(
							'phone_number' => array(
								'phone' => array(
									'rule' => array('phone', '^\(?(?:(?:0(?:0|11)\)?[\s-]?\(?|\+)44\)?[\s-]?\(?(?:0\)?[\s-]?\(?)?|0)(?:\d{2}\)?[\s-]?\d{4}[\s-]?\d{4}|\d{3}\)?[\s-]?\d{3}[\s-]?\d{3,4}|\d{4}\)?[\s-]?(?:\d{5}|\d{3}[\s-]?\d{3})|\d{5}\)?[\s-]?\d{4,5}|8(?:00[\s-]?11[\s-]?11|45[\s-]?46[\s-]?4\d))(?:(?:[\s-]?(?:x|ext\.?\s?|\#)\d+)?)$^')
								)
							)
						);
					}						
				
					$country_code = $user['QueryProfile']['country'];
					if ($user['QueryProfile']['country'] == 'GB') {
						$mobile_number = preg_replace('~.*(\d{2})[^\d]*(\d{4})[^\d]*(\d{4}).*~', '$1-$2-$3', $this->data['User']['phone_number']);
						$country_code = 'GB';
					}
					else {
						$mobile_number = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '$1-$2-$3', $this->data['User']['phone_number']);
					}
					$twilio_number = $this->TwilioNumber->find('first', array(
						'conditions' => array(
							'TwilioNumber.number' => $mobile_number
						)
					));
					if (!$twilio_number || ($twilio_number && $twilio_number['TwilioNumber']['modified'] <= date(DB_DATETIME, strtotime('-2 months')))) {
						$HttpSocket = new HttpSocket(array(
							'timeout' => 15,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						$HttpSocket->configAuth('Basic', $settings['twilio.account_sid'], $settings['twilio.auth_token']);
						$results = $HttpSocket->get($settings['twilio.lookup.endpoint'] . $mobile_number, array(
							'Type' => 'carrier', 
							'CountryCode' => $country_code
						));								
						if ($results->code == '200') {
							$carrier_data = json_decode($results->body, true);
							$caller_name = null;
							$caller_type = null;
							if (strtoupper($carrier_data['country_code']) == 'US') {
								$results = $HttpSocket->get($settings['twilio.lookup.endpoint'] . $mobile_number, array('Type' => 'caller-name', 'CountryCode' => $country_code));
								if ($results->code == 200) {											
									$caller_data = json_decode($results->body, true);
									$caller_name = !empty($caller_data['caller_name']['caller_name']) ? $caller_data['caller_name']['caller_name'] : null;
									$caller_type = !empty($caller_data['caller_name']['caller_type']) ? $caller_data['caller_name']['caller_type'] : null;
								}
							}									
							$twilio_number_to_save = array(
								'number' => $mobile_number,
								'mobile_country_code' => $carrier_data['carrier']['mobile_country_code'],
								'mobile_network_code' => $carrier_data['carrier']['mobile_network_code'],
								'name' => $carrier_data['carrier']['name'],
								'caller_name' => $caller_name,
								'caller_type' => $caller_type,
								'type' => $carrier_data['carrier']['type'],
								'national_format' => $carrier_data['national_format'],
								'phone_number' => $carrier_data['phone_number'],
								'country_code' => $carrier_data['country_code']
							);
							if ($twilio_number) {
								$twilio_number_to_save['id'] = $twilio_number['TwilioNumber']['id'];
							}
							
							$twilioNumberSource = $this->TwilioNumber->getDataSource();
							$twilioNumberSource->begin();
							$this->TwilioNumber->create();
							$this->TwilioNumber->save(array('TwilioNumber' => $twilio_number_to_save), true, array(array_keys($twilio_number_to_save)));
							$twilio_number = $this->TwilioNumber->find('first', array(
								'conditions' => array(
									'TwilioNumber.id' => $this->TwilioNumber->getInsertId()
								)
							));
							$twilioNumberSource->commit();
						}
					}
					$this->request->data['User']['twilio_number_id'] = $twilio_number ? $twilio_number['TwilioNumber']['id']: null; 
				
					// set verification flag
					if ($twilio_number) {
						$this->TwilioNumber->create();
						$this->TwilioNumber->save(array('TwilioNumber' => array(
							'id' => $twilio_number['TwilioNumber']['id'],
							'verified' => $this->request->data['User']['is_mobile_verified'],
							'modified' => false
						)), true, array('verified')); 
					}
				}
					
				$this->User->save($this->request->data, array('validate' => false, 'callbacks' => false, 'fieldList' => array('email', 'send_sms', 'twilio_number_id')));
				$this->QueryProfile->save($this->request->data, true, array('birthdate', 'gender'));
				$this->Session->setFlash(__('Users\' email, DOB & gender updated.'), 'flash_success');
					$this->redirect(array(
						'controller' => 'users',
						'action' => 'history',
						$user_id
					));
			}
			else {
				$this->Session->setFlash(__('There is some errors, Please try again.'), 'flash_error');
			}			
		}
		else {
			$this->request->data = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $user_id
				),
				'fields' => array(
					'User.id',
					'User.email',
					'User.twilio_number_id',
					'User.send_sms',
					'QueryProfile.id',
					'QueryProfile.gender',
					'QueryProfile.birthdate'
				)
			));
			$twilio_number = $this->TwilioNumber->find('first', array(
				'conditions' => array(
					'TwilioNumber.id' => $this->request->data['User']['twilio_number_id']
				)
			));
			if ($twilio_number) {
				$this->request->data['User']['phone_number'] = $twilio_number['TwilioNumber']['number']; 
				$this->request->data['User']['is_mobile_verified'] = $twilio_number['TwilioNumber']['verified'];				
			}
			$this->set(compact('twilio_number'));
		}
	}
	
	function mass_hellban() {
		if ($this->request->is(array('put', 'post'))) {
			if (!empty($this->request->data['User']['user_id'])) {
				if (empty($this->request->data['User']['action'])) {
					$user_ids = explode("\n", $this->request->data['User']['user_id']);
					$this->User->bindModel(array('hasOne' => array(
						'QueryProfile'
					)));
					$users = $this->User->find('all', array(
						'conditions' => array(
							'User.id' => $user_ids
						),
						'recursive' => -1,
						'contain' => array('QueryProfile', 'Referrer')
					));
					if (!$users) {
						$this->Session->setFlash('No matched record found.', 'flash_error');
						$this->redirect(array('controller' => 'users', 'action' => 'mass_hellban'));
					}
					$this->set(compact('users'));
				}
				else {
					$user_ids = $this->request->data['User']['user_id'];
					foreach ($user_ids as $user_id) {
						MintVineUser::hellban($user_id, $this->request->data['User']['reason'], $this->current_user['Admin']['id']);
					}
					$this->Session->setFlash('Users hellban successfully.', 'flash_success');
					$this->redirect(array('controller' => 'users', 'action' => 'index'));
				}
			}
		}
		$title_for_layout = 'Mass Hellban';
		$this->set(compact('title_for_layout'));
	}
	
	function ajax_remove_referrer($user_id = null) {
		if ($this->request->is('put') || $this->request->is('post')) {
			$user = $this->User->findById($this->request->data['id']);
				
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'referred_by' => false
			)), true, array('referred_by')); 
			
			$this->UserLog->create();
			$this->UserLog->save(array('UserLog' => array(
				'user_id' => $user['User']['id'],
				'type' => 'user.updated',
				'description' => 'referred_by updated from "' . $user['User']['referred_by'] . '" to 0'
			)));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => 'The referrer for this user has been successfully unset. You may close this window.'
				)), 
				'type' => 'json',
				'status' => '201'
			));	
		}
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'fields' => array('User.id'),
			'recursive' => -1
		));
		$this->set(compact('user'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	function merge() {
		if ($this->request->is(array('put', 'post'))) {
			$merged_id = $this->request->data['User']['merged_id'];
			$merging_id = $this->request->data['User']['merging_id'];
			if ($merging_id != $merged_id) {
				if ($merged_id{0} == '#' && $merging_id{0} == '#') {
					$merged_account = $this->User->find('first', array(
						'conditions' => array(
							'User.id' => substr($merged_id, 1)
						),
						'recursive' => -1,
						'fields' => array('id', 'twilio_number_id', 'fb_id', 'email')
					));
					
					$merging_account = $this->User->find('first', array(
						'conditions' => array(
							'User.id' => substr($merging_id, 1)
						),
						'recursive' => -1,
						'fields' => array('id', 'twilio_number_id', 'fb_id', 'email')
					));
					
					if (!$merged_account || !$merging_account) {
						$message = (!$merged_account && !$merging_account) ? 'User ids does not exists.' : ((!$merged_account) ? 'Merged account does not exists.' : 'Merging account does not exists.');
						$this->Session->setFlash(__($message), 'flash_error');	
					}
					else {						
						$merged_account_paypal = $this->PaymentMethod->find('first', array(
							'conditions' => array(
								'PaymentMethod.user_id' => $merged_account['User']['id'],
								'PaymentMethod.payment_method' => 'paypal',
								'PaymentMethod.confirmed !=' => null,
								'PaymentMethod.status' => 'active',
							),
							'order' => array('PaymentMethod.id' => 'DESC')
						));
						
						if (!$merged_account_paypal) {
							$merging_account_paypal = $this->PaymentMethod->find('first', array(
								'conditions' => array(
									'PaymentMethod.user_id' => $merging_account['User']['id'],
									'PaymentMethod.payment_method' => 'paypal',
									'PaymentMethod.confirmed !=' => null,
									'PaymentMethod.status' => 'active',
								),
								'order' => array('PaymentMethod.id' => 'DESC')
							));
							
							if ($merging_account_paypal) {
								$this->PaymentMethod->create();
								$this->PaymentMethod->save(array('PaymentMethod' => array(
									'id' => $merging_account_paypal['PaymentMethod']['id'],
									'user_id' => $merged_account['User']['id'],
									'modified' => false									
								)), array(
									'fieldList' => array('user_id'),
									'callbacks' => false,
									'validate' => false
								));
							}
						}
						$fb_id = $merged_account['User']['fb_id'];
						if (empty($merged_account['User']['fb_id']) && !empty($merging_account['User']['fb_id'])) {
							$fb_id = $merging_account['User']['fb_id'];
						}
						
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $merging_account['User']['id'],
							'password' => null,
							'pass' => null,							
							'deleted' => false,
							'fb_id' => $fb_id,
							'modified' => false
						)), array(
							'fieldList' => array('password', 'fb_id', 'pass', 'deleted'),
							'callbacks' => false,
							'validate' => false
						));
						
						// Checking email already exists or not in BlockedEmail
						$blocked_email = $this->BlockedEmail->find('first', array(
							'conditions' => array(
								'BlockedEmail.email' => $merging_account['User']['email']
							),
							'recursive' => -1
						));
						
						// Adding email into BlockedEmail if not exists to prevent account creation with this email
						if (!$blocked_email) {
							$this->BlockedEmail->create();
							$this->BlockedEmail->save(array('BlockedEmail' => array(
								'email' => $merging_account['User']['email']
							)));
						}
						
						$merged_account_phone = $this->TwilioNumber->find('first', array(
							'conditions' => array(
								'TwilioNumber.id' => $merged_account['User']['twilio_number_id']
							)
						));
						
						$merging_account_phone = $this->TwilioNumber->find('first', array(
							'conditions' => array(
								'TwilioNumber.id' => $merging_account['User']['twilio_number_id']
							)
						));
						// Updating merged account mobile number if not verified but verified in merging account
						if ($merged_account_phone && $merged_account_phone['TwilioNumber']['verified'] == false && $merging_account_phone && $merging_account_phone['TwilioNumber']['verified'] == true) {
							$this->User->create();
							$this->User->save(array('User' => array(
								'id' => $merged_account['User']['id'],
								'twilio_number_id' => $merging_account_phone['TwilioNumber']['id']
							)), array(
								'fieldList' => array('twilio_number_id'),
								'callbacks' => false,
								'validate' => false
							));
						}
						// Updating mobile number based on admin preference if both merging and merged account mobile numbers verified
						elseif (!empty($this->request->data['User']['phone_number'])) {
							$this->User->create();
							$this->User->save(array('User' => array(
								'id' => $merged_account['User']['id'],								
								'twilio_number_id' => $this->request->data['User']['phone_number']
							)), array(
								'fieldList' => array('twilio_number_id'),
								'callbacks' => false,
								'validate' => false
							));
						}					
						
						$transactions = $this->Transaction->find('all', array(
							'fields' => array('Transaction.id'),
							'conditions' => array(
								'Transaction.type_id' => array(
									TRANSACTION_OFFER,
									TRANSACTION_REFERRAL,
									TRANSACTION_WITHDRAWAL,
									TRANSACTION_DWOLLA,
									TRANSACTION_CHALLENGE,
									TRANSACTION_POLL_STREAK,
									TRANSACTION_SURVEY_NQ,
									TRANSACTION_SURVEY,
									TRANSACTION_POLL,
									TRANSACTION_GROUPON
								),
								'Transaction.user_id' => $merging_account['User']['id'],
								'Transaction.deleted' => null,
							),
							'recursive' => -1
						));
						
						if ($transactions) {
							foreach ($transactions as $transaction) {
								$this->Transaction->create();
								$this->Transaction->save(array('Transaction' => array(
									'id' => $transaction['Transaction']['id'],
									'user_id' => $merged_account['User']['id'],									
									'modified' => false
								)), array(
									'fieldList' => array('user_id'),
									'callbacks' => false,
									'validate' => false
								));
							}
						}
						$this->User->rebuildBalances($merged_account);
						
						$this->User->delete($merging_account['User']['id']);
						
						$this->redirect(array(
							'controller' => 'users',
							'action' => 'history',
							$merged_account['User']['id'],
						));
					}
				}
				else {
					$this->Session->setFlash(__('Please enter valid user ids.'), 'flash_error');				
				}
			}
			else {
				$this->Session->setFlash(__('Merged account and Merging account can not be same.'), 'flash_error');			
			}
		}
	}
	
	function check_phone_numbers() {
		if ($this->request->is(array('put', 'post'))) {
			$merged_id = $this->request->data['User']['merged_id'];
			$merging_id = $this->request->data['User']['merging_id'];
			$phone_numbers = $this->User->find('all', array(
				'conditions' => array(
					'User.id' => array(substr($merged_id, 1), substr($merging_id, 1))
				),
				'recursive' => -1,
				'fields' => array('id', 'twilio_number_id')
			));
			
			if (count($phone_numbers) < 2) {
				
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => true
					)),
					'type' => 'json',
					'status' => '201'
				));
			}
			
			foreach ($phone_numbers as $phone_number) {
				$twilio_number = $this->TwilioNumber->find('first', array(
					'conditions' => array(
						'TwilioNumber.id' => $phone_number['User']['twilio_number_id'],
						'TwilioNumber.verified' => true
					)
				));
				if ($twilio_number) {
					$mobile_numbers[$phone_number['User']['twilio_number_id']] = $twilio_number['TwilioNumber']['national_format'];
				}
			}
			
			$this->set(compact('mobile_numbers'));
			
		}
	}
	
	function ajax_rebuild_balance($user_id = null) {
		if ($this->request->is(array('put', 'post'))) {
			$user = $this->User->findById($this->request->data['id']);
			if ($user) {
				CakeLog::write('users.rebuild.balances', '#'.$user['User']['id'] . ' stored balance was ' . $user['User']['balance']);
				$this->User->rebuildBalances($user);
				return new CakeResponse(array(
					'body' => json_encode(array(
						'message' => 'The balance for this user has been successfully updated. You may close this window.'
					)), 
					'type' => 'json',
					'status' => '201'
				));
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => 'User not found.'
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'recursive' => -1,
			'fields' => array('User.id', 'User.balance', 'User.pending', 'User.total')
		));
		
		$this->set(compact('user'));
	}

	function survey_quality($user_id = null) {
		$this->User->bindModel(array('hasOne' => array(
			'QueryProfile' => array(
				'foreignKey' => 'user_id'
			)
		)));
		$this->User->bindModel(array('hasMany' => array('HellbanLog' => array(
			'fields' => array('HellbanLog.automated'),
			'conditions' => array(
				'HellbanLog.type' => 'unhellban'
			),
			'order' => 'HellbanLog.id DESC'
		))));
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			)
		));
		if (empty($user)) {
			throw new NotFoundException();
		}
		
		$user_analysis = $this->UserAnalysis->find('first', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user_id,
			),
			'order' => 'UserAnalysis.id DESC'
		));
	
		$user_surveys = array();
		if (!empty($user['User']['verified'])) {
			$user_surveys = $this->SurveyUserVisit->find('all', array(
				'conditions' => array(
					'SurveyUserVisit.user_id' => $user_id,
					'SurveyUserVisit.created >=' => $user['User']['verified']
				),
				'fields' => array('*'),
				'order' => 'SurveyUserVisit.id ASC',
				'limit' => 20,
				'contain' => array(
					'Project' => array(
						'SurveyVisitCache'
					)
				)
			));
		}
		
		$this->set(compact('user_surveys', 'user', 'user_analysis'));
	}
	
	function unique_users() {
		$this->UniqueUser->virtualFields = array(
			'linked_users' => 'COUNT(UniqueUser.unique_id)'
		);
		
		$this->paginate = array(
			'UniqueUser' => array(
				'contain' => array('UniqueUser' => array('User')),
				'order' => 'UniqueUser.linked_users DESC',
				'group' => array('UniqueUser.unique_id'),
				'limit' => 100
			)
		);
		$unique_users = $this->paginate('UniqueUser');
		$this->set(compact('unique_users'));
	}
	
	function linked_users($unique_id = null) {
		if (empty($unique_id) && !isset($this->request->query['user_id'])) {
			throw new NotFoundException();
		}
		
		if ($this->request->is('put') || $this->request->is('post')) {
			if (isset($this->request->data['LinkedUser']) && !empty($this->request->data['LinkedUser'])) {
				$i = 0;
				foreach ($this->request->data['LinkedUser'] as $hellban_user_id => $value) {
					if ($hellban_user_id == 'null') {
						continue;
					}
					$user = $this->User->find('first', array(
						'conditions' => array(
							'User.hellbanned' => 0,
							'User.id' => $hellban_user_id,
						),
						'recursive' => -1,
						'fields' => array('hellbanned')
					));
					if ($user) {
						MintVineUser::hellban($hellban_user_id, 'Manual hellban from unique users view', $this->current_user['Admin']['id']);
						$i++;
					}
				}
				$this->Session->setFlash($i.' users have been hellbanned.', 'flash_success');
			}
		}
		
		if (isset($this->request->query['user_id'])) {
			$user_id = $this->request->query['user_id'];
			$this->User->bindModel(array('hasMany' => array('HellbanLog' => array(
				'fields' => array('HellbanLog.automated'),
				'conditions' => array(
					'HellbanLog.type' => 'unhellban'
				),
				'order' => 'HellbanLog.id DESC'
			))));
			$user = $this->User->findById($user_id);
			if (empty($user)) {
				throw new NotFoundException();
			}
			
			// get unique ids linked to this user
			$unique_ids = $this->UniqueUser->find('list', array(
				'conditions' => array(
					'UniqueUser.user_id' => $user_id
				),
				'fields' => array('UniqueUser.unique_id', 'UniqueUser.unique_id')
			));
			$total_linked_browsers = count($unique_ids);
			$conditions = array(
				'UniqueUser.unique_id' => $unique_ids,
				'UniqueUser.user_id <>' => $user_id
			);
			
			// get user info
			if ($user['User']['twilio_number_id'] > 0) {
				$twilio_number = $this->TwilioNumber->find('first', array(
					'conditions' => array(
						'TwilioNumber.id' => $user['User']['twilio_number_id']
					),
					'recursive' => -1
				));
				$this->set(compact('twilio_number'));
			}
			$this->loadModel('UserAnalysis');
			$user_analysis = $this->UserAnalysis->find('first', array(
				'conditions' => array(
					'UserAnalysis.user_id' => $user_id
				),
				'order' => 'UserAnalysis.id DESC'
			));
			$this->set(compact('user', 'user_analysis', 'total_linked_browsers'));
		}
		else {
			$conditions = array('UniqueUser.unique_id' => $unique_id);
		}
		
		$this->UniqueUser->bindModel(array('belongsTo' => array(
			'User' => array(
				'foreignKey' => 'user_id'
			)
		)));
		$unique_users = $this->UniqueUser->find('all', array(
			'conditions' => $conditions,
			'order' => 'UniqueUser.unique_id, User.email ASC'
		));
		$total_linked_users = count($unique_users);
		
		$linked_users = array();
		if (!empty($unique_users)) {
			foreach ($unique_users as $unique_user) {
				$linked_users[$unique_user['UniqueUser']['unique_id']]['UniqueUser'] = $unique_user['User']['UniqueUser'] = $unique_user['UniqueUser'];
				$linked_users[$unique_user['UniqueUser']['unique_id']]['User'][] = $unique_user['User'];
			}
		}
		
		$this->set(compact('linked_users', 'total_linked_users'));
	}
	
	public function ajax_check_user_report($report_id) {
		App::import('Model', 'UserReport');
		$this->UserReport = new UserReport;
		$report = $this->UserReport->findById($report_id);
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['UserReport']['status'],
				'file' => Router::url(array('controller' => 'users', 'action' => 'download_user_report', $report['UserReport']['id']))
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function download_user_report($report_id) {
		if (empty($report_id)) {
			throw new NotFoundException();
		}
		App::import('Model', 'UserReport');
		$this->UserReport = new UserReport;
		$report = $this->UserReport->find('first', array(
			'conditions' => array(
				'UserReport.id' => $report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));

		if ($report) {
			if ($report['UserReport']['status'] == 'complete') {
				$settings = $this->Setting->find('list', array(
					'fields' => array('name', 'value'),
					'conditions' => array(
						'Setting.name' => array(
							's3.access',
							's3.secret',
							's3.bucket',
							's3.host'
						),
						'Setting.deleted' => false
					)
				));

				CakePlugin::load('Uploader');
				App::import('Vendor', 'Uploader.S3');

				$file = $report['UserReport']['path'];

				// we store with first slash; but remove it for S3
				if (substr($file, 0, 1) == '/') {
					$file = substr($file, 1, strlen($file));
				}

				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);

				$this->redirect($url);
			}
			else {
				$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
				$this->redirect(array(
					'controller' => 'reports',
					'action' => 'terming_actions'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}

	private function __bind_user_analysis($user) {
		$user_analysis = $this->UserAnalysis->find('first', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $user['User']['id'],
			),
			'order' => 'UserAnalysis.id DESC',
			'recursive' => -1,
		));
		if (!empty($user_analysis)) {
			$user['UserAnalysis'] = $user_analysis['UserAnalysis'];
		}
		else {
			$user['UserAnalysis'] = array();
		}
		return $user;
	}
}