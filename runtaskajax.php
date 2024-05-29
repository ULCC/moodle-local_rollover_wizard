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
        
        // // Shell-escaped path to the PHP binary.
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
        // $command = "{$phpbinary} {$scriptpath} {$taskarg} > {$outputarg} 2>&1 &";
        // echo "<pre>", $command, "</pre>";
        // // Execute it.
        // passthru($command);

        /* Version 1 Code */
        // $parts = parse_url((new moodle_url('/local/rollover_wizard/workerfile.php'))->out());
        // $path = parse_url((new moodle_url('/local/rollover_wizard/workerfile.php'))->out(), PHP_URL_PATH);
        // $parts = [
        //     'port' => $_SERVER['SERVER_PORT'],
        //     'host' => $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$path,
        // ];
        // echo "<pre>", var_dump($parts), "</pre>";
        // $fp = fsockopen($parts['host'], isset($parts['port']) ? intval($parts['port']) : 80, $errno, $errstr, 300);

        // if (!$fp) {
        //     return false;
        // }
        // $out = "GET " . $parts['path'] . " HTTP/1.1\r\n";
        // $out .= "Host: " . $parts['host'] . "\r\n";
        // $out .= "Connection: Close\r\n\r\n";

        // fwrite($fp, $out);
        // fclose($fp);

        /* Version 2 Code */
        // $url = (new moodle_url('/local/rollover_wizard/workerfile.php'))->out();
        // $ch = curl_init();

        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_TIMEOUT, 1); // 1 second timeout to let the request go in background
        // curl_setopt($ch, CURLOPT_HEADER, 0);
        // curl_setopt($ch, CURLOPT_NOBODY, true); // We don't need the body
        
        // // Option to make the request in the background
        // curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); // Ensure no reuse of connection
        // curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        
        // $result = curl_exec($ch);
        // curl_close($ch);
    

    }
}

echo "";
exit;