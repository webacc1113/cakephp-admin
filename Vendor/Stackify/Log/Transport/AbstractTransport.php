<?php

namespace Stackify\Log\Transport;

use Stackify\Log\Builder\BuilderInterface;
use Stackify\Log\Builder\NullBuilder;
use Stackify\Log\Filters\ErrorGovernor;
use Stackify\Exceptions\InitializationException;

abstract class AbstractTransport implements TransportInterface
{

    /**
     * @var \Stackify\Log\Builder\BuilderInterface
     */
    protected $messageBuilder;

    /**
     * @var \Stackify\Log\Filters\ErrorGovernor
     */
    protected $errorGovernor;

    protected $debug = false;
    private $debugLogPath;

    public function __construct()
    {
        $ds = DIRECTORY_SEPARATOR;
        $this->debugLogPath = realpath(dirname(__FILE__) . "$ds..$ds..") . $ds . 'debug/log.log';
        $this->errorGovernor = new ErrorGovernor();
        // add empty implementation to avoid method calls on non-object
        $this->setMessageBuilder(new NullBuilder());
    }

    public function setMessageBuilder(BuilderInterface $messageBuilder)
    {
        $this->messageBuilder = $messageBuilder;
    }

    protected abstract function getTransportName();

    protected abstract function getAllowedOptions();

    protected function extractOptions($options)
    {
        foreach ($this->getAllowedOptions() as $name => $regex) {
            if (isset($options[$name])) {
                $value = $options[$name];
                if (preg_match($regex, $value)) {
                    $this->$name = $value;
                } else {
                    throw new InitializationException("Option '$name' has invalid value");
                }
            }
        }
    }

    protected function logError($message)
    {
        $this->log($message, func_get_args(), false);
    }

    protected function logDebug($message)
    {
        if (!$this->debug) {
            return;
        }
        $this->log($message, func_get_args(), true);
    }

    private function log($message, $args, $success = true)
    {
        $replacements = array_slice($args, 1);
        $prefix = $success ? 'Stackify Log' : 'Stackify Error';
        $template = "[$prefix] $message [{$this->getTransportName()}]";
        $formatted = preg_replace('/\r\n/', '', vsprintf($template, $replacements));
        // first option - write to local file if possible
        // this can be not available because of file permissions
        @file_put_contents($this->debugLogPath, "$formatted\n", FILE_APPEND);
        if (!$success) {
            // second option - send to default PHP error log
            error_log($formatted);
        }
    }

}