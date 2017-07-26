<?php
App::uses('Component', 'Controller');

class StackifyComponent extends Component {
	public $stackify_key = '';
	public $app_name = '';
	public $environment_name = '';
	public $logger;

	function __construct() {
		$this->stackify_key = STACKIFY_KEY;
		$this->app_name = STACKIFY_APP_NAME;
		$this->environment_name = STACKIFY_ENVIRONMENT_NAME;
		
		App::import('Vendor', 'CurlTransport2', array(
			'file' => 'Stackify' . DS . 'Log' . DS . 'Transport' . DS . 'CurlTransport.php'
		));
		
		App::import('Vendor', 'HandlerInterface ', array(
			'file' => 'Stackify' . DS . 'Log' . DS . 'Monolog' . DS . 'Handler' . DS . 'HandlerInterface.php'
		));
		
		App::import('Vendor', 'AbstractHandler ', array(
			'file' => 'Stackify' . DS . 'Log' . DS . 'Monolog' . DS . 'Handler' . DS . 'AbstractHandler.php'
		));
		
		App::import('Vendor', 'Handler ', array(
			'file' => 'Stackify' . DS . 'Log' . DS . 'Monolog' . DS . 'Handler.php'
		));
		
		App::import('Vendor', 'LoggerInterface ', array(
			'file' => 'Stackify' . DS . 'Psr' . DS . 'Log' . DS . 'LoggerInterface.php'
		));
		
		App::import('Vendor', 'Logger ', array(
			'file' => 'Stackify' . DS . 'Log' . DS . 'Monolog' . DS . 'Logger.php'
		));
	}
	
	function write_log($type, $message) {
		$env_name = ucfirst($type);
		
		$transport = new \Stackify\Log\Transport\CurlTransport($this->stackify_key);
		$handler = new \Stackify\Log\Monolog\Handler($this->app_name, $env_name, $transport);
		$this->logger = new \Monolog\Logger('logger');
		$this->logger->pushHandler($handler);
		
		return $this->logger->addDebug($env_name, array('message' => $message));
	}
	
	function write_error($title, $message) {
		$transport = new \Stackify\Log\Transport\CurlTransport($this->stackify_key);
		$handler = new \Stackify\Log\Monolog\Handler($this->app_name, $this->environment_name, $transport);
		$this->logger = new \Monolog\Logger('logger');
		$this->logger->pushHandler($handler);
		
		return $this->logger->addError($title, array('ex' => $message));
	}
	
	function write_exception($title, Exception $ex) {
		$transport = new \Stackify\Log\Transport\CurlTransport($this->stackify_key);
		$handler = new \Stackify\Log\Monolog\Handler($this->app_name, $this->environment_name, $transport);
		$this->logger = new \Monolog\Logger('logger');
		$this->logger->pushHandler($handler);
		
		return $this->logger->addError($title, array('ex' => $ex));
	}
}