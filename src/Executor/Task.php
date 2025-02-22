<?php

namespace PhelixJuma\GUIFlow\Executor;

class Task {

    private $id;
    private $callable;
    private $status;
    private $result;
    private $exception;
    private $exceptionTrace;
    private $startTime;
    private $endTime;
    private $input_state;

    public function __construct($id, $input_state, callable $callable) {

        $this->id = $id;
        $this->input_state = $input_state;
        $this->callable = $callable;
        $this->status = TaskStatus::PENDING;

    }

    public function getId() {
        return $this->id;
    }

    public function getInputState() {
        return $this->input_state;
    }

    public function getCallable() {
        return $this->callable;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setStatus($status) {

        $this->status = $status;

        if ($this->status == TaskStatus::RUNNING) {
            $this->setStartTime();
        } elseif ($this->status == TaskStatus::COMPLETED || $this->status == TaskStatus::FAILED) {
            $this->setEndTime();
        }
    }

    public function getResult() {
        return $this->result;
    }

    public function getExecutionTime() {
        return $this->endTime - $this->startTime;
    }

    public function setResult($result) {
        $this->result = $result;
    }

    public function getException() {
        return $this->exception;
    }

    public function setException($exception) {
        $this->exception = $exception;
    }

    public function getExceptionTrace() {
        return $this->exceptionTrace;
    }

    public function setExceptionTrace($exceptionTrace) {
        $this->exceptionTrace = $exceptionTrace;
    }

    public function setStartTime() {
        $this->startTime = microtime(true);
    }

    public function setEndTime() {
        $this->endTime = microtime(true);
    }

}
