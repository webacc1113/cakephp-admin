<?php
App::uses('AppController', 'Controller');

class Points2shopLogsController extends AppController {
	public $uses = array('Points2shopLog', 'Points2shopPanelistsLog');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$conditions = array();
		if (isset($this->request->query['country'])) {
			$conditions['Points2shopLog.country'] = $this->request->query['country'];
		}

		if (isset($this->request->query['project_id'])) {
			$conditions['Points2shopLog.p2s_project_id'] = $this->request->query['project_id'];
		}

		$paginate = array(
			'Points2shopLog' => array(
				'limit' => 50,
				'order' => 'Points2shopLog.id DESC',
				'conditions' => $conditions,
			)
		);
		$this->paginate = $paginate;
		$points2shop_logs = $this->paginate();
		$this->set(compact('points2shop_logs'));
	}

	public function search() {
		if (isset($this->request->query['project_id'])) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array(
						'points2shop.secret',
						'points2shop.host',
					),
					'Setting.deleted' => false
				),
				'recursive' => -1
			));

			$http = new HttpSocket(array(
				'timeout' => 30,
				'ssl_verify_host' => false
			));
			$params = array(
				'project_id' => $this->request->query['project_id']
			);
			$header = array(
				'header' => array(
					'X-YourSurveys-Api-Key' => $settings['points2shop.secret']
				),
			);
			$response = $http->get($settings['points2shop.host'] . '/suppliers_api/surveys', $params, $header);
			$response = json_decode($response, true);

			if (!empty($response['surveys'][0])) {
				$this->set('points2shop_project', $response['surveys'][0]);
			}
			else {
				$this->Session->setFlash('No project available', 'flash_error');
			}
		}
	}

	function qe_logs() {
		$conditions = array();
		if (isset($this->request->query['project_id'])) {
			$conditions['Points2shopPanelistsLog.project_id'] = $this->request->query['project_id'];
		}
		$paginate = array(
			'Points2shopPanelistsLog' => array(
				'conditions' => $conditions,
				'limit' => 50,
				'order' => 'Points2shopPanelistsLog.id DESC'
			)
		);
		$this->paginate = $paginate;
		$qe_logs = $this->paginate('Points2shopPanelistsLog');
		$this->set(compact('qe_logs'));
	}

	function qe_log($qe_log_id) {
		$qe_log = $this->Points2shopPanelistsLog->find('first', array(
			'conditions' => array(
				'Points2shopPanelistsLog.id' => $qe_log_id
			),
			'recursive' => -1,
		));
		$this->set(compact('qe_log'));
	}
}