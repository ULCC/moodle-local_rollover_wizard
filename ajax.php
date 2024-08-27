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
 * This file contains helper functions for the Moodle Rollover Wizard.
 *
 * Provides functionalities related to the course rollover process, including verification,
 * data manipulation, and progress tracking. This file utilizes existing Moodle libraries
 * for course management, block handling, and backup/restore operations.
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

global $CFG, $DB, $USER, $PAGE;

if (!isloggedin()) {
    echo '';
    exit;
}

/**
 * Displays a JSON response with a status code and data.
 *
 * This function encodes an array containing a 'status' code and the provided 'data'
 * into JSON format and outputs it to the client.
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
    if ($action == 'renderform') {
        $setting = get_config('local_rollover_wizard');
        $step = required_param('step', PARAM_INT);
        if ($step == 1) {
            $html = '<h2>'.get_string('importcourse', 'local_rollover_wizard').'</h2>'.'';
            $html .= '<p>Do you want to start from a blank template or import content from a previous course ?</p>';
            $warning = local_rollover_wizard_verify_course(
                null,
                ($_SESSION['local_rollover_wizard'][$datakey]['target_course'])->id,
                false
            );
            if (!empty($warning) && $warning !== '&nbsp;') {
                $warning = "<div class='alert alert-warning'>".$warning."</div>";
            }
            $html .= '<div class="alert-container">'.$warning.'</div>';
            $html .= '<div class="form-group row  fitem">';
            $html .= '  <div class="col-md-3 col-form-label pb-0 pt-0">';
            $html .= '  </div>';
            $html .= '  <div class="col-md-9 checkbox">';
            $html .= '    <div class="form-check d-flex">';
            $html .= '      <label class="form-check-label">';
            $html .= '        <input type="radio" class="form-check-input" name="content_option" id="id_content_option_blanktemplate" value="blanktemplate"> ';
            $html .= get_string('content_option1', 'local_rollover_wizard');
            $html .= '      </label>';
            $html .= '      <div class="ml-2 d-flex align-items-center align-self-start"> </div>';
            $html .= '    </div>';
            $html .= '    <div class="form-control-feedback invalid-feedback" id="id_error_content_option_blanktemplate"></div>';
            $html .= '  </div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="form-group row  fitem  ">';
            $html .= '  <div class="col-md-3 col-form-label pb-0 pt-0">';
            $html .= '  </div>';
            $html .= '  <div class="col-md-9 checkbox">';
            $html .= '    <div class="form-check d-flex">';
            $html .= '      <label class="form-check-label">';
            $html .= '        <input type="radio" class="form-check-input" name="content_option" id="id_content_option_previouscourse" value="previouscourse"> ';
            $html .= get_string('content_option2', 'local_rollover_wizard');
            $html .= '      </label>';
            $html .= '      <div class="ml-2 d-flex align-items-center align-self-start"> </div>';
            $html .= '    </div>';
            $html .= '    <div class="form-control-feedback invalid-feedback" id="id_error_content_option_previouscourse"> </div>';
            $html .= '  </div>';
            $html .= '</div>';
            $html .= '</div>';
            display_result(200, ['html' => $html]);
        }
        if ($step == 2) {
            $mode = required_param('mode', PARAM_TEXT);
            if ($mode == 'previouscourse') {
                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $targetcourse = $sessiondata['target_course'];
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>';
                $html .= '<div class="alert-container"></div>';
                $html .= "<div class='row text-center'>";

                $sourcecourse = null;
                $sourcecoursehtml = "";
                // Preg Match source course here.
                if (!empty($targetcourse->idnumber)) {
                    $matches = [];
                    preg_match('/(.*?)([0-9]{6})/', $targetcourse->idnumber, $matches);
                    $targetacademicyear = intval((empty($matches[2]) ? 0 : $matches[2]));

                    if (!empty($targetacademicyear)) {
                        $courseids = [];
                        $academicyears = [];
                        $courses = $DB->get_records('course');
                        foreach ($courses as $course) {
                            $codeidnumber = preg_replace('/(.*?)([0-9]{6})/', '$1', $course->idnumber);
                            if (!empty($codeidnumber) && $codeidnumber == $matches[1]) {
                                $acadidnumber = preg_replace('/(.*?)([0-9]{6})/', '$2', $course->idnumber);
                                $acadidnumber = intval($acadidnumber);
                                $courseids[$acadidnumber] = $course->id;
                                $academicyears[] = $acadidnumber;
                            }
                        }
                        rsort($academicyears);
                        list($targetstartyear, $targetendyear) = preg_split('/(?<=.{4})/', $matches[2], 2);
                        $matchedcourseid = null;
                        foreach ($academicyears as $year) {
                            list($sourcestartyear, $sourceendyear) = preg_split('/(?<=.{4})/', (string) $year, 2);

                            if ($sourcestartyear < $targetstartyear && $sourceendyear < $targetendyear) {
                                $matchedcourseid = $courseids[$year];
                                break;
                            }
                        }
                        if (!empty($matchedcourseid)) {
                            $sessiondata['source_course'] = $DB->get_record('course', ['id' => $matchedcourseid]);

                        }
                    }
                }

                if (!empty($sessiondata['source_course'])) {
                    $sourcecourse = $sessiondata['source_course'];
                }
                if (empty($sourcecourse)) {
                    $sessiondata['source_course'] = null;
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='#' class='previewcourse_source_course_link rollover-disabled-link' target='_blank'>Source course not selected yet</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                } else {
                    $sessiondata['source_course'] = $sourcecourse;
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $sourcecourse->id]);
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank' data-courseid='".$sourcecourse->id."'>".$sourcecourse->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= $sourcecoursehtml;
                $html .= "</div></div>";
                // Separator.
                $html .= "<div class='col-2'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h4><i class='fa fa-arrow-right'></i></h4>";
                $html .= "</div></div>";
                // Target Course.
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h6>This Course : </h6>".$targetcourse->fullname;
                $html .= "</div></div>";
                $html .= "</div>";
                display_result(200, ['html' => $html]);
            }

            if ($mode == 'blanktemplate') {
                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $targetcourse = $sessiondata['target_course'];
                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>';
                $html .= '<div class="alert-container"></div>';
                $html .= "<div class='d-flex justify-content-center text-center'>";


                $sourcecourse = null;
                $sourcecoursehtml = "";
                // Preg Match source course here.
                if (!empty($sessiondata['source_course'])) {
                    $sourcecourse = $sessiondata['source_course'];
                }
                if (empty($sourcecourse)) {
                    $sessiondata['source_course'] = null;
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='#' class='previewcourse_source_course_link' target='_blank'>Source course not selected yet</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                } else {
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $sourcecourse->id]);
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank'>".$sourcecourse->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $sourcecoursehtml = "
                <h6>Choose a template : </h6>";

                $sourcecoursehtml .= "<select class='form-control' id='selected_template_course'>";
                $sourcecoursehtml .= "<option selected style='display:none !important;' value=''>Select Template Course</option>";

                $setting = get_config('local_rollover_wizard');
                $templatecategory = !empty($setting->identifier_template_course) ? $setting->identifier_template_course : 0;

                $curcategory = core_course_category::get($templatecategory);
                $curcourses = $curcategory->get_courses(['recursive' => false]);
                foreach ($curcourses as $course) {
                    $sourcecoursehtml .= "<option value='".$course->id."'>".$course->fullname."</option>";
                }

                $sourcecoursehtml .= "</select>";
                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $sourcecoursehtml;
                $html .= "</div>";

                $html .= "</div>";
                display_result(200, ['html' => $html]);
            }
        }
        if ($step == 3) {
            $mode = required_param('mode', PARAM_TEXT);
            if ($mode == 'previouscourse') {

                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $targetcourse = $sessiondata['target_course'];

                $sourcecourse = null;
                $sourcecoursehtml = "";
                $warninghtml = "";
                // Preg Match source course here.
                if (!empty($sessiondata['source_course'])) {
                    $sourcecourse = $sessiondata['source_course'];
                }

                $warninghtml = local_rollover_wizard_verify_course($sourcecourse->id, $targetcourse->id, false);
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
                $html .= "<div class='d-flex flex-column justify-content-center text-center'>";

                if (empty($sourcecourse)) {
                    display_result(403, ['html' => null]);
                } else {
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $sourcecourse->id]);
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank'>".$sourcecourse->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $sourcecoursehtml;
                $html .= "</div>";
                $html .= "<div id='rollover-activity-container'>";
                $time = time();
                // Buttons
                $html .= "<div class='d-flex justify-content-between my-5'>";
                $html .= "<div class='filter-button-container'><button type='button' class='btn btn-primary' id='btn-select-filter'>Select Activity Types</button></div>";
                $html .= '<div class="form-check d-flex justify-content-center align-items-center" style="gap:20px; flex-grow:1;">';
                $html .= '<input class="form-check-input position-static rollover-check-coursesettings"';
                $html .= ' id="rollover-wizard-coursesettings'.$time.'" data-module="coursesettings" data-section="-1"';
                $html .= ' name="rollover-wizard-cm[]" type="checkbox" value="coursesettings" style="margin-top:0;">';
                $html .= ' <label class="form-check-label" for="rollover-wizard-coursesettings'.$time.'">Import Course Settings</label></div>';
                $html .= "<div class='select-button-container'><button type='button' class='btn btn-primary' id='btn-select-all'>Select All</button>";
                $html .= " | <button type='button' class='btn btn-secondary' id='btn-deselect-all'>Deselect All</button></div>";
                $html .= "</div>";

                // Modules
                $html .= "<div class='d-flex flex-column w-75 mx-auto' style='gap:10px;max-height: 40vh;overflow-y:scroll;'>";
                $coursesections = $DB->get_records('course_sections', ['course' => $sourcecourse->id], 'section ASC');
                $iteration = 1;
                $activitytypes = [];

                $coursecontext = \context_course::instance($targetcourse->id);
                $PAGE->set_context($coursecontext);
             
                $excludedactivitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
                $excludedactivitytypes = array_map('trim', $excludedactivitytypes);
                foreach ($coursesections as $section) {
                    $sequence = $section->sequence;
                    $html .= '<div class="card">';
                    $html .= '<div class="card-header ">';
                    $html .= '<div class="d-flex justify-content-between w-80 text-center">';
                    $html .= '<div class="form-check"><input class="form-check-input position-static rollover-check-section"';
                    $html .= ' data-section="'.$section->section.'" name="rollover-wizard-cm[]" data-module="coursesections"';
                    $html .= ' data-section="'.$section->section.'" type="checkbox" value="'.$section->section.'" data-id="'.$section->id.'"></div>';
                    $html .= '<div style="flex-grow:1;cursor:pointer;" data-toggle="collapse"';
                    $html .= ' data-target="#collapsecontainer'.$iteration.'"><b>'.get_section_name($sourcecourse->id, $section->section)."</b></div>";
                    $html .= '<i class="collapse-toggle" style="cursor:pointer;" data-toggle="collapse" data-target="#collapsecontainer'.$iteration.'"></i>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '<div class="card-body collapse show" id="collapsecontainer'.$iteration.'">';
                    $html .= '<div class="d-flex flex-column" style="gap:15px;">';
                    $cmids = explode(',', $sequence);
                    foreach ($cmids as $cmid) {
                        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                        if ($cm && $cm->deletioninprogress < 1) {
                            $modulerecord = $DB->get_record('modules', ['id' => $cm->module]);
                            if (!$modulerecord) {
                                continue;
                            }
                            if (empty($modulerecord->name)) {
                                continue;
                            }
                            $disabledattr = "";
                            $disabledstyle = "";
                            $disabled = false;
                            if (in_array($modulerecord->name, $excludedactivitytypes)) {
                                $disabled = true;
                                $disabledattr = "disabled";
                                $disabledstyle = "opacity: 50%;";
                            }
                            $modshortname = $modulerecord->name;
                            $modfullname = get_string('pluginname', $modshortname);
                            if (!$disabled) {
                                $activitytypes[$modshortname] = $modfullname;
                            }
                            $modlogo = $OUTPUT->image_icon('monologo', $modshortname, $modshortname);
                            $activityrecord = $DB->get_record($modshortname, ['id' => $cm->instance]);
                            $activityname = $activityrecord->name;
                            $html .= "<div class='d-flex flex-row justify-content-start align-items-center' style='gap:25px;".$disabledstyle."'>";
                            $html .= '<div class="form-check"><input class="form-check-input position-static rollover-check-'.$modshortname.' rollover-check-cm" '.$disabledattr.'';
                            $html .= ' data-section="'.$section->section.'" data-module="'.$modshortname.'" name="rollover-wizard-cm[]" type="checkbox" value="'.$cm->id.'"></div>';
                            $html .= $modlogo;
                            $html .= '<div class="d-flex flex-column text-left"><div class="text-uppercase small">'.$modshortname.'</div><div>'.$activityname.'</div></div>';
                            if ($cm->visible == 0) {
                                $html .= '<div class="my-1 d-flex align-items-center"><span class="badge badge-pill badge-warning">Hidden from students</span></div>';
                            }
                            $html .= "</div>";
                        }
                    }
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $iteration++;
                }
                $html .= "</div>";
                $html .= "</div>";
                $temp = [];
                foreach ($activitytypes as $key => $value) {
                    $temp[] = [
                        'key' => $key,
                        'value' => $value,
                    ];
                }
                $activitytypes = $temp;
                display_result(200, ['html' => $html, 'activity_types' => $activitytypes]);
            }

            if ($mode == 'blanktemplate') {
                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $targetcourse = $sessiondata['target_course'];
                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>';
                $html .= '<div class="alert-container"></div>';
                $html .= "<div class='row text-center'>";

                $sourcecourse = null;
                $sourcecoursehtml = "";
                // Preg Match source course here.
                if (!empty($sessiondata['source_course'])) {
                    $sourcecourse = $sessiondata['source_course'];
                }
                if (empty($sourcecourse)) {
                    $sessiondata['source_course'] = null;
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Template Course : </h6>
                    <div>
                    <a href='#' class='previewcourse_source_course_link' target='_blank'>Source course not selected yet</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                } else {
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $sourcecourse->id]);
                    $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                    $sourcecoursehtml = "
                    <h6>Template Course : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank'>".$sourcecourse->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= $sourcecoursehtml;
                $html .= "</div></div>";
                // Separator.
                $html .= "<div class='col-2'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h4><i class='fa fa-arrow-right'></i></h4>";
                $html .= "</div></div>";
                // Target Course.
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h6>This Course : </h6>".$targetcourse->fullname;
                $html .= "</div></div>";
                $html .= "</div>";
                display_result(200, ['html' => $html]);
            }
        }

        if ($step == 4) {
            $mode = required_param('mode', PARAM_TEXT);
            if ($mode == 'previouscourse') {

                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $targetcourse = $sessiondata['target_course'];
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
                $html .= "<div class='d-flex flex-column justify-content-between text-center'>";
                $selectedactivity = $sessiondata['selected_activity'];
                $processedactivity = [];
                $selectedsections = [];
                foreach ($selectedactivity as $activity) {

                    if ($activity->key == 'coursesections') {
                        if (!in_array($activity->section, $selectedsections)) {
                            $selectedsections[] = $activity->section;
                        }
                        continue;
                    }
                    $processedactivity[$activity->section][] = $activity->value;

                    if (!in_array($activity->section, $selectedsections)) {
                        $selectedsections[] = $activity->section;
                    }
                }
                $sourcecourse = $sessiondata['source_course'];
                $sourcecoursehtml = "";
                $viewurl = new \moodle_url('/course/view.php', ['id' => $sourcecourse->id]);
                $sourcecoursehtml = "
                <h6>Previous Course you were enrolled in : </h6>
                <div>
                <p>".$sourcecourse->fullname."</p>
                </div>";

                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $sourcecoursehtml;
                $html .= "</div>";

                if (!empty($sessiondata['import_course_setting']) && $sessiondata['import_course_setting'] == true) {
                    $html .= "<div class='alert alert-primary'>Import Course Setting Enabled</div>";
                }

                $html .= "<div class='d-flex flex-column w-75 mx-auto' style='gap:10px;max-height: 50vh;overflow-y:scroll;'>";
                $html .= "<table class='table table-striped'>";
                $html .= "<thead>";
                $html .= "<tr>";
                $html .= "<th>Module</th>";
                $html .= "<th>Status</th>";
                $html .= "</tr>";
                $html .= "</thead>";
                $html .= "<tbody>";

                // Modules.
                $coursesections = $DB->get_records('course_sections', ['course' => $sourcecourse->id], 'section ASC');
                $iteration = 1;
                $activitytypes = [];


                $coursecontext = \context_course::instance($targetcourse->id);
                $PAGE->set_context($coursecontext);

                $excludedactivitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
                $excludedactivitytypes = array_map('trim', $excludedactivitytypes);
                foreach ($coursesections as $section) {
                    if (!in_array($section->section, $selectedsections)) {
                        continue;
                    }
                    $sequence = $section->sequence;
                    $html .= '<tr>';
                    $html .= '<td colspan="2"><b>'.get_section_name($sourcecourse->id, $section->section)."</b></td>";
                    $html .= '</tr>';
                    $cmids = explode(',', $sequence);
                    foreach ($cmids as $cmid) {
                        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                        if ($cm && $cm->deletioninprogress < 1) {
                            $modulerecord = $DB->get_record('modules', ['id' => $cm->module]);
                            if (!$modulerecord) {
                                continue;
                            }
                            if (empty($modulerecord->name)) {
                                continue;
                            }
                            $disabledattr = "";
                            $disabledstyle = "";
                            $disabled = false;
                            if (in_array($modulerecord->name, $excludedactivitytypes)) {
                                $disabled = true;
                                $disabledattr = "disabled";
                                $disabledstyle = "opacity: 50%;";
                            }
                            $modshortname = $modulerecord->name;
                            $modfullname = get_string('pluginname', $modshortname);
                            if (!$disabled) {
                                $activitytypes[$modshortname] = $modfullname;
                            }
                            $modlogo = $OUTPUT->image_icon('monologo', $modshortname, $modshortname);
                            $activityrecord = $DB->get_record($modshortname, ['id' => $cm->instance]);
                            $activityname = $activityrecord->name;
                            $html .= "<tr>";
                            $html .= "<td>";
                            $html .= "<div class='d-flex flex-row justify-content-start align-items-center' style='gap:25px;".$disabledstyle."'>";
                            $html .= $modlogo;
                            $html .= '<div class="d-flex flex-column text-left"><div class="text-uppercase small">'.$modshortname.'</div><div>'.$activityname.'</div></div>';
                            if ($cm->visible == 0) {
                                $html .= '<div class="my-1 d-flex align-items-center"><span class="badge badge-pill badge-warning">Hidden from students</span></div>';
                            }
                            $html .= "</div>";
                            $html .= "</td>";
                            $html .= "<td>";

                            $isselected = false;
                            if (in_array($cm->id, $processedactivity[$section->section] ?? [])) {
                                $isselected = true;
                            }

                            if ($isselected) {
                                $html .= "<h4><i class='fa fa-check text-success'></h4>";
                            } else {
                                $html .= "<h4><i class='fa fa-times text-danger'></h4>";
                            }

                            $html .= "</td>";
                            $html .= "</tr>";
                        }
                    }
                    $iteration++;
                }


                $html .= "</tbody>";
                $html .= "</table>";


                // Process identifier wheter task should be CRON or instant.
                $iscron = local_rollover_wizard_is_crontask($sourcecourse->id);

                if ($iscron) {
                    $html .= '<input type="hidden" id="rollover_process_mode" value="cron">';
                } else {
                    $html .= '<input type="hidden" id="rollover_process_mode" value="instantexecute">';
                }
                display_result(200, ['html' => $html]);
            }
            if ($mode == 'blanktemplate') {
                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $targetcourse = $sessiondata['target_course'];
                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
                $html .= "<div class='d-flex flex-column justify-content-between text-center'>";
                $selectedactivity = $sessiondata['selected_activity'];
                $processedactivity = [];

                $sourcecourse = $sessiondata['source_course'];
                $sourcecoursehtml = "";
                $viewurl = new \moodle_url('/course/view.php', ['id' => $sourcecourse->id]);
                $sourcecoursehtml = "
                <h6>Template Course : </h6>
                <div>
                <p>".$sourcecourse->fullname."</p>
                </div>";

                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $sourcecoursehtml;
                $html .= "</div>";
                $time = time();
                $html .= '<div class="form-check d-flex justify-content-center align-items-center" style="gap:20px; flex-grow:1;">';
                $html .= ' <input class="form-check-input position-static rollover-check-coursesettings"';
                $html .= ' id="rollover-wizard-coursesettings'.$time.'" data-module="coursesettings"';
                $html .= ' data-section="-1" name="rollover-wizard-cm[]" type="checkbox" value="coursesettings" style="margin-top:0;">';
                $html .= ' <label class="form-check-label" for="rollover-wizard-coursesettings'.$time.'">Import Course Settings</label></div>';
                $html .= "<div class='d-flex flex-column w-75 mx-auto' style='gap:10px;max-height: 50vh;overflow-y:scroll;'>";
                $html .= "<table class='table table-striped'>";
                $html .= "<thead>";
                $html .= "<tr>";
                $html .= "<th>Module</th>";
                $html .= "<th>Status</th>";
                $html .= "</tr>";
                $html .= "</thead>";
                $html .= "<tbody>";

                // Modules
                $coursesections = $DB->get_records('course_sections', ['course' => $sourcecourse->id], 'section ASC');
                $iteration = 1;
                $activitytypes = [];

                $coursecontext = \context_course::instance($targetcourse->id);
                $PAGE->set_context($coursecontext);

                $excludedactivitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
                $excludedactivitytypes = array_map('trim', $excludedactivitytypes);
                foreach ($coursesections as $section) {
                    $sequence = $section->sequence;
                    $html .= '<tr>';
                    $html .= '<td colspan="2"><b>'.get_section_name($sourcecourse->id, $section->section)."</b></td>";
                    $html .= '</tr>';
                    $cmids = explode(',', $sequence);
                    foreach ($cmids as $cmid) {
                        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                        if ($cm && $cm->deletioninprogress < 1) {
                            $modulerecord = $DB->get_record('modules', ['id' => $cm->module]);
                            if (!$modulerecord) {
                                continue;
                            }
                            if (empty($modulerecord->name)) {
                                continue;
                            }
                            $disabledattr = "";
                            $disabledstyle = "";
                            $disabled = false;
                            if (in_array($modulerecord->name, $excludedactivitytypes)) {
                                $disabled = true;
                                $disabledattr = "disabled";
                                $disabledstyle = "opacity: 50%;";
                            }
                            $modshortname = $modulerecord->name;
                            $modfullname = get_string('pluginname', $modshortname);
                            if (!$disabled) {
                                $activitytypes[$modshortname] = $modfullname;
                            }
                            $modlogo = $OUTPUT->image_icon('monologo', $modshortname, $modshortname);
                            $activityrecord = $DB->get_record($modshortname, ['id' => $cm->instance]);
                            $activityname = $activityrecord->name;
                            $html .= "<tr>";
                            $html .= "<td>";
                            $html .= "<div class='d-flex flex-row justify-content-start align-items-center' style='gap:25px;".$disabledstyle."'>";
                            $html .= $modlogo;
                            $html .= '<div class="d-flex flex-column text-left"><div class="text-uppercase small">'.$modshortname.'</div><div>'.$activityname.'</div></div>';
                            if ($cm->visible == 0) {
                                $html .= '<div class="my-1 d-flex align-items-center"><span class="badge badge-pill badge-warning">Hidden from students</span></div>';
                            }
                            $html .= "</div>";
                            $html .= "</td>";
                            $html .= "<td>";

                            $isselected = true;
                            if ($disabled) {
                                $isselected = false;
                            }

                            if ($isselected) {
                                $html .= "<h4><i class='fa fa-check text-success'></h4>";
                            } else {
                                $html .= "<h4><i class='fa fa-times text-danger'></h4>";
                            }

                            $html .= "</td>";
                            $html .= "</tr>";
                        }
                    }
                    $iteration++;
                }


                $html .= "</tbody>";
                $html .= "</table>";


                // process identifier wheter task should be CRON or instant.
                $iscron = local_rollover_wizard_is_crontask($sourcecourse->id);
                if ($iscron) {
                    $html .= '<input type="hidden" id="rollover_process_mode" value="cron">';
                } else {
                    $html .= '<input type="hidden" id="rollover_process_mode" value="instantexecute">';
                }

                display_result(200, ['html' => $html]);
            }
        }

        if ($step == 5) {
            $mode = required_param('mode', PARAM_TEXT);
            if ($mode == 'previouscourse') {
                // Success Message : The content import has completed successfully.
                // Fail Message : The content import did not complete due to XXXX. Please contact LXI for support.
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
            }
            if ($mode == 'blanktemplate') {
                // Success Message : The content import has completed successfully.
                // Fail Message : The content import did not complete due to XXXX. Please contact LXI for support.

                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
            }

            $html .= "<div class='d-flex justify-content-center align-items-center w-100 h-100'>";

            $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
            $targetcourse = $sessiondata['target_course'];

            $sourcecourse = $sessiondata['source_course'];

            $iscron = local_rollover_wizard_is_crontask($sourcecourse->id);
            // $is_cron = false;
            if ($iscron) {
                $taskname = 'local_rollover_wizard\task\execute_rollover';

                $task = \core\task\manager::get_scheduled_task($taskname);
                if (!$task) {
                    print_error('cannotfindinfo', 'error', $taskname);
                }
                $html .= "<div class='d-flex flex-column rollover-finish-notification'>";
                $html .= "<p>The content import will take place on ".userdate($task->get_next_run_time())."</p>";
                $html .= "</div>";
            } else {
                $html .= "<div class='d-flex flex-column rollover-finish-notification'>";
                $html .= "<p>Rolling Over Course Content...</p>";
                $html .= '<div class="progress" style="min-width: 100%;"><div class="progress-bar progress-bar-striped progress-bar-animated"';
                $html .= ' role="progressbar" style="width: 0;" id="rollover-progress-bar"></div></div>';
                $html .= "</div>";
            }

            $html .= "</div>";

            display_result(200, ['html' => $html]);
        }
    } else if ($action == 'retrievesessiondata') {
        $sessiondata = $_SESSION['local_rollover_wizard'];

        display_result(200, ['data' => $sessiondata]);
    } else if ($action == 'retrieveconfirmdialog') {
        $mode = required_param('mode', PARAM_TEXT);
        $html = '';
        if ($mode == 'instantexecute') {
            $html .= "<p>The import process will start immediately</p>";
        }
        if ($mode == 'cron') {
            $taskname = 'local_rollover_wizard\task\execute_rollover';
            $task = \core\task\manager::get_scheduled_task($taskname);
            if (!$task) {
                print_error('cannotfindinfo', 'error', $taskname);
            }
            $setting = get_config('local_rollover_wizard');
            // The selected content is over 3GB - the content import will take place on xxxx. You will receive a notification when it has completed.
            $html .= "<p>The selected content is over ".$setting->cron_size_threshold."GB ";
            $html .= "- the content import will take place on ".userdate($task->get_next_run_time()).". You will receive a notification when it has completed.</p>";
        }

        display_result(200, ['html' => $html]);
    } else if ($action == 'savesourcecourseid') {
        $sourcecourseid = required_param('sourcecourseid', PARAM_INT);
        $datakey = required_param('data_key', PARAM_INT);
        $mode = required_param('mode', PARAM_TEXT);
        $status = 403;
        $result = null;
        if ($sourcecourseid > -1) {
            if ($record = $DB->get_record('course', ['id' => $sourcecourseid])) {
                $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
                $sessiondata['source_course'] = $record;
                $sessiondata['mode'] = $mode;
                $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
                $result = $record;
                $status = 200;
            }
        } else {
            $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
            $sessiondata['source_course'] = null;
            $sessiondata['selected_activity'] = null;
            $sessiondata['mode'] = null;
            $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
            $result = null;
            $status = 200;
        }
        display_result($status, ['data' => $result]);
    } else if ($action == 'saveselectedactivity') {
        $selectedactivity = required_param('selectedactivity', PARAM_TEXT);

        $datakey = required_param('data_key', PARAM_INT);
        $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
        $result = json_decode($selectedactivity);
        $coursesettings = null;
        foreach ($result as $res) {
            if ($res->key == 'coursesettings' || $res->value == 'coursesettings') {
                $coursesettings = $res;
                break;
            }
        }
        $sessiondata['selected_activity'] = $result;
        $sessiondata['import_course_setting'] = !empty($coursesettings);
        $_SESSION['local_rollover_wizard'][$datakey] = $sessiondata;
        display_result(200, ['data' => $result]);
    } else if ($action == 'startrollover') {
        $mode = required_param('mode', PARAM_RAW);
        $datakey = required_param('data_key', PARAM_INT);
        $activity = required_param("activity", PARAM_TEXT);
        $excludedactivitytypes = (empty(trim($activity))) ? [] : json_decode($activity);
        $sessiondata = $_SESSION['local_rollover_wizard'][$datakey];
        $targetcourse = $sessiondata['target_course'];
        $sourcecourse = $sessiondata['source_course'];
        $cmids = [];
        $selectedsections = null;
        if ($mode == 'blanktemplate') {

            if (!empty($sessiondata['import_course_setting']) && $sessiondata['import_course_setting'] == true) {
                $cmids[] = 'coursesettings';
            }
            $selectedactivity = $DB->get_records('course_modules', ['course' => $sourcecourse->id]);
            foreach ($selectedactivity as $activity) {
                $cmids[] = $activity->id;
            }
        }
        if ($mode == 'previouscourse') {
            $selectedactivity = $sessiondata['selected_activity'];
            $cmids = [];
            $selectedsections = [];
            foreach ($selectedactivity as $activity) {
                if ($activity->key == 'coursesections') {
                    if (!in_array($activity->section, $selectedsections)) {
                        $selectedsections[] = $activity->section;
                    }
                    continue;
                }
                $cmids[] = $activity->value;
                if (!in_array($activity->section, $selectedsections)) {
                    $selectedsections[] = $activity->section;
                }
            }
        }
        $instantexecute = 1;
        $iscron = local_rollover_wizard_is_crontask($sourcecourse->id);
        if ($iscron) {
            $instantexecute = 0;
        }

        $taskid = time();
        $newrollover = new \stdClass();
        $newrollover->taskid = $taskid;
        $newrollover->rollovermode = $mode;
        $newrollover->instantexecute = $instantexecute;
        $newrollover->sourcecourseid = $sourcecourse->id;
        $newrollover->targetcourseid = $targetcourse->id;
        $newrollover->templatecourse = null;
        $newrollover->status = ROLLOVER_WIZARD_NOTSTARTED;
        $newrollover->userid = $USER->id;
        $newrollover->note = '';
        $newrollover->cmids = json_encode($cmids);
        $newrollover->selectedsections = !empty($selectedsections) ? json_encode($selectedsections) : null;
        $newrollover->rolledovercmids = null;
        $newrollover->excludedactivitytypes = json_encode($excludedactivitytypes);
        $newrollover->timecreated = time();
        $newrollover->timeupdated = time();
        $DB->insert_record('local_rollover_wizard_log', $newrollover);
       
        if (!$iscron) {
            $command = "php " . __DIR__ . "/rollover_wizard_background.php $taskid > /dev/null 2>&1 &";
            exec($command);
        }
        display_result(200, ['taskid' => $taskid]);
    } else if ($action == 'runrollovertask') {
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
        \core\task\manager::run_from_cli($task);

        exit;

    } else if ($action == 'checkrolloverstate') {
        $taskid = required_param('taskid', PARAM_INT);
        $record = $DB->get_record('local_rollover_wizard_log', ['taskid' => $taskid]);
        $percentage = 1;
        $message = "";
        $status = $record->status;
        if ($status != ROLLOVER_WIZARD_INPROGRESS && ($status == ROLLOVER_WIZARD_PARTLYSUCCESS || $status == ROLLOVER_WIZARD_UNSUCCESS)) {
            // $link = get_string('lxi_support_link', 'local_rollover_wizard');
            $text = get_string('wizard_support_text', 'local_rollover_wizard');
            $link = get_string('wizard_support_link', 'local_rollover_wizard');
            $comp = get_string('wizard_support_company', 'local_rollover_wizard');
            $link = "<a href='".$link."' target='_blank'>".$comp."</a>";
            // $message = 'The content import did not complete due to : <br>'.$record->note.'<br><p>Please contact <a href="'.$link.'" target="_blank">LXI</a> for support</p>';
            $message = str_replace('{NOTE}', $record->note, $text);
            $message = str_replace('{LINK}', $link, $message);
        }

        if ($status == ROLLOVER_WIZARD_SUCCESS) {
            $message = 'The content import has completed successfully';
        }
        display_result(200, ['taskid' => $taskid, 'percentage' => $percentage, 'rolloverstatus' => $status, 'message' => $message]);
    } else if ($action == 'retrievecourses') {
        $categoryid = required_param('categoryid', PARAM_INT);
        $courseid = optional_param('courseid', 0, PARAM_INT);

        $mode = required_param('mode', PARAM_TEXT);
        if ($mode == 'previouscourse') {
            if ($categoryid == 0 && $courseid > 0) {
                $allowedcategories = [];

                if (!is_siteadmin($USER)) {
                    $mycourses = enrol_get_my_courses();
                    foreach ($mycourses as $course) {
                        $categoryid = $course->category;
                        $superparent = false;
                        do {
                            $currentcategoryid = $categoryid;
                            $category = $DB->get_record('course_categories', ['id' => $currentcategoryid]);
                            if ($category->depth == 1 || $category->parent == 0) {
                                $superparent = true;
                                $categoryid = $category->id;
                                $allowedcategories[] = $categoryid;
                            } else {
                                $allowedcategories[] = $categoryid;
                                $categoryid = $category->parent;
                            }
                        } while (!$superparent);
                    }
                }

                $categories = [];
                $curcategory = core_course_category::top();
                $curcategories = $curcategory->get_children();
                foreach ($curcategories as $category) {
                    $courses = $DB->get_records('course', ['category' => $category->id]);
                    $hasenrol = false;
                    if (!is_siteadmin($USER) && !in_array($category->id, $allowedcategories)) {
                        continue;
                    }
                    $obj = new stdClass;
                    $obj->id = $category->id;
                    $obj->name = $category->name;
                    $categories[] = $obj;
                }

                $courses = [];
                $curcourses = $curcategory->get_courses(['recursive' => false]);
                foreach ($curcourses as $course) {
                    $context = \context_course::instance($course->id);
                    $enrolled = is_enrolled($context, $USER);
                    if (is_siteadmin($USER)) {
                        $enrolled = true;
                    }
                    if (!$enrolled) {
                        continue;
                    }
                    $obj = new stdClass;
                    $obj->id = $course->id;
                    $obj->fullname = $course->fullname;
                    $obj->urlviewcourse = (new moodle_url($CFG->wwwroot . "/course/view.php", ["id" => $course->id]))->out();
                    $courses[] = $obj;
                }

                echo json_encode(['categories' => $categories, 'courses' => $courses]);
                exit;
            } else {
                $directparentid = $DB->get_field('course_categories', 'parent', ['id' => $categoryid]);
                // $parent_category = $DB->get_record('course_categories', ['id' => $categoryid]);
                // $directparentid = $parent_category->parent;

                $course = $DB->get_record('course', ['id' => $courseid]);

                $categories = [];

                $obj = new stdClass;
                $obj->id = $directparentid;
                $obj->name = '..';
                $categories[] = $obj;

                $curcategory = core_course_category::get($categoryid);
                $curcategories = $curcategory->get_children();

                foreach ($curcategories as $category) {
                    $courses = $DB->get_records('course', ['category' => $category->id]);
                    $hasenrol = false;
                    $obj = new stdClass;
                    $obj->id = $category->id;
                    $obj->name = $category->name;
                    $categories[] = $obj;
                }

                $courses = [];
                $curcourses = $curcategory->get_courses(['recursive' => false]);
                foreach ($curcourses as $course) {
                    $context = \context_course::instance($course->id);
                    $enrolled = is_enrolled($context, $USER);
                    if (is_siteadmin($USER)) {
                        $enrolled = true;
                    }
                    if (!$enrolled) {
                        continue;
                    }
                    $obj = new stdClass;
                    $obj->id = $course->id;
                    $obj->fullname = $course->fullname;
                    $obj->urlviewcourse = (new moodle_url($CFG->wwwroot . "/course/view.php", ["id" => $course->id]))->out();
                    $courses[] = $obj;
                }

                echo json_encode(['categories' => $categories, 'courses' => $courses]);
                exit;
            }
        }

        if ($mode == 'blanktemplate') {
            $course = $DB->get_record('course', ['id' => $courseid]);
            $setting = get_config('local_rollover_wizard');
            $templatecategory = !empty($setting->identifier_template_course) ? $setting->identifier_template_course : 0;

            $categories = [];
            if (!empty($parentcategory->parent)) {
                $obj = new stdClass;
                $obj->id = $directparentid;
                $obj->name = '..';
                $categories[] = $obj;
            }

            $curcategory = core_course_category::get($templatecategory);
            $curcategories = $curcategory->get_children();

            foreach ($curcategories as $category) {
                $obj = new stdClass;
                $obj->id = $category->id;
                $obj->name = $category->name;
                $categories[] = $obj;
            }

            $courses = [];
            $curcourses = $curcategory->get_courses(['recursive' => true]);
            foreach ($curcourses as $course) {
                $obj = new stdClass;
                $obj->id = $course->id;
                $obj->fullname = $course->fullname;
                $obj->urlviewcourse = (new moodle_url($CFG->wwwroot . "/course/view.php", ["id" => $course->id]))->out();
                $courses[] = $obj;
            }

            echo json_encode(['categories' => $categories, 'courses' => $courses]);
            exit;
        }
    } else if ($action == 'searchcourses') {
        $search = required_param('search', PARAM_TEXT);
        $courseid = optional_param('courseid', 0, PARAM_INT);

        $mode = required_param('mode', PARAM_TEXT);

        $setting = get_config('local_rollover_wizard');
        $templatecategory = !empty($setting->identifier_template_course) ? $setting->identifier_template_course : 0;

        $categories = [];
        $course = $DB->get_record('course', ['id' => $courseid]);
        $categoryid = 0;
        if ($mode == 'previouscourse') {
            // $categoryid = (!empty($courseid)) ? $course->category : 0;
            $categoryid = 0;
        } else {
            $categoryid = $templatecategory;
        }
        if (!empty($courseid)) {
            $superparent = false;
            $supercategoryid = 0;
            if ($categoryid > 0) {
                do {
                    $currentcategoryid = $categoryid;
                    $category = $DB->get_record('course_categories', ['id' => $currentcategoryid]);
                    if ($category->depth == 1 || $category->parent == 0) {
                        $superparent = true;
                        $supercategoryid = $category->id;
                    } else {
                        $categoryid = $category->parent;
                    }
                    if (!in_array($category->id, $categories)) {
                        $categories[] = $category->id;
                    }
                } while (!$superparent);
            }
            $curcategory = core_course_category::get($supercategoryid);
            $curcategories = $curcategory->get_all_children_ids();
            $categories = array_merge($categories, $curcategories);
        }

        $likequery1 = $DB->sql_like('fullname', ':fullname', false);
        $likequery2 = $DB->sql_like('shortname', ':shortname', false);
        $sql = "SELECT * FROM {course} WHERE ({$likequery1} OR {$likequery2}) AND id > 1";
        $params =
            [
            'fullname' => '%' . $DB->sql_like_escape($search) . '%',
            'shortname' => '%' . $DB->sql_like_escape($search) . '%',
        ];
        if (!empty($categories)) {
            list($insql, $inparams) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
            $params = array_merge($params, $inparams);
            $sql .= " AND category $insql";
        }
        $result = $DB->get_records_sql(
            $sql,
            $params
        );
        $courses = [];
        foreach ($result as $course) {
            if ($mode == 'previouscourse') {
                $context = \context_course::instance($course->id);
                $enrolled = is_enrolled($context, $USER);
                if (is_siteadmin($USER)) {
                    $enrolled = true;
                }
                if (!$enrolled) {
                    continue;
                }
            }
            $obj = new stdClass;
            $obj->id = $course->id;
            $obj->fullname = $course->fullname;
            $obj->urlviewcourse = (new moodle_url($CFG->wwwroot . "/course/view.php", ["id" => $course->id]))->out();
            $courses[] = $obj;
        }
        echo json_encode(['categories' => [], 'courses' => $courses]);
        exit;
    } else if ($action == 'verifycourse') {
        $sourcecourseid = required_param('sourcecourseid', PARAM_INT);
        $targetcourseid = required_param('targetcourseid', PARAM_INT);
        $mode = required_param('mode', PARAM_TEXT);
        $warnings = local_rollover_wizard_verify_course($sourcecourseid, $targetcourseid, false);
        echo $warnings;
        exit;
    }
}

echo "";
exit;
