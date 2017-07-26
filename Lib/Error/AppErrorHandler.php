<?php
/**
 * AppErrorHandler class
 *
 * Provides Error Capturing for Framework errors.
 */

App::uses('Debugger', 'Utility');
App::uses('CakeLog', 'Log');
App::uses('ExceptionRenderer', 'Error');
App::uses('ErrorHandler', 'Error');
App::uses('Router', 'Routing');
App::uses('StackifyComponent', 'Controller/Component');

class AppErrorHandler extends ErrorHandler {

	public static function handleException(Exception $exception) {
		$config = Configure::read('Exception');
		self::_log($exception, $config);

		$renderer = isset($config['renderer']) ? $config['renderer'] : 'ExceptionRenderer';
		if ($renderer !== 'ExceptionRenderer') {
			list($plugin, $renderer) = pluginSplit($renderer, true);
			App::uses($renderer, $plugin . 'Error');
		}
		try {
			$error = new $renderer($exception);
			$error->render();
		} catch (Exception $e) {
			set_error_handler(Configure::read('Error.handler')); // Should be using configured AppErrorHandler
			Configure::write('Error.trace', false); // trace is useless here since it's internal
			$message = sprintf("[%s] %s\n%s", // Keeping same message format
				get_class($e),
				$e->getMessage(),
				$e->getTraceAsString()
			);
			trigger_error($message, E_USER_ERROR);
		}
	}

	/**
	 * Handles exception logging
	 *
	 * @param Exception $exception The exception to render.
	 * @param array $config An array of configuration for logging.
	 * @return bool
	 */
	protected static function _log(Exception $exception, $config) {
		if (empty($config['log'])) {
			return false;
		}

		if (!empty($config['skipLog'])) {
			foreach ((array)$config['skipLog'] as $class) {
				if ($exception instanceof $class) {
					return false;
				}
			}
		}
		
		//Sending log to stackify
		App::uses('StackifyComponent', 'Controller/Component');
		$Stackify = new StackifyComponent();
		$Stackify->write_exception($exception->getMessage(), $exception);
		return $Stackify->write_exception($exception->getMessage(), $exception);
		//return CakeLog::write(LOG_ERR, self::_getMessage($exception));
		
		return CakeLog::write(LOG_ERR, self::_getMessage($exception));
	}

	public static function handleError($code, $description, $file = null, $line = null, $context = null) {
		if (error_reporting() === 0) {
			return false;
		}
		$errorConfig = Configure::read('Error');
		list($error, $log) = self::mapErrorCode($code);
		if ($log === LOG_ERR) {
			return self::handleFatalError($code, $description, $file, $line);
		}

		$debug = Configure::read('debug');
		if ($debug) {
			$data = array(
				'level' => $log,
				'code' => $code,
				'error' => $error,
				'description' => $description,
				'file' => $file,
				'line' => $line,
				'context' => $context,
				'start' => 2,
				'path' => Debugger::trimPath($file)
			);
			return Debugger::getInstance()->outputError($data);
		}
		$message = $error . ' (' . $code . '): ' . $description . ' in [' . $file . ', line ' . $line . ']';
		if (!empty($errorConfig['trace'])) {
			$trace = Debugger::trace(array('start' => 1, 'format' => 'log'));
			$message .= "\nTrace:\n" . $trace . "\n";
		}
		
		$Stackify = new StackifyComponent();
		$Stackify->write_error('Error', $message);
		return $Stackify->write_error('Error', $message);
		//return CakeLog::write($log, $message);
	}

	public static function handleFatalError($code, $description, $file, $line) {
		$logMessage = 'Fatal Error (' . $code . '): ' . $description . ' in [' . $file . ', line ' . $line . ']';
		//CakeLog::write(LOG_ERR, $logMessage);
		$Stackify = new StackifyComponent();
		$Stackify->write_error('Fatal Error', $logMessage);

		$exceptionHandler = Configure::read('Exception.handler');
		if (!is_callable($exceptionHandler)) {
			return false;
		}

		if (ob_get_level()) {
			ob_end_clean();
		}

		if (Configure::read('debug')) {
			call_user_func($exceptionHandler, new FatalErrorException($description, 500, $file, $line));
		} else {
			call_user_func($exceptionHandler, new InternalErrorException());
		}
		return false;
	}
}
