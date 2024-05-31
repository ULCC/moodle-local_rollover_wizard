<?php

define('CLI_SCRIPT', true);

require_once __DIR__.'/../../config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/local/rollover_wizard/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->libdir . '/cronlib.php';

$plugin_name = 'local_rollover_wizard';
$taskid_config = 'taskid';
$taskid = get_config($plugin_name, $taskid_config);
if(empty($taskid)){
    return;
}

$taskname = 'local_rollover_wizard\task\execute_rollover';

$task = \core\task\manager::get_scheduled_task($taskname);
if (!$task) {
    print_error('cannotfindinfo', 'error', $taskname);
}

// Run the specified task (this will output an error if it doesn't exist).
\core\task\manager::run_from_cli($task);

// Shell-escaped path to the PHP binary.
// $pathphp = false;

// if (!empty($CFG->pathtophp) && is_executable(trim($CFG->pathtophp))) {
//     $pathphp = $CFG->pathtophp;
// }
// $phpbinary = escapeshellarg($pathphp);

// // Shell-escaped path CLI script.
// $pathcomponents = [$CFG->dirroot, $CFG->admin, 'cli', 'scheduled_task.php'];
// $scriptpath     = escapeshellarg(implode(DIRECTORY_SEPARATOR, $pathcomponents));

// // Shell-escaped task name.
// $classname = get_class($task);
// $taskarg   = escapeshellarg("--execute={$classname}") . " " . escapeshellarg("--force");

// $outputarg = escapeshellarg($CFG->dataroot. '\\rollover_wizard_log.txt');

// // Build the CLI command.
// $command = "{$phpbinary} {$scriptpath} {$taskarg} > {$outputarg} &";
// // Execute it.
// passthru($command);