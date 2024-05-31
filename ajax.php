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

global $CFG, $DB, $USER, $PAGE;

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
    if ($action == 'renderform') {
        $setting = get_config('local_rollover_wizard');
        $step = required_param('step', PARAM_INT);
        if($step == 1){
            // $mform = new \MoodleQuickForm('import_content_form','POST','#', $target = '', ['id' => 'import_content_form']);
            // // $radioarray=array();
            // $mform->addElement('hidden', 'rollover_step', $step);
            // $mform->addElement('radio', 'content_option', '', get_string('content_option1','local_rollover_wizard'), 'blanktemplate');
            // $mform->addElement('radio', 'content_option', '', get_string('content_option2','local_rollover_wizard'), 'previouscourse');
            
            $html = '<h2>Import Content</h2>'.'<p>Do you want to start from a blank template or import content from a previous course ?</p>';
            $warning = local_rollover_wizard_verify_course(null, ($_SESSION['local_rollover_wizard'][$data_key]['target_course'])->id, false);
            if(!empty($warning) && $warning !== '&nbsp;'){
                $warning = "<div class='alert alert-warning'>".$warning."</div>";
            }
            $html.='<div class="alert-container">'.$warning.'</div>';
            // $html.= $mform->toHtml();
            $html .= '<div class="form-group row  fitem  ">
                        <div class="col-md-3 col-form-label pb-0 pt-0">
                        </div>
                        <div class="col-md-9 checkbox">
                            <div class="form-check d-flex">
                                    <label class="form-check-label">
                                        
                                        <input type="radio" class="form-check-input" name="content_option" id="id_content_option_blanktemplate" value="blanktemplate">
                                            Import a Blank Template
                                    </label>
                                    <div class="ml-2 d-flex align-items-center align-self-start">
                                        
                                    </div>
                            </div>
                            <div class="form-control-feedback invalid-feedback" id="id_error_content_option_blanktemplate">
                                
                            </div>
                        </div>
                    </div>';

            $html .= '<div class="form-group row  fitem  ">
                        <div class="col-md-3 col-form-label pb-0 pt-0">
                        </div>
                        <div class="col-md-9 checkbox">
                            <div class="form-check d-flex">
                                    <label class="form-check-label">
                                        
                                        <input type="radio" class="form-check-input" name="content_option" id="id_content_option_previouscourse" value="previouscourse">
                                            Import content from previous course you were enrolled in
                                    </label>
                                    <div class="ml-2 d-flex align-items-center align-self-start">
                                        
                                    </div>
                            </div>
                            <div class="form-control-feedback invalid-feedback" id="id_error_content_option_previouscourse">
                                
                            </div>
                        </div>
                    </div>';
            display_result(200,['html' => $html]);
        }
        if($step == 2){
            $mode = required_param('mode', PARAM_TEXT);
            if($mode == 'previouscourse'){
                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $target_course = $session_data['target_course'];
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>';
                $html .='<div class="alert-container"></div>';
                // $html .= "<div class='d-flex justify-content-between text-center'>";
                $html .= "<div class='row text-center'>";
                
                $source_course = null;
                $source_course_html = "";
                // Preg Match source course here
                if(!empty($target_course->idnumber)){
                    $matches = [];
                    preg_match('/(.*?)([0-9]{6})/', $target_course->idnumber, $matches);
                    $target_academic_year = intval((empty($matches[2]) ? 0 : $matches[2]));
    
                    if (!empty($target_academic_year)) {
                        $courseids = [];
                        $academic_years = [];
                        $courses = $DB->get_records('course');
                        foreach($courses as $course){
                            $codeidnumber = preg_replace('/(.*?)([0-9]{6})/', '$1', $course->idnumber);
                            if(!empty($codeidnumber) && $codeidnumber == $matches[1]){
                                $acadidnumber = preg_replace('/(.*?)([0-9]{6})/', '$2', $course->idnumber);
                                $acadidnumber = intval($acadidnumber);
                                $courseids[$acadidnumber] = $course->id;
                                $academic_years[] = $acadidnumber;
                            }
                        }
                        rsort($academic_years);
                        list($target_start_year, $target_end_year) = preg_split('/(?<=.{4})/', $matches[2], 2);
                        $matched_courseid = null;
                        foreach($academic_years as $year){
                            list($source_start_year, $source_end_year) = preg_split('/(?<=.{4})/', (string) $year, 2);
                            
                            if($source_start_year < $target_start_year && $source_end_year < $target_end_year){
                                $matched_courseid = $courseids[$year];
                                break;
                            }
                        }
                        if(!empty($matched_courseid)){
                            $session_data['source_course'] = $DB->get_record('course', ['id' => $matched_courseid]);
    
                        }
                    }
                }

                if(!empty($session_data['source_course'])){
                    $source_course = $session_data['source_course'];
                }
                if(empty($source_course)){
                    $session_data['source_course'] = null;
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='#' class='previewcourse_source_course_link rollover-disabled-link' target='_blank'>Source course not selected yet</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                else{
                    $session_data['source_course'] = $source_course;
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $source_course->id]);
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank' data-courseid='".$source_course->id."'>".$source_course->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= $source_course_html;
                $html .= "</div></div>";
                // Separator
                $html .= "<div class='col-2'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h4><i class='fa fa-arrow-right'></i></h4>"; 
                $html .= "</div></div>";
                // Target Course
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h6>This Course : </h6>".$target_course->fullname; 
                $html .= "</div></div>";
                // $html+= "<div><h4>".Previous."</h4></div>";
                $html .= "</div>";
                display_result(200,['html' => $html]);
            }
            
            if($mode == 'blanktemplate'){
                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $target_course = $session_data['target_course'];
                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>';
                $html .='<div class="alert-container"></div>';
                $html .= "<div class='d-flex justify-content-center text-center'>";

                
                $source_course = null;
                $source_course_html = "";
                // Preg Match source course here
                if(!empty($session_data['source_course'])){
                    $source_course = $session_data['source_course'];
                }
                if(empty($source_course)){
                    $session_data['source_course'] = null;
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='#' class='previewcourse_source_course_link' target='_blank'>Source course not selected yet</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                else{
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $source_course->id]);
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank'>".$source_course->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $source_course_html = "
                <h6>Choose a template : </h6>";

                $source_course_html .= "<select class='form-control' id='selected_template_course'><option selected style='display:none !important;' value=''>Select Template Course</option>";
 
                $setting = get_config('local_rollover_wizard');
                $template_category = !empty($setting->identifier_template_course) ? $setting->identifier_template_course : 0;
                
                $curcategory = core_course_category::get($template_category);
                $curcourses = $curcategory->get_courses(['recursive' => false]);
                foreach ($curcourses as $course) {
                    $source_course_html .= "<option value='".$course->id."'>".$course->fullname."</option>";
                }

                $source_course_html .= "</select>";
                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $source_course_html;
                $html .= "</div>";
                
                $html .= "</div>";
                display_result(200,['html' => $html]);
            }
        }
        if($step == 3){
            $mode = required_param('mode', PARAM_TEXT);
            if($mode == 'previouscourse'){
                
                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $target_course = $session_data['target_course'];
                
                $source_course = null;
                $source_course_html = "";
                $warning_html = "";
                // Preg Match source course here
                if(!empty($session_data['source_course'])){
                    $source_course = $session_data['source_course'];
                }

                $warning_html = local_rollover_wizard_verify_course($source_course->id, $target_course->id, false);
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
                $html .= "<div class='d-flex flex-column justify-content-center text-center'>";
                
                if(empty($source_course)){
                    display_result(403,['html' => null]);
                }
                else{
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $source_course->id]);
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Previous Course you were enrolled in : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank'>".$source_course->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $source_course_html;
                $html .= "</div>";
                $html .= "<div id='rollover-activity-container'>";
                // Buttons
                $html .= "<div class='d-flex justify-content-between my-5'>";
                $html .= "<div class='filter-button-container'><button type='button' class='btn btn-primary' id='btn-select-filter'>Select Activity Types</button></div>";
                $html .= "<div class='select-button-container'><button type='button' class='btn btn-primary' id='btn-select-all'>Select All</button> | <button type='button' class='btn btn-secondary' id='btn-deselect-all'>Deselect All</button></div>"; 
                $html .= "</div>";
                
                // Modules
                $html .= "<div class='d-flex flex-column w-75 mx-auto' style='gap:10px;max-height: 40vh;overflow-y:scroll;'>";
                $course_sections = $DB->get_records('course_sections', ['course' => $source_course->id],'section ASC');
                $iteration = 1;
                $activity_types = [];
                
                $coursecontext = \context_course::instance($target_course->id);
                $PAGE->set_context($coursecontext);
                
                $excluded_activitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
                $excluded_activitytypes = array_map('trim', $excluded_activitytypes);
                foreach($course_sections as $section){
                    $sequence = $section->sequence;
                    $html .= '<div class="card">';
                    $html .= '<div class="card-header " style="cursor:pointer;" data-toggle="collapse" data-target="#collapsecontainer'.$iteration.'">';
                    $html .= '<div class="d-flex justify-content-between w-80 text-center">';
                    $html .= '<b>'.get_section_name($source_course->id, $section->section)."</b>";
                    $html .= '<i class="collapse-toggle"></i>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '<div class="card-body collapse show" id="collapsecontainer'.$iteration.'">';
                    $html .= '<div class="d-flex flex-column" style="gap:15px;">';
                    $cmids = explode(',',$sequence);
                    foreach($cmids as $cmid){
                        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                        if($cm && $cm->deletioninprogress < 1){
                            $module_record = $DB->get_record('modules', ['id' => $cm->module]);
                            if(!$module_record){
                                continue;
                            }
                            if(empty($module_record->name)){
                                continue;
                            }
                            $disabled_attr = "";
                            $disabled_style = "";
                            $disabled = false;
                            if(in_array($module_record->name, $excluded_activitytypes)){
                                $disabled = true;
                                $disabled_attr = "disabled";
                                $disabled_style = "opacity: 50%;";
                            }
                            $modshortname = $module_record->name;
                            $modfullname = get_string('pluginname', $modshortname);
                            if(!$disabled){
                                $activity_types[$modshortname] = $modfullname;
                            }
                            $modlogo = $OUTPUT->image_icon('monologo', $modshortname, $modshortname);
                            $activity_record = $DB->get_record($modshortname, ['id' => $cm->instance]);
                            $activity_name = $activity_record->name;
                            $html .= "<div class='d-flex flex-row justify-content-start align-items-center' style='gap:25px;".$disabled_style."'>";
                            $html .= '<div class="form-check"><input class="form-check-input position-static rollover-check-'.$modshortname.'" '.$disabled_attr.' data-section="'.$section->section.'" data-module="'.$modshortname.'" name="rollover-wizard-cm[]" type="checkbox" value="'.$cm->id.'"></div>';
                            $html .= $modlogo;
                            $html .= '<div class="d-flex flex-column text-left"><div class="text-uppercase small">'.$modshortname.'</div><div>'.$activity_name.'</div></div>';
                            if($cm->visible == 0){
                                $html .='<div class="my-1 d-flex align-items-center"><span class="badge badge-pill badge-warning">Hidden from students</span></div>';
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
                foreach($activity_types as $key => $value){
                    $temp[] = [
                        'key' => $key,
                        'value' => $value
                    ];
                }
                $activity_types = $temp;
                display_result(200,['html' => $html, 'activity_types' => $activity_types]);
            }
            
            if($mode == 'blanktemplate'){
                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $target_course = $session_data['target_course'];
                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>';
                $html .='<div class="alert-container"></div>';
                // $html .= "<div class='d-flex justify-content-between text-center'>";
                $html .= "<div class='row text-center'>";
                
                $source_course = null;
                $source_course_html = "";
                // Preg Match source course here
                if(!empty($session_data['source_course'])){
                    $source_course = $session_data['source_course'];
                }
                if(empty($source_course)){
                    $session_data['source_course'] = null;
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Template Course : </h6>
                    <div>
                    <a href='#' class='previewcourse_source_course_link' target='_blank'>Source course not selected yet</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                else{
                    $viewurl = new \moodle_url('/course/view.php', ['id' => $source_course->id]);
                    $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                    $source_course_html = "
                    <h6>Template Course : </h6>
                    <div>
                    <a href='".$viewurl->out()."' class='previewcourse_source_course_link' target='_blank'>".$source_course->fullname."</a>
                    &nbsp;
                    <i class='fa fa-pencil changecoursebutton' style='cursor:pointer;'></i>
                    </div>";
                }
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= $source_course_html;
                $html .= "</div></div>";
                // Separator
                $html .= "<div class='col-2'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h4><i class='fa fa-arrow-right'></i></h4>"; 
                $html .= "</div></div>";
                // Target Course
                $html .= "<div class='col-5'><div class='d-flex flex-column justify-content-center'>";
                $html .= "<h6>This Course : </h6>".$target_course->fullname; 
                $html .= "</div></div>";
                // $html+= "<div><h4>".Previous."</h4></div>";
                $html .= "</div>";
                display_result(200,['html' => $html]);
            }
        }
        
        if($step == 4){
            $mode = required_param('mode', PARAM_TEXT);
            if($mode == 'previouscourse'){

                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $target_course = $session_data['target_course'];
                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
                $html .= "<div class='d-flex flex-column justify-content-between text-center'>";
                $selected_activity = $session_data['selected_activity'];
                $processed_activity = [];
                foreach($selected_activity as $activity){
                    $processed_activity[$activity->section][] = $activity->value;
                }
                $source_course = $session_data['source_course'];
                $source_course_html = "";
                $viewurl = new \moodle_url('/course/view.php', ['id' => $source_course->id]);
                $source_course_html = "
                <h6>Previous Course you were enrolled in : </h6>
                <div>
                <p>".$source_course->fullname."</p>
                </div>";

                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $source_course_html;
                $html .= "</div>";
                
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
                $course_sections = $DB->get_records('course_sections', ['course' => $source_course->id],'section ASC');
                $iteration = 1;
                $activity_types = [];
                

                $coursecontext = \context_course::instance($target_course->id);
                $PAGE->set_context($coursecontext);

                $excluded_activitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
                $excluded_activitytypes = array_map('trim', $excluded_activitytypes);
                foreach($course_sections as $section){
                    $sequence = $section->sequence;
                    $html .= '<tr>';
                    $html .= '<td colspan="2"><b>'.get_section_name($source_course->id, $section->section)."</b></td>";
                    $html .= '</tr>';
                    $cmids = explode(',',$sequence);
                    foreach($cmids as $cmid){
                        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                        if($cm && $cm->deletioninprogress < 1){
                            $module_record = $DB->get_record('modules', ['id' => $cm->module]);
                            if(!$module_record){
                                continue;
                            }
                            if(empty($module_record->name)){
                                continue;
                            }
                            $disabled_attr = "";
                            $disabled_style = "";
                            $disabled = false;
                            if(in_array($module_record->name, $excluded_activitytypes)){
                                $disabled = true;
                                $disabled_attr = "disabled";
                                $disabled_style = "opacity: 50%;";
                            }
                            $modshortname = $module_record->name;
                            $modfullname = get_string('pluginname', $modshortname);
                            if(!$disabled){
                                $activity_types[$modshortname] = $modfullname;
                            }
                            $modlogo = $OUTPUT->image_icon('monologo', $modshortname, $modshortname);
                            $activity_record = $DB->get_record($modshortname, ['id' => $cm->instance]);
                            $activity_name = $activity_record->name;
                            $html .= "<tr>";
                            $html .= "<td>";
                            $html .= "<div class='d-flex flex-row justify-content-start align-items-center' style='gap:25px;".$disabled_style."'>";
                            $html .= $modlogo;
                            $html .= '<div class="d-flex flex-column text-left"><div class="text-uppercase small">'.$modshortname.'</div><div>'.$activity_name.'</div></div>';
                            if($cm->visible == 0){
                                $html .='<div class="my-1 d-flex align-items-center"><span class="badge badge-pill badge-warning">Hidden from students</span></div>';
                            }
                            $html .= "</div>";
                            $html .= "</td>";
                            $html .= "<td>";

                            $is_selected = false;
                            if(in_array($cm->id,$processed_activity[$section->section] ?? [])){
                                $is_selected = true;
                            }

                            if($is_selected){
                                $html .= "<h4><i class='fa fa-check text-success'></h4>";
                            }
                            else{
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
                
                
                // process identifier wheter task should be CRON or instant
                $is_cron = local_rollover_wizard_is_crontask($source_course->id);
                // $is_cron = false; 
                if($is_cron){
                    $html .= '<input type="hidden" id="rollover_process_mode" value="cron">';
                }
                else{
                    $html .= '<input type="hidden" id="rollover_process_mode" value="instantexecute">';
                }
                
                display_result(200,['html' => $html]);
            }
            if($mode == 'blanktemplate'){

                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $target_course = $session_data['target_course'];
                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
                $html .= "<div class='d-flex flex-column justify-content-between text-center'>";
                $selected_activity = $session_data['selected_activity'];
                $processed_activity = [];

                $source_course = $session_data['source_course'];
                $source_course_html = "";
                $viewurl = new \moodle_url('/course/view.php', ['id' => $source_course->id]);
                $source_course_html = "
                <h6>Template Course : </h6>
                <div>
                <p>".$source_course->fullname."</p>
                </div>";

                $html .= "<div class='d-flex flex-column justify-content-center'>";
                $html .= $source_course_html;
                $html .= "</div>";
                
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
                $course_sections = $DB->get_records('course_sections', ['course' => $source_course->id],'section ASC');
                $iteration = 1;
                $activity_types = [];
                
                $coursecontext = \context_course::instance($target_course->id);
                $PAGE->set_context($coursecontext);

                $excluded_activitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
                $excluded_activitytypes = array_map('trim', $excluded_activitytypes);
                foreach($course_sections as $section){
                    $sequence = $section->sequence;
                    $html .= '<tr>';
                    $html .= '<td colspan="2"><b>'.get_section_name($source_course->id, $section->section)."</b></td>";
                    $html .= '</tr>';
                    $cmids = explode(',',$sequence);
                    foreach($cmids as $cmid){
                        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                        if($cm && $cm->deletioninprogress < 1){
                            $module_record = $DB->get_record('modules', ['id' => $cm->module]);
                            if(!$module_record){
                                continue;
                            }
                            if(empty($module_record->name)){
                                continue;
                            }
                            $disabled_attr = "";
                            $disabled_style = "";
                            $disabled = false;
                            if(in_array($module_record->name, $excluded_activitytypes)){
                                $disabled = true;
                                $disabled_attr = "disabled";
                                $disabled_style = "opacity: 50%;";
                            }
                            $modshortname = $module_record->name;
                            $modfullname = get_string('pluginname', $modshortname);
                            if(!$disabled){
                                $activity_types[$modshortname] = $modfullname;
                            }
                            $modlogo = $OUTPUT->image_icon('monologo', $modshortname, $modshortname);
                            $activity_record = $DB->get_record($modshortname, ['id' => $cm->instance]);
                            $activity_name = $activity_record->name;
                            $html .= "<tr>";
                            $html .= "<td>";
                            $html .= "<div class='d-flex flex-row justify-content-start align-items-center' style='gap:25px;".$disabled_style."'>";
                            $html .= $modlogo;
                            $html .= '<div class="d-flex flex-column text-left"><div class="text-uppercase small">'.$modshortname.'</div><div>'.$activity_name.'</div></div>';
                            if($cm->visible == 0){
                                $html .='<div class="my-1 d-flex align-items-center"><span class="badge badge-pill badge-warning">Hidden from students</span></div>';
                            }
                            $html .= "</div>";
                            $html .= "</td>";
                            $html .= "<td>";

                            $is_selected = true;
                            if($disabled){
                                $is_selected = false;
                            }

                            if($is_selected){
                                $html .= "<h4><i class='fa fa-check text-success'></h4>";
                            }
                            else{
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
                
                
                // process identifier wheter task should be CRON or instant
                $is_cron = local_rollover_wizard_is_crontask($source_course->id);
                // $is_cron = false;
                if($is_cron){
                    $html .= '<input type="hidden" id="rollover_process_mode" value="cron">';
                }
                else{
                    $html .= '<input type="hidden" id="rollover_process_mode" value="instantexecute">';
                }
                
                display_result(200,['html' => $html]);
            }
        }

        if($step == 5){
            $mode = required_param('mode', PARAM_TEXT);
            if($mode == 'previouscourse'){
                // Success Message : The content import has completed successfully
                // Fail Message : The content import did not complete due to XXXX. Please contact LXI for support

                $html = '<h2 class="text-center">Import from previous course</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
            }
            if($mode == 'blanktemplate'){

                // Success Message : The content import has completed successfully
                // Fail Message : The content import did not complete due to XXXX. Please contact LXI for support

                $html = '<h2 class="text-center">Import a blank template</h2>'
                .'<p></p>'
                .'<div class="alert-container"></div>';
            }
            
            $html .= "<div class='d-flex justify-content-center align-items-center w-100 h-100'>";

            $session_data = $_SESSION['local_rollover_wizard'][$data_key];
            $target_course = $session_data['target_course'];

            $source_course = $session_data['source_course'];

            $is_cron = local_rollover_wizard_is_crontask($source_course->id);
            // $is_cron = false; 
            if($is_cron){
                $taskname = 'local_rollover_wizard\task\execute_rollover';
    
                $task = \core\task\manager::get_scheduled_task($taskname);
                if (!$task) {
                    print_error('cannotfindinfo', 'error', $taskname);
                }
                $html .= "<div class='d-flex flex-column rollover-finish-notification'>";           
                $html .= "<p>The content import will take place on ".userdate($task->get_next_run_time())."</p>";
                $html .= "</div>";
            }
            else{
                $html .= "<div class='d-flex flex-column rollover-finish-notification'>";                
                $html .= "<p>Rolling Over Course Content...</p>";
                $html .= '<div class="progress" style="min-width: 100%;"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0;" id="rollover-progress-bar"></div></div>';
                $html .= "</div>";
            }
            
            $html .= "</div>";

            display_result(200,['html' => $html]);
        }
    }
    else if ($action == 'retrievesessiondata'){
        $session_data = $_SESSION['local_rollover_wizard'];
        
        display_result(200,['data' => $session_data]);
    }
    else if ($action == 'retrieveconfirmdialog'){
        $mode = required_param('mode', PARAM_TEXT);
        $html = '';
        if($mode == 'instantexecute'){
            $html .="<p>The import process will start immediately</p>";
        }
        if($mode == 'cron'){
            $taskname = 'local_rollover_wizard\task\execute_rollover';

            $task = \core\task\manager::get_scheduled_task($taskname);
            if (!$task) {
                print_error('cannotfindinfo', 'error', $taskname);
            }
            $setting = get_config('local_rollover_wizard');
            // The selected content is over 3GB - the content import will take place on xxxx. You will receive a notification when it has completed.
            $html .="<p>The selected content is over ".$setting->cron_size_threshold."GB - the content import will take place on ".userdate($task->get_next_run_time()).". You will receive a notification when it has completed.</p>";
        }

        display_result(200,['html' => $html]);
    }
    else if($action == 'savesourcecourseid'){
        $source_courseid = required_param('sourcecourseid', PARAM_INT);
        $data_key = required_param('data_key', PARAM_INT);
        $mode = required_param('mode', PARAM_TEXT);
        $status = 403;
        $result = null;
        // -1 mean deselect source course
        if($source_courseid > -1){
            if($record = $DB->get_record('course',['id' => $source_courseid])){
                $session_data = $_SESSION['local_rollover_wizard'][$data_key];
                $session_data['source_course'] = $record;
                $session_data['mode'] = $mode;
                $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
                $result = $record;
                $status = 200;
            }
        }
        else{
            $session_data = $_SESSION['local_rollover_wizard'][$data_key];
            $session_data['source_course'] = null;
            $session_data['selected_activity'] = null;
            $session_data['mode'] = null;
            $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
            $result = null;
            $status = 200;
        }
        display_result($status,['data' => $result]);
    }
    else if($action == 'saveselectedactivity'){
        $selected_activity = required_param('selectedactivity', PARAM_TEXT);
        $data_key = required_param('data_key', PARAM_INT);
        $session_data = $_SESSION['local_rollover_wizard'][$data_key];
        $result = json_decode($selected_activity);
        $session_data['selected_activity'] = $result;
        $_SESSION['local_rollover_wizard'][$data_key] = $session_data;
        display_result(200,['data' => $result]);
    }
    else if($action == 'startrollover'){
        $mode = required_param('mode', PARAM_TEXT);
        $data_key = required_param('data_key', PARAM_INT);
        $setting = get_config('local_rollover_wizard');

        $excluded_activitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
        $excluded_activitytypes = array_map('trim', $excluded_activitytypes);

        $session_data = $_SESSION['local_rollover_wizard'][$data_key];
        $target_course = $session_data['target_course'];
        $source_course = $session_data['source_course'];
        
        $cmids = [];
        if($mode == 'blanktemplate'){
            $selected_activity = $DB->get_records('course_modules', ['course' => $source_course->id]);
            foreach($selected_activity as $activity){
                $cmids[] = $activity->id;
            }
        }
        if($mode == 'previouscourse'){
            $selected_activity = $session_data['selected_activity'];
            $cmids = [];
            foreach($selected_activity as $activity){
                $cmids[] = $activity->value;
            }
        }

        $instantexecute = 1;
        
        $is_cron = local_rollover_wizard_is_crontask($source_course->id);
        // $is_cron = false; 
        if($is_cron){
            $instantexecute = 0;
        }

        $taskid = time();
        $newrollover = new \stdClass();
        $newrollover->taskid = $taskid;
        $newrollover->rollovermode = $mode;
        $newrollover->instantexecute = $instantexecute;
        $newrollover->sourcecourseid = $source_course->id;
        $newrollover->targetcourseid = $target_course->id;
        $newrollover->templatecourse = null;
        $newrollover->status = ROLLOVER_WIZARD_NOTSTARTED;
        $newrollover->userid = $USER->id;
        $newrollover->note = '';
        $newrollover->cmids = json_encode($cmids);
        $newrollover->rolledovercmids = null;
        $newrollover->excludedactivitytypes = json_encode($excluded_activitytypes);
        $newrollover->timecreated = time();
        $newrollover->timeupdated = time();
        $DB->insert_record('local_rollover_wizard_log', $newrollover);

        if(!$is_cron){
            $plugin_name = 'local_rollover_wizard';
            $rollingid_config = 'taskid';
            set_config($rollingid_config, $taskid, $plugin_name);
        }
        
        display_result(200,['taskid' => $taskid]);
    }
    else if ($action == 'runrollovertask'){

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

        exit;

    }
    else if ($action == 'checkrolloverstate') {

        $taskid = required_param('taskid', PARAM_INT);

        $record = $DB->get_record('local_rollover_wizard_log', ['taskid' => $taskid]);
        $cmids = json_decode($record->cmids);
        $rolledovercmids = explode(',', $record->rolledovercmids);
        $total = count($cmids);
        $done = count($rolledovercmids);
        $percentage = 1;
        if($done > 0){
            $percentage = ($done / $total) * 100;
        }

        $status = $record->status;
        $message = 'The content import has completed successfully';
        display_result(200,['taskid' => $taskid, 'percentage' => $percentage,'rolloverstatus' => $status, 'message' => $message]);
    }
    else if ($action == 'retrievecourses') {
        $categoryid = required_param('categoryid', PARAM_INT);
        $courseid = optional_param('courseid', 0, PARAM_INT);
        
        $mode = required_param('mode', PARAM_TEXT);
        if($mode == 'previouscourse'){
            if ($categoryid == 0 && $courseid > 0) {
                $allowedcategories = [];
                
                if(!is_siteadmin($USER)){
                    $my_courses = enrol_get_my_courses();
                    foreach($my_courses as $course){
                        
                        $categoryid = $course->category;
                        $superparent = false;
                        do {
                            $current_categoryid = $categoryid;
                            $category = $DB->get_record('course_categories', ['id' => $current_categoryid]);
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
                    $has_enrol = false;
                    if(!is_siteadmin($USER) && !in_array($category->id,$allowedcategories)){
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
                    $enrolled = is_enrolled($context,$USER);
                    if(is_siteadmin($USER)){
                        $enrolled = true;
                    }
                    if(!$enrolled){
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
                    $has_enrol = false;
                    // foreach($courses as $course){
                    //     $context = \context_course::instance($course->id);
                    //     $enrolled = is_enrolled($context,$USER);
                    //     if(is_siteadmin($USER)){
                    //         $enrolled = true;
                    //     }
                    //     if($enrolled){
                    //         $has_enrol = true;
                    //         break;
                    //     }
                    // }
                    // if(!$has_enrol){
                    //     continue;
                    // }
                    $obj = new stdClass;
                    $obj->id = $category->id;
                    $obj->name = $category->name;
                    $categories[] = $obj;
                }
    
                $courses = [];
                $curcourses = $curcategory->get_courses(['recursive' => false]);
                foreach ($curcourses as $course) {
                    $context = \context_course::instance($course->id);
                    $enrolled = is_enrolled($context,$USER);
                    if(is_siteadmin($USER)){
                        $enrolled = true;
                    }
                    if(!$enrolled){
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
        
        if($mode == 'blanktemplate'){

            $course = $DB->get_record('course', ['id' => $courseid]);
            $setting = get_config('local_rollover_wizard');
            $template_category = !empty($setting->identifier_template_course) ? $setting->identifier_template_course : 0;

            $categories = [];
            if (!empty($parent_category->parent)) {
                $obj = new stdClass;
                $obj->id = $directparentid;
                $obj->name = '..';
                $categories[] = $obj;
            }

            $curcategory = core_course_category::get($template_category);
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
    } 
    else if ($action == 'searchcourses') {
        $search = required_param('search', PARAM_TEXT);
        $courseid = optional_param('courseid', 0, PARAM_INT);

        $mode = required_param('mode', PARAM_TEXT);

        $setting = get_config('local_rollover_wizard');
        $template_category = !empty($setting->identifier_template_course) ? $setting->identifier_template_course : 0;

        $categories = [];
        $course = $DB->get_record('course', ['id' => $courseid]);
        $categoryid = 0;
        if($mode == 'previouscourse'){
            $categoryid = (!empty($courseid)) ? $course->category : 0;
        }
        else{
            $categoryid = $template_category;
        }

        if (!empty($courseid)) {
            $superparent = false;
            $supercategoryid = 0;
            do {
                $current_categoryid = $categoryid;
                $category = $DB->get_record('course_categories', ['id' => $current_categoryid]);
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
            if($mode == 'previouscourse'){
                $context = \context_course::instance($course->id);
                $enrolled = is_enrolled($context,$USER);
                if(is_siteadmin($USER)){
                    $enrolled = true;
                }
                if(!$enrolled){
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
    }
    else if ($action == 'verifycourse') {
        $sourcecourseid = required_param('sourcecourseid', PARAM_INT);
        $targetcourseid = required_param('targetcourseid', PARAM_INT);
        $mode = required_param('mode', PARAM_TEXT);
        // if($mode == 'reverse'){
        //     $temp1 = $sourcecourseid;
        //     $temp2 = $targetcourseid;
        //     $sourcecourseid = $temp2;
        //     $targetcourseid = $temp1;
        // }
        $warnings = local_rollover_wizard_verify_course($sourcecourseid, $targetcourseid, false);

        echo $warnings;

        exit;
    }
}

echo "";
exit;