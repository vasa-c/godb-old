<?php

class goDBTestHelperDebug {

    public function __construct($prefix = '') {
        $this->prefix = $prefix;
    }

    public function debug($message) {
        $this->message = $this->prefix.': '.$message;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getCallback() {
        return array($this, 'debug');
    }

    private $prefix, $message;

}