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
class calculate_course_size extends \core\task\scheduled_task {

     /**
      * Gets the name of the task.
      *
      * @return string The name of the task.
      */
    public function get_name() {
        // Shown on admin screens
        return 'Rollover Wizard - Calculate Course Size';
    }

    /**
     * Executes the rollover process.
     *
     * @return bool True if the rollover was successful, false otherwise.
     */
    public function execute() {

        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');

        mtrace('Rollover Wizard - Calculate Course Size Start');
        $courses = $DB->get_records_sql('SELECT * FROM {course} WHERE id > 1');
        foreach ($courses as $course) {
            $courseid = $course->id;

            mtrace('Checking Course '.$course->fullname. " (".$courseid.")");
            $coursefilesize = local_rollover_wizard_course_filesize($courseid);
            if (!$coursefilesize || $coursefilesize->filesize < 1) {
                mtrace('Course '.$course->fullname. " (".$courseid.") skipped (cannot detect filesize!)");
                continue;
            }
            if ($record = $DB->get_record('rollover_wizard_coursesize', ['courseid' => $courseid])) {
                $record->size = $coursefilesize->filesize;
                $record->timeupdated = time();
                $DB->update_record('rollover_wizard_coursesize', $record);
            } else {
                $record = new \stdClass();
                $record->courseid = $courseid;
                $record->size = $coursefilesize->filesize;
                $record->timeupdated = time();
                $record->timecreated = time();
                $DB->insert_record('rollover_Wizard_coursesize', $record);
            }

            mtrace('Course '.$course->fullname. " (".$courseid.") Total size : ".display_size($coursefilesize->filesize));
        }

        mtrace('Rollover Wizard - Calculate Course Size End');
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
