<?php

namespace PhelixJuma\GUIFlow;

class WorkflowEvent
{

    const EVENT_TYPE_RULE = 'rule';
    const EVENT_TYPE_ACTION = 'action';

    const EVENT_STARTED = 'started';
    const EVENT_SKIPPED = 'skipped';
    const EVENT_SUCCESS = 'success';
    const EVENT_FAILED = 'failed';
    const EVENT_COMPLETED = 'completed';

    public $event_type;
    public $name;
    public $dataBefore;

    public $event_logs = [];

    /**
     * @param $name
     * @param $eventType
     * @param $dataBefore
     * @return $this
     */
    public function init($name, $eventType, $dataBefore = null) {

        $this->name = $name;
        $this->event_type = $eventType;
        $this->dataBefore = $dataBefore;

        return $this;
    }

    /**
     * @param $event
     * @param $dataAfter
     * @param $error_message
     * @param $error_trace
     * @return void
     */
    private function triggerEvent($event, $dataAfter = null, $error_message = null, $error_trace = null) {

        $this->event_logs[$this->name][] = [
            'event_type'    => $this->event_type,
            'event'         => $event,
            'data_before'   => $this->dataBefore,
            'data_after'    => $dataAfter,
            'error'         => [
                "message"   => $error_message,
                "trace"     => $error_trace
            ]
        ];
    }

    /**
     * @return void
     */
    public function onStarted() {
        $this->triggerEvent(self::EVENT_STARTED);
    }

    /**
     * @return void
     */
    public function onSkipped() {
        $this->triggerEvent(self::EVENT_SKIPPED);
    }

    public function onCompleted() {
        $this->triggerEvent(self::EVENT_COMPLETED);
    }

    /**
     * @param $dataAfter
     * @return void
     */
    public function onSuccess($dataAfter) {
        $this->triggerEvent(self::EVENT_SUCCESS, $dataAfter);
    }

    /**
     * @param $errorMessage
     * @param $errorTrace
     * @return void
     */
    public function onFailed($errorMessage, $errorTrace = null) {
        $this->triggerEvent(self::EVENT_FAILED, null, $errorMessage, $errorTrace);
    }


}
