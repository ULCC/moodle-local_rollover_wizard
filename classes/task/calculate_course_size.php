<?php
/**
 *
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rollover_wizard\task;

class calculate_course_size extends \core\task\scheduled_task {

    public function get_name() {
        // Shown on admin screens
        return 'Rollover Wizard - Calculate Course Size';
    }

    public function execute() {

        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');

        // local_rollover_wizard_executerollover();
        // table name rollover_wizard_coursesize
        mtrace('Rollover Wizard - Calculate Course Size Start');
        $courses = $DB->get_records_sql('SELECT * FROM {course} WHERE id > 1');
        foreach($courses as $course){
            $courseid = $course->id;
            
            mtrace('Checking Course '.$course->fullname. " (".$courseid.")");
            $coursefilesize = local_rollover_wizard_course_filesize($courseid);
            if(!$coursefilesize){
                mtrace('Course '.$course->fullname. " (".$courseid.") skipped (cannot detect filesize!)");
                continue;
            }
            if($record = $DB->get_record('rollover_wizard_coursesize', ['courseid' => $courseid])){
                $record->size = $coursefilesize->filesize;
                $record->timeupdated = time();
                $DB->update_record('rollover_wizard_coursesize', $record);
            }
            else{
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

    public function can_run(): bool  {
        return true;
    }
}
