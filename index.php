<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_rollover_wizard
 * @copyright  2024 Terus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';

global $PAGE, $USER, $DB, $CFG, $OUTPUT;

require_login();

if (!has_capability('local/rollover_wizard:edit', context_system::instance())) {
    throw new moodle_exception("You don't have the permission to access this page.");
}
$import_step = optional_param('import_step', 1, PARAM_INT);

$mform = new \MoodleQuickForm('import_content_form','POST','#', $target = '', ['id' => 'import_content_form']);
// // $radioarray=array();
$mform->addElement('hidden', 'rollover_step', $import_step);
$mform->addElement('radio', 'content_option', '', get_string('content_option1','local_rollover_wizard'), 1);
$mform->addElement('radio', 'content_option', '', get_string('content_option2','local_rollover_wizard'), 0);
// $mform->add_action_buttons(true, 'Next');
// $mform->addGroup($radioarray, 'radioar', '', array(' '), false);
// $html .= $testform->toHtml();
// $buttonarray=array();
// $buttonarray[] = $mform->createElement('button', 'cancelbutton', 'Cancel');
// $buttonarray[] = $mform->createElement('submit', 'submitbutton', 'Next');
// $mform->addGroup($buttonarray, 'buttonar', null, ' ', false);


$PAGE->set_pagelayout('popup');
echo $OUTPUT->header();

?>
<div class="container" style="padding-top: 15px;">
    <h2>Import Course</h2>
    <p>Do you want to start from a blank template or import content from a previous course ?</p>

    <?php
        $mform->display();
    ?>
</div>
<div style="position:absolute;bottom:0;width:80%;left:0;right:0; margin: auto;">
    <div class="d-flex justify-content-between">
        <button type="button" id="wizard_cancel_button" class="btn btn-secondary">Cancel</button>
        <button type="button" id="wizard_next_button" class="btn btn-primary">Submit</button>
    </div>
</div>
<?php

echo $OUTPUT->footer();