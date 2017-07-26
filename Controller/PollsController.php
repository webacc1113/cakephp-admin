<?php
App::uses('AppController', 'Controller');

class PollsController extends AppController {
	public $uses = array('Poll', 'PollUserAnswer','PollAnswer','QueryProfile');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();

		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.Uploader');
	}
	
	public function index() {
		$limit = 50;
		
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		if ($this->request->is('post') && isset($this->data['Poll']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['Poll'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->Poll->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Polls' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$this->Poll->bindModel(array('hasOne' => array(
			'Report' => array(
				'fields' => array('Report.id', 'Report.status'),
			)
		)));
		$paginate = array(
			'Poll' => array(
				'limit' => $limit,
				'order' => 'Poll.publish_date DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Poll']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$polls = $this->paginate();
		$this->set('polls', $polls);
	}
	
	public function add() {
		if ($this->request->is('post')) {
			$this->Poll->create();
			$poll = array('Poll' => array(
				'poll_question' => $this->request->data['Poll']['poll_question'],
				'award' => $this->request->data['Poll']['award'],
				'publish_date' => date(DB_DATE, strtotime($this->request->data['Poll']['publish_date'])),
			));
			$save = $this->Poll->save($poll); 
            if ($save) {
            	if ($this->request->data['Poll']['answers']) {
					$answers = $this->request->data['Poll']['answers'];
					if (strpos($answers, "\r\n") !== false) {
						$answers = str_replace("\r\n", '|', $answers);
					} 
					elseif (strpos($answers, "\r") !== false) {
        				$answers = str_replace("\r", '|', $answers);
        			} 
					elseif (strpos($answers, "\n") !== false) {
       					$answers = str_replace("\n", '|', $answers);
       				}
       				$answer_arr = explode('|', $answers);
					foreach ($answer_arr as $answer) {
						$answer = trim($answer);
						if (empty($answer)) {
							continue;
						}
						$this->Poll->PollAnswer->create();
						$this->Poll->PollAnswer->save(array('PollAnswer' => array(
							'poll_id' => $this->Poll->id,
							'answer' => $answer,
						)));
					}
				}
                $this->Session->setFlash(__('Poll has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash(__('Unable to add the poll.'), 'flash_error');
        }
	}
	
	public function edit($id) {
		$poll = $this->Poll->findById($id);
    	if (!$poll) {
        	throw new NotFoundException(__('Invalid poll'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
        	$poll = array('Poll' => array(
        		'id' => $id,
       			'poll_question' => $this->request->data['Poll']['poll_question'],
       			'award' => $this->request->data['Poll']['award'],
				'publish_date' => date(DB_DATE, strtotime($this->request->data['Poll']['publish_date'])),
			));
        	if ($this->Poll->save($poll)) {
        		if ($this->request->data['Poll']['answers']) {
					$answers = $this->request->data['Poll']['answers'];
					if (strpos($answers, "\r\n") !== false) {
						$answers = str_replace("\r\n", '|', $answers);
					} 
					elseif (strpos($answers, "\r") !== false) {
        				$answers = str_replace("\r", '|', $answers);
        			} 
					elseif (strpos($answers, "\n") !== false) {
       					$answers = str_replace("\n", '|', $answers);
       				}
       				$answer_arr = explode('|', $answers);
					$poll_answers = $this->Poll->PollAnswer->find('all', array(
						'recursive' => -1,
						'conditions' => array(
							'PollAnswer.poll_id' => $id
						),
						'fields' => array('PollAnswer.id')
					));
					if ($poll_answers) {
						foreach ($poll_answers as $poll_answer) {
							$this->Poll->PollAnswer->delete($poll_answer['PollAnswer']['id'], false); 
						}
					}
					foreach ($answer_arr as $answer) {
						$this->Poll->PollAnswer->create();
						$this->Poll->PollAnswer->save(array('PollAnswer' => array(
							'poll_id' => $id,
							'answer' => $answer,
						)));
					}
				}		
        	    $this->Session->setFlash(__('Poll has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the poll.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $poll;
    	}
	}
	
	public function results($poll_id) {
		App::import('Vendor', 'SiteProfile');
		$this->layout = 'poll';
		$poll = $this->Poll->find('first', array(
			'conditions' => array(
				'Poll.id' => $poll_id
			)
		));
		$answer_count = array();
		$user_answers = $this->PollUserAnswer->find('all', array(
			'conditions' => array(
				'PollUserAnswer.poll_id' => $poll_id
			),
			'fields' => array('PollUserAnswer.*', 'QueryProfile.gender', 'QueryProfile.hhi', 'QueryProfile.education','QueryProfile.birthdate'),
			'joins' => array(
				array(
					'alias' => 'QueryProfile',
					'table' => 'query_profiles',
					'conditions' => array(
						'QueryProfile.user_id = PollUserAnswer.user_id'
					)
				)
			)
		));
		$poll_answers = $this->PollAnswer->find('list', array(
			'fields' => array('id', 'answer'),
			'conditions' => array(
				'PollAnswer.poll_id' => $poll_id
			)
		));
		$gender_data = $hhi_data = $overall_age_data = $age_data = array();
		// note that the keys contain all the possible values - do not hardcode values from HHI and such
		foreach ($user_answers as $user_answer) {
			//answers
			if (!isset($answer_dates[$user_answer['PollUserAnswer']['answer_id']])) {
				$answer_dates[$user_answer['PollUserAnswer']['answer_id']] = 1;
			}
			else {
				$answer_dates[$user_answer['PollUserAnswer']['answer_id']]++;
			}
			
			// store gender data for this result set
			if (!isset($gender_data[$user_answer['PollUserAnswer']['answer_id']][$user_answer['QueryProfile']['gender']])) {
				$gender_data[$user_answer['PollUserAnswer']['answer_id']][$user_answer['QueryProfile']['gender']] = 1;
			}
			else {
				$gender_data[$user_answer['PollUserAnswer']['answer_id']][$user_answer['QueryProfile']['gender']]++;
			}
			//hhi
			if (!isset($hhi_data[$user_answer['PollUserAnswer']['answer_id']][$user_answer['QueryProfile']['hhi']])) {
				$hhi_data[$user_answer['PollUserAnswer']['answer_id']][$user_answer['QueryProfile']['hhi']] = 1;
			}
			else {
				$hhi_data[$user_answer['PollUserAnswer']['answer_id']][$user_answer['QueryProfile']['hhi']] ++;
			}
			
			//ages
			$ages = array('14', '19', '24', '34', '44', '54', '64');
			$birthdate = strtotime($user_answer['QueryProfile']['birthdate']);
			foreach ($ages as $key => $age) {
				$initial = strtotime("-$age years");
				if (isset($ages[$key + 1])) {
					$end = strtotime("-".$ages[$key + 1]." years");
				}
				else {
					// for the last data set set a high number.
					// note there will never be people < 14 years old
					$end = strtotime("-200 years");
				}
				// find the matching range
				if ($birthdate <= $initial && $birthdate > $end) {		
					if (!isset($overall_age_data[$key])) {
						$overall_age_data[$key] = 1;
					}
					else {
						$overall_age_data[$key]++;
					}
					if (!isset($age_data[$user_answer['PollUserAnswer']['answer_id']][$key])) {
						$age_data[$user_answer['PollUserAnswer']['answer_id']][$key] = 1;
					}
					else {
						$age_data[$user_answer['PollUserAnswer']['answer_id']][$key]++;
					}
				}
			}
		}
		foreach ($answer_dates as $key => $answer_date) {
			if (isset($poll_answers[$key])) {
				$answer_count[$poll_answers[$key]] = $answer_date;
			}
		}
		
		$this->set(compact('poll', 'user_answers', 'gender_data', 'poll_answers', 'hhi_data', 'age_data', 'overall_age_data', 'answer_count','poll_answers'));
	}
	
	public function push_to_facebook() {
		App::import('Vendor', 'facebook');
		$this->Facebook = new Facebook(array(
			'appId' => FB_APP_ID,
			'secret' => FB_APP_SECRET
		));

		if ($this->Facebook->getUser()) {
			$fb_user = $this->Facebook->api('/me');
			//Get Page access token
			//To generate a page access token, an admin of the page must grant an extended permission called manage_pages
			$response = $this->Facebook->api($fb_user['id'] . '/accounts');
			foreach ($response['data'] as $item) {
				if ($item['id'] == MV_PAGE_ID) {
					$page_access_token = $item['access_token'];
					break;
				}
			}

			$this->Facebook->setAccessToken($page_access_token);
			//Test settings, will eventually be dynamic for the selected poll.
			$params = array(
				'message' => 'this is my message',
				'name' => 'This is my demo Facebook application!',
				'caption' => "MintVine Poll",
				'link' => HOSTNAME_WWW.'/users/dashboard',
				'description' => 'this is a description',
				'picture' => 'https://mintvine.com/img/MV-brandmark.png',
				'actions' => array(
					array(
						'name' => 'Go to poll',
						'link' => HOSTNAME_WWW.'/users/dashboard'
					)
				)
			);
			$response = $this->Facebook->api(MV_PAGE_ID . '/feed', 'POST', $params);
			if (isset($response['id'])) {
				$this->Session->setFlash('Post successfully made.', 'success');
				$this->redirect(array('action' => 'index'));
			}
		}
	}

}