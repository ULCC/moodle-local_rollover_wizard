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

namespace local_rollover_wizard\task;

/**
 * Executes a rollover process.
 *
 * This class is responsible for performing a calculate course size
 *
 * @package core\task
 */
class replace_pluginfile_urls extends \core\task\scheduled_task {
    /**
     * Gets the name of the task.
     *
     * @return string The name of the task.
     */
    public function get_name() {
        return get_string('replace_pluginfile_urls', 'local_rollover_wizard');
    }

    /**
     * Executes the rollover process.
     *
     * @return bool True if the rollover was successful, false otherwise.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');
        local_rollover_wizard_replace_urls_section();
        return true;
    }

    /**
     * Checks if the task can run.
     *
     * This method always returns true, indicating that the task can run anytime.
     *
     * @return bool True if the task can run, false otherwise.
     */
    public function can_run(): bool {
        return true;
    }
}
