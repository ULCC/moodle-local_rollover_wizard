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

define('CLI_SCRIPT', true);

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->libdir . '/cronlib.php');

$pluginname = 'local_rollover_wizard';
$taskidconfig = 'taskid';
$taskid = get_config($pluginname, $taskidconfig);
if (empty($taskid)) {
    return;
}

$taskname = 'local_rollover_wizard\task\execute_rollover';

$task = \core\task\manager::get_scheduled_task($taskname);
if (!$task) {
    print_error('cannotfindinfo', 'error', $taskname);
}

// Run the specified task (this will output an error if it doesn't exist).
// \core\task\manager::run_from_cli($task);

local_rollover_wizard_executerollover(2);

/* Old Code */
// Shell-escaped path to the PHP binary.
// $pathphp = false;

// if (!empty($CFG->pathtophp) && is_executable(trim($CFG->pathtophp))) {
// $pathphp = $CFG->pathtophp;
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
