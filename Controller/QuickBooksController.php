<?php
App::uses('AppController', 'Controller');

class QuickBooksController extends AppController {
	var $name = 'QuickBooks';
	var $uses = array('Admin', 'Site');
	
	function beforeFilter() {
		parent::beforeFilter();
	}
	
	function request_oauth_token() {
		$site = $this->Site->find('first', array(
			'conditions' => array(
				'Site.path_name' => QUICKBOOK_API_PATH_NAME
			)
		));
		if ($site) {
			if (!empty($this->params->query['oauth_token'])) {	
				$access_tokens = $this->get_access_token($site['Site']['api_key'], $site['Site']['api_secret'], $this->params->query['oauth_token'], $this->Session->read('QuickBook.secret'));
				$this->params->query['oauth_token_secret'] = $access_tokens['oauth_token_secret'];				
				$this->params->query['oauth_token'] = $access_tokens['oauth_token'];				
				$this->Site->save(array(
					'Site' => array(
						'oauth_tokens' => json_encode($this->params->query),
						'id' => $site['Site']['id']
					)
				), true, array(
						'oauth_tokens'
					)
				);
				$this->Session->delete('QuickBook');
				echo '<script type="text/javascript">window.opener.location.reload();window.close();</script>';
				die;
			}
			else {
				try {
					// App::import('Vendor', 'OAuthClient', array('file' => 'OAuth/OAuthClient.php'));
					$oauth = new OAuth($site['Site']['api_key'], $site['Site']['api_secret']);
					$oauth->disableSSLChecks();
					$get_request_token = $oauth->getRequestToken(QUICKBOOK_OAUTH_REQUEST_URL, Router::url(array('controller' => 'quick_books', 'action' => 'request_oauth_token'), true) );
					$this->Session->write('QuickBook.secret' , $get_request_token['oauth_token_secret']);
					$this->Session->write('QuickBook.key' , $get_request_token['oauth_token']);
					$this->redirect(QUICKBOOK_OAUTH_AUTHORISE_URL .'?oauth_token='.$get_request_token['oauth_token']);
				}
				catch (Exception $e) {
				}
			}
		}
		else {
			$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
		}
	}
	
	function get_access_token($consumer_key, $consumer_secret, $request_token, $request_token_secret) {
		$oauth = new OAuth( $consumer_key, $consumer_secret);
		$oauth->setToken( $request_token, $request_token_secret);
		$oauth->disableSSLChecks();
		// $tokens = (object)array('key' => $request_token, 'secret' => $request_token_secret);		
		return $get_access_token = $oauth->getAccessToken(QUICKBOOK_OAUTH_ACCESS_URL);
	}
}