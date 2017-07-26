<?php
App::uses('AppController', 'Controller');

class ProjectInvitesController extends AppController {
	public $uses = array('ProjectInviteReport');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();

		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.Uploader');
	}

	public function report() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		$date_from = '';
		$date_to = '';

		if (isset($this->data) && !empty($this->data)) {
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_to']));
				}
				else {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_from']));
				}
			}
		}
		else { // Get the last 7 days' data as default
			$date_from = date(DB_DATE, strtotime("7 days ago"));
			$date_to = date(DB_DATE, strtotime("1 day ago"));
			$this->data = array(
				'date_from' => date('m/d/Y', strtotime($date_from)),
				'date_to' => date('m/d/Y', strtotime($date_to))
			);
		}

		$paginate = array(
			'ProjectInviteReport' => array(
				'conditions' => array(
					'ProjectInviteReport.date >=' => $date_from,
					'ProjectInviteReport.date <=' => $date_to
				),
				'order' => 'ProjectInviteReport.date DESC',
				'limit' => 50
			)
		);
		$this->paginate = $paginate;
		$reports = $this->paginate();
		$title_for_layout = 'Project Invites';
		$this->set(compact('reports', 'title_for_layout'));
	}
}