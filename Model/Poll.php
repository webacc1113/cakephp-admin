<?php
App::uses('AppModel', 'Model');

class Poll extends AppModel {
	public $actsAs = array('Containable');
	    
    public $hasMany = array(
        'PollAnswer' => array(
            'className' => 'PollAnswer',
            'foreignKey' => 'poll_id',
        )
    );
    
    public $validate = array(
        'poll_question' => array(
            'rule' => 'notEmpty'
        ),
        'award' => array(
            'rule' => 'notEmpty'
        ),
        'answers' => array(
            'rule' => 'notEmpty'
        ),
        'publish_date' => array(
        	'rule' => 'isUnique',
        	'message' => 'Daily Polls must be set on unique dates'
        ),
    );
    
    public function beforeDelete($cascade = true) {
    	$models = array('PollUserAnswer', 'PollAnswer');
		foreach ($models as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
    	$poll_answers = $this->PollAnswer->find('all', array(
			'recursive' => -1,
			'fields' => array('PollAnswer.id'),
			'conditions' => array(
				'PollAnswer.poll_id' => $this->id
			),
		));
		if ($poll_answers) {
			foreach ($poll_answers as $poll_answer) {
				$this->PollAnswer->delete($poll_answer['PollAnswer']['id']);	
			}
		}
		
		$poll_user_answers = $this->PollUserAnswer->find('all', array(
			'recursive' => -1,
			'fields' => array('PollUserAnswer.id'),
			'conditions' => array(
				'PollUserAnswer.poll_id' => $this->id
			),
		));
		if ($poll_user_answers) {
			foreach ($poll_user_answers as $poll_user_answer) {
				$this->PollUserAnswer->delete($poll_user_answer['PollUserAnswer']['id']);	
			}
		}
    	return true;
    }
}