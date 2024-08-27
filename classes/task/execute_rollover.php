<?php
/**
 *
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rollover_wizard\task;

class execute_rollover extends \core\task\scheduled_task {

    public function get_name() {
        // Shown on admin screens
        return 'Rollover Wizard - Execute Rollover';
    }

    public function execute() {

        global $CFG;

        require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');

        local_rollover_wizard_executerollover();

        return true;
    }

    public function can_run(): bool  {
        return true;
    }
}
