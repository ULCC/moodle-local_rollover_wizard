<?php
/**
 *
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/local/rollover_wizard/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->libdir . '/cronlib.php';

global $CFG, $DB, $USER;

if (!isloggedin()) {
    echo '';
    exit;
}

function display_result($status = 200,$data){
    echo json_encode([
        'status' => 200,
        'data' => $data
    ]);
    exit;
}
if (confirm_sesskey()) {

    $action = required_param('action', PARAM_TEXT);

    $data_key = required_param('data_key', PARAM_INT);
    if ($action == 'runrollovertask'){

        // $plugin_name = 'local_rollover_wizard';
        // $taskid_config = 'taskid';
        // $taskid = get_config($plugin_name, $taskid_config);

        // if(empty($taskid)){
        //     return;
        // }

        // $taskname = 'local_rollover_wizard\task\execute_rollover';

        // $task = \core\task\manager::get_scheduled_task($taskname);
        // if (!$task) {
        //     print_error('cannotfindinfo', 'error', $taskname);
        // }

        // // Run the specified task (this will output an error if it doesn't exist).
        // \core\task\manager::run_from_cli($task);

        /* Version 4 Code */
        $ch = curl_init();

        $url = (new moodle_url('/local/rollover_wizard/workerfile.php'))->out();
        curl_setopt($ch, CURLOPT_URL, $url);
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
    
        curl_setopt($ch, CURLOPT_NOBODY, true);
    
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    
        curl_exec($ch);
    
        curl_close($ch);
    }
}

echo "";
exit;