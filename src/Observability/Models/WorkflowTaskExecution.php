<?php

namespace PhelixJuma\GUIFlow\Observability\Models;

use DataLoader\FromArray;

class WorkflowTaskExecution {
    use FromArray;

    public string $id;
    public string $execution_id;
    public string $parent_execution_id;
    public string $status;
    public array $input_state;
    public array $output_state;
    public array $inputs;
    public array $output;
    public string $start_time;
    public string $end_time;
    public string $error_message;
    public string $error_trace;
}
