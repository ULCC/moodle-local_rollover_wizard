<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 *
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->libdir . '/cronlib.php');

global $CFG, $DB, $USER;

if (!isloggedin()) {
    echo '';
    exit;
}

/**
 * Displays a JSON response with a given status code and data.
 *
 * Encodes an array containing a 'status' code and the provided 'data' into JSON format
 * and outputs it to the client. Terminates script execution after outputting.
 *
 * @param int $status The HTTP status code to be included in the response. Defaults to 200.
 * @param mixed $data The data to be included in the response. Can be any data type.
 * @return void
 */
function display_result($status = 200, $data) {
    echo json_encode([
        'status' => 200,
        'data' => $data,
    ]);
    exit;
}
if (confirm_sesskey()) {
    $action = required_param('action', PARAM_TEXT);
    $datakey = required_param('data_key', PARAM_INT);
    if ($action == 'runrollovertask') {
        $taskname = 'local_rollover_wizard\task\execute_rollover';

        $task = \core\task\manager::get_scheduled_task($taskname);
        if (!$task) {
            print_error('cannotfindinfo', 'error', $taskname);
        }
        $pathphp = false;

        if (!empty($CFG->pathtophp) && is_executable(trim($CFG->pathtophp))) {
            $pathphp = $CFG->pathtophp;
        }
        $phpbinary = escapeshellarg($pathphp);

        // Shell-escaped path CLI script.
        $pathcomponents = [$CFG->dirroot, 'local/rollover_wizard/workerfile.php'];
        $scriptpath     = escapeshellarg(implode(DIRECTORY_SEPARATOR, $pathcomponents));

        // Shell-escaped task name.
        $classname = get_class($task);

        $outputarg = escapeshellarg($CFG->dataroot. DIRECTORY_SEPARATOR.'rollover_wizard_log.txt');

        // Build the CLI command.
        $command = "nohup {$phpbinary} {$scriptpath} > {$outputarg} 2>&1 &";
        // Execute it.
        shell_exec($command);

        echo "1";
    }
}
echo "";
exit;
