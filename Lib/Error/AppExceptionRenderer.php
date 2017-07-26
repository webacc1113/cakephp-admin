<?php
App::uses('ExceptionRenderer', 'Error');

class AppExceptionRenderer extends ExceptionRenderer {

	public function notFound($error) {
		$this->controller->render('/Errors/error404');
		$this->controller->response->send();
	}

	public function badRequest($error) {
		$this->controller->render('/Errors/error400');
		$this->controller->response->send();
	}
	
	public function forbidden($error) {
		$this->controller->render('/Errors/error403');
		$this->controller->response->send();
	}
}