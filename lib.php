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
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die();

 define('ROLLOVER_WIZARD_SUCCESS', 'Successful');
 define('ROLLOVER_WIZARD_UNSUCCESS', 'Unsuccessful');
 define('ROLLOVER_WIZARD_CANCELLED', 'Cancelled');
 define('ROLLOVER_WIZARD_NOTSTARTED', 'Not-started');
 define('ROLLOVER_WIZARD_INPROGRESS', 'In-progress');
 define('ROLLOVER_WIZARD_PARTLYSUCCESS', 'Partly-Successful');
 
 require_once $CFG->dirroot . '/course/lib.php';
 require_once $CFG->dirroot . '/backup/util/includes/restore_includes.php';
 require_once $CFG->dirroot . '/backup/util/includes/backup_includes.php';

function local_rollover_wizard_extend_navigation_course(navigation_node $navigation, $course, $context) {
    global $PAGE, $CFG;
    
    if (has_capability('local/rollover_wizard:edit', $context)) {
        $_SESSION['local_rollover_wizard']['target_course'] = $course;
        $_SESSION['local_rollover_wizard']['source_course'] = null;
        $_SESSION['local_rollover_wizard']['selected_activity'] = null;
        $_SESSION['local_rollover_wizard']['mode'] = null;
        $PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/rollover_wizard/script/app.js') );
        $navigation->add(get_string('importcourse','local_rollover_wizard'), '#', navigation_node::TYPE_SETTING, null, 'rolloverwizard', new pix_icon('i/report', ''));
    }
    
}

function local_rollover_wizard_verify_course($sourcecourseid, $targetcourseid, $fullwarning = true, $mode = 'reverse')
{
    global $DB, $CFG, $USER;
    $setting = get_config('local_rollover_wizard');
    $excluded_activitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
    $excluded_activitytypes = array_map('trim', $excluded_activitytypes);

    $warnings = '';
    $warningcount = 0;
    $sql = "SELECT DISTINCT targetcourseid, timecreated, userid
            FROM {local_rollover_wizard_log} WHERE status IN('Successful', 'Partly-Successful')
            GROUP BY targetcourseid";
    $rolledover_targetcourses = $DB->get_records_sql($sql);
    $rolledover_targetcourse_keys = array_keys($rolledover_targetcourses);

    if (in_array($targetcourseid, $rolledover_targetcourse_keys)) {
        $userid = $rolledover_targetcourses[$targetcourseid]->userid;
        $user = $DB->get_record('user', ['id' => $userid]);
        $warnings = 'This course has content imported on ' . userdate($rolledover_targetcourses[$targetcourseid]->timecreated, '%d-%m-%y');
        $warningcount++;
    }
    if($fullwarning){
        $sourcecourse_activities = course_modinfo::get_array_of_activities($DB->get_record('course', ['id' => $sourcecourseid]));
        $targetcourse_activities = course_modinfo::get_array_of_activities($DB->get_record('course', ['id' => $targetcourseid]));
    
        $sourcecourse_activities = array_filter($sourcecourse_activities, static function ($element) {return $element->mod != 'forum';}); // 'forum' added by default in all courses
        $targetcourse_activities = array_filter($targetcourse_activities, static function ($element) {return $element->mod != 'forum';}); // 'forum' added by default in all courses
    
        $sourcecourse_activitynames = array_column($sourcecourse_activities, 'name');
        $targetcourse_activitynames = array_column($targetcourse_activities, 'name');
        $duplicatenames = null;
        $diffs = null;
        
        $duplicatenames = array_intersect($targetcourse_activitynames, $sourcecourse_activitynames);
        $diffs = array_diff($sourcecourse_activitynames, $targetcourse_activitynames);
        if (!empty($duplicatenames) || !empty($diffs)) {
            // $warnings = 'Content exists in target course: ' . implode(',', $duplicatenames);
            $warnings .= (empty($warnings) ? '' : '<br>') . 'Content exists in target course';
            $warningcount++;
        }
        $diffs = null;
        
        $diffs = array_diff(array_column($targetcourse_activities, 'mod'), $excluded_activitytypes);
        if (empty($diffs)) {
            $warnings .= (empty($warnings) ? '' : '<br>') . 'The source course is blank';
            $warningcount++;
        }
    
        $sourceformat = course_get_format($sourcecourseid);
        $targetformat = course_get_format($targetcourseid);
        if ($sourceformat->get_format() != $targetformat->get_format()) {
            $warnings .= (empty($warnings) ? '' : '<br>') . 'The course-format is different in source and target courses';
            $warningcount++;
        }
    }

    $targetcourse = $DB->get_record('course', ['id' => $targetcourseid]);
    $coursetext = "";
    if ($warningcount > 0) {
        $coursetext = "<b>" . $targetcourse->fullname . "</b> : <br> ";
        $warnings = "<div class='rollover-warning-courseid-" . $targetcourseid . "'> $coursetext $warnings </div>";
    }
    else{
        $warnings = "&nbsp;";
    }
    return $warnings;
}

function local_rollover_wizard_executerollover()
{
    global $CFG, $USER, $DB;
    require_once $CFG->dirroot . '/course/modlib.php';
    require_once $CFG->dirroot . '/backup/util/includes/backup_includes.php';
    require_once $CFG->dirroot . '/backup/util/includes/restore_includes.php';
    require_once $CFG->libdir . '/filelib.php';

    $setting = get_config('local_rollover_wizard');
    $excluded_activitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
    $excluded_activitytypes = array_map('trim', $excluded_activitytypes);
    // $teacherroles_to_rollover = (empty(trim($setting->teacherroles_to_rollover)) ? [] : explode(',', $setting->teacherroles_to_rollover));
    // $teacherroles_to_rollover = array_map('trim', $teacherroles_to_rollover);
    mtrace($exluded_activitytypes);
    $plugin_name = 'local_rollover_wizard';
    $taskid_config = 'taskid';
    $taskid = get_config($plugin_name, $taskid_config);
    set_config($taskid_config, '', $plugin_name);

    $rolloverqueues = [];

    if (!empty($taskid)) {
        $rolloverqueue = $DB->get_record('local_rollover_wizard_log', ['taskid' => $taskid, 'status' => ROLLOVER_WIZARD_NOTSTARTED]);
        $rolloverqueues[] = $rolloverqueue;
        // mtrace("Content Rollover Wizard taskid: $taskid");
    } else {
        $rolloverqueues = $DB->get_records('local_rollover_wizard_log', ['instantexecute' => 0, 'status' => ROLLOVER_WIZARD_NOTSTARTED]);
        if (empty($rolloverqueues)) {
            $rolloverqueues = $DB->get_records('local_rollover_wizard_log', ['instantexecute' => 1, 'status' => ROLLOVER_WIZARD_NOTSTARTED]);
        }
    }

    foreach ($rolloverqueues as $rolloverqueue) {

        $rolloverqueue = $DB->get_record('local_rollover_wizard_log', ['id' => $rolloverqueue->id]);

        mtrace('Content Rollover Wizard Taskid: ' . $rolloverqueue->taskid . ' Started.');

        if (!empty($rolloverqueue->cmids) && $rolloverqueue->rollovermode == 'previouscourse') {

            if ($rolloverqueue->status != ROLLOVER_WIZARD_NOTSTARTED) {
                continue;
            }

            mtrace('TaskID ' . $rolloverqueue->taskid . ' Started.');

            $note = '';
            $rolledovercmids = '';

            try {
                $rolloverqueue->status = ROLLOVER_WIZARD_INPROGRESS;
                $DB->update_record('local_rollover_wizard_log', $rolloverqueue);

                $cmids = json_decode($rolloverqueue->cmids);
                $cur_excluded_activitytypes = array_merge($excluded_activitytypes, json_decode($rolloverqueue->excludedactivitytypes));
                mtrace($cur_exluded_activitytypes);
                // Old Code using core moodle function :
                // $sourcesection_numbers = array_keys(get_fast_modinfo($rollover->sourcecourseid)->get_section_info_all());
                // course_create_sections_if_missing($rollover->targetcourseid, $sourcesection_numbers);
                // New Code using modified core moodle function :
                local_rollover_wizard_course_create_sections_if_missing($rolloverqueue->targetcourseid, $rolloverqueue->sourcecourseid);

                $sourcesections = $DB->get_records_sql("SELECT id, section, name, summary, summaryformat, visible FROM {course_sections} WHERE course = :courseid ORDER BY section ASC",
                    ['courseid' => $rolloverqueue->sourcecourseid]);

                // Update the name and summary of target sections
                foreach ($sourcesections as $sourcesection) {
                    $targetsection = $DB->get_record('course_sections', ['course' => $rolloverqueue->targetcourseid, 'section' => $sourcesection->section]);
                    $targetsection->name = $sourcesection->name;
                    $targetsection->summary .= local_rollover_wizard_rewrite_summary($rolloverqueue->sourcecourseid, $sourcesection->summary);
                    $targetsection->summaryformat = $sourcesection->summaryformat;
                    $targetsection->visible = $sourcesection->visible;
                    $targetsection->timemodified = time();
                    $DB->update_record('course_sections', $targetsection);

                    // Copy section images if course format is grid
                    $sourcecourseid = $rolloverqueue->sourcecourseid;
                    $targetcourseid = $rolloverqueue->targetcourseid;
                    $sourcecourse = $DB->get_record('course', ['id' => $sourcecourseid]);
                    $courseformat = course_get_format($sourcecourse);

                    if ($courseformat->get_format() == 'grid') {
                        $sourcesectionid = $sourcesection->id;
                        $targetsectionid = $targetsection->id;
                        $sourcecoursecontext = context_course::instance($sourcecourseid);
                        $targetcoursecontext = context_course::instance($targetcourseid);

                        $format_grid_image = $DB->get_record('format_grid_image', array('sectionid' => $sourcesectionid));
                        if (!empty($format_grid_image)) {
                            //
                            $fs = get_file_storage();
                            $files = $fs->get_area_files($sourcecoursecontext->id, 'format_grid', 'sectionimage', $sourcesectionid);
                            foreach ($files as $file) {
                                if (!$file->is_directory()) {

                                    $filerecord = new stdClass();
                                    $filerecord->contextid = $targetcoursecontext->id;
                                    $filerecord->component = 'format_grid';
                                    $filerecord->filearea = 'sectionimage';
                                    $filerecord->itemid = $targetsectionid;
                                    $filerecord->filename = $format_grid_image->image;
                                    $newfile = $fs->create_file_from_storedfile($filerecord, $file);
                                    if ($newfile) {
                                        // $DB->set_field('format_grid_image', 'contenthash', $newfile->get_contenthash(), array('sectionid' => $filesectionid));
                                        $grid_image = $DB->get_record('format_grid_image', array('sectionid' => $targetsectionid));
                                        if (empty($grid_image)) {
                                            $grid_image = new \stdClass();
                                            $grid_image->sectionid = $targetsectionid;
                                            $grid_image->courseid = $targetcourseid;
                                            $grid_image->image = $format_grid_image->image;
                                            $grid_image->displayedimagestate = 0;
                                            $grid_image->contenthash = $newfile->get_contenthash();
                                            $newid = $DB->insert_record('format_grid_image', $grid_image);
                                        } else {
                                            $grid_image->sectionid = $targetsectionid;
                                            $grid_image->courseid = $targetcourseid;
                                            $grid_image->image = $format_grid_image->image;
                                            $grid_image->displayedimagestate = 0;
                                            $grid_image->contenthash = $newfile->get_contenthash();
                                            $newid = $DB->update_record('format_grid_image', $grid_image);
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    
                }
            } catch (\Throwable $e) {
                mtrace("Sections failed, sourcecourse $rolloverqueue->sourcecourseid, targetcourse $rolloverqueue->targetcourseid: " . $e->getMessage());
                $note .= "Sections failed, sourcecourse $rolloverqueue->sourcecourseid, targetcourse $rolloverqueue->targetcourseid: " . $e->getMessage() . '<br>';
            }

            foreach ($cmids as $cmid) {
                $nr_of_trying = 3;
                $cur_trying = 0;
                do {
                    $cur_trying += 1;
                    try {
                        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

                        if (!in_array($cm->modname, $cur_excluded_activitytypes) && $cm->deletioninprogress == 0) {

                            $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $rolloverqueue->userid);

                            $backupid = $bc->get_backupid();
                            $bc->execute_plan();
                            $bc->destroy();

                            $rc = new restore_controller($backupid, $rolloverqueue->targetcourseid, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $rolloverqueue->userid, backup::TARGET_CURRENT_ADDING);

                            $rc->execute_precheck();
                            $rc->execute_plan();

                            $rolledovercmids .= (empty($rolledovercmids) ? $cmid : ",$cmid");

                            
                            $rolloverqueue->rolledovercmids = $rolledovercmids;

                            $DB->update_record('local_rollover_wizard_log', $rolloverqueue);
                        }
                        break;
                    } catch (\Throwable $e) {
                        mtrace("Cmid-$cmid failed: " . $e->getMessage());
                        $note .= "Cmid-$cmid failed: " . $e->getMessage() . '<br>';
                        if (trim($e->getMessage()) == 'Table "backup_files_temp" already exists') {
                            $dbman = $DB->get_manager();
                            $dbman->drop_table(new \xmldb_table('backup_ids_temp'));
                            $dbman->drop_table(new \xmldb_table('backup_files_temp'));
                        }
                    }
                } while ($cur_trying < $nr_of_trying);
            }

            if (empty($note)) {
                $rolloverqueue->status = ROLLOVER_WIZARD_SUCCESS;
            } else {
                $rolloverqueue->status = ROLLOVER_WIZARD_PARTLYSUCCESS;
                $rolloverqueue->note = $note;
            }

            $rolloverqueue->rolledovercmids = $rolledovercmids;

            $DB->update_record('local_rollover_wizard_log', $rolloverqueue);
        }

        // Purge Caches
        rebuild_course_cache($rolloverqueue->targetcourseid, true);
        mtrace('TaskID ' . $rolloverqueue->taskid . ' finished.');


        if (!empty($setting->enable_email_notification) && $setting->enable_email_notification == 1) {
            try {
                mtrace('Rollover process - Start sending email out.');
                local_rollover_wizard_send_email($rolloverqueue);
                mtrace('Rollover process - Sending email finished.');
            } catch (\Exception $e) {
                mtrace("Error at sending email out: " . $e->getMessage());
            }
            
        }
        mtrace('Rollover process finished.');
    }
}

/**
 * Creates missing course section(s) and rebuilds course cache
 *
 * @param int|stdClass $courseorid course id or course object
 * @param int|array $sections list of relative section numbers to create
 * @return bool if there were any sections created
 */
function local_rollover_wizard_course_create_sections_if_missing($targetcourseid, $sourcecourseid) {
    
    $sections = array_keys(get_fast_modinfo($sourcecourseid)->get_section_info_all());
    if (!is_array($sections)) {
        $sections = array($sections);
    }
    $original_sections = get_fast_modinfo($sourcecourseid)->get_section_info_all();
    $existing = array_keys(get_fast_modinfo($targetcourseid)->get_section_info_all());
    if ($newsections = array_diff($sections, $existing)) {
        foreach ($newsections as $sectionnum) {
            $original_section = $original_sections[$sectionnum];
            local_rollover_wizard_course_create_section($targetcourseid, $sectionnum, true, $original_section->visible);
        }
        return true;
    }
    return false;
}

function local_rollover_wizard_rewrite_summary($courseid, $summary)
{
    global $DB;

    $context = context_course::instance($courseid);

    if (!empty($summary)) {
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = false;
        @$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $summary . '</div>');
        $xpath = new DOMXpath($doc);
        $imgsrcs = $xpath->query('//img/@src');
        foreach ($imgsrcs as $src) {
            $nameonly = str_replace('@@PLUGINFILE@@/', '', $src->nodeValue);
            $nameonly = explode('?', $nameonly)[0];
            $nameonly = str_replace('%20', ' ', $nameonly);

            $sql = "SELECT itemid
                    FROM {files}
                    WHERE component='course'
                        AND filearea='section'
                        AND " . $DB->sql_compare_text('filename') . "=" . $DB->sql_compare_text(':filename') . "
                        AND contextid=:contextid LIMIT 1";
            $fileitemid = $DB->get_field_sql($sql, ['contextid' => $context->id, 'filename' => $nameonly]);
            if (!empty($fileitemid)) {
                $imgpath = file_rewrite_pluginfile_urls($src->nodeValue, 'pluginfile.php', $context->id, 'course', 'section', $fileitemid);
                $summary = str_replace($src->nodeValue, $imgpath, $summary);
            }
        }
    }

    return $summary;
}

function local_rollover_wizard_send_email($rolloverqueue)
{
    global $CFG, $DB;

    require_once $CFG->dirroot . '/course/lib.php';

    $subject = 'Course content rollover has completed.';

    $html_emmail_template = '
        Dear {FULLNAME},
        <br><br>
        A course content rollover has been completed. View the results here: {REPORT-LINK}';

    $report_link = $CFG->wwwroot . '/local/rollover_wizard/viewreport.php?taskid=' . $rolloverqueue->taskid;
    $report_link = "<a href='$report_link'>$report_link</a>";

    $email_user = $DB->get_record('user', ['id' => $rolloverqueue->userid]);

    $html_emmail_template = str_replace('{FULLNAME}', "$email_user->firstname $email_user->lastname", $html_emmail_template);
    $html_emmail_template = str_replace('{REPORT-LINK}', $report_link, $html_emmail_template);

    $from_user = core_user::get_noreply_user();

    //$result = email_to_user($email_user, $from_user, $subject, '', $html_emmail_template);

    // New approach.
    $eventdata = new \core\message\message();
    $eventdata->component = 'local_rollover_wizard';
    $eventdata->name = 'content_rolledover';
    $eventdata->userfrom = \core_user::get_noreply_user();
    $eventdata->userto = $email_user;
    $eventdata->subject = $subject;
    $eventdata->fullmessage = $html_emmail_template;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = $html_emmail_template;
    $eventdata->smallmessage = $html_emmail_template;
    $eventdata->notification = 1;
    // $eventdata->contexturl = $report_link;
    $eventdata->contexturl = (new moodle_url('/local/rollover_wizard/viewreport.php', ['taskid' => $rolloverqueue->taskid]))->out();
    $eventdata->contexturlname = 'View report';
    $eventdata->courseid = 0;

    message_send($eventdata);
}


/**
 * Creates a course section and adds it to the specified position
 *
 * @param int|stdClass $courseorid course id or course object
 * @param int $position position to add to, 0 means to the end. If position is greater than
 *        number of existing secitons, the section is added to the end. This will become sectionnum of the
 *        new section. All existing sections at this or bigger position will be shifted down.
 * @param bool $skipcheck the check has already been made and we know that the section with this position does not exist
 * @return stdClass created section object
 */
function local_rollover_wizard_course_create_section($courseorid, $position = 0, $skipcheck = false, $visible = 1) {
    global $DB;
    $courseid = is_object($courseorid) ? $courseorid->id : $courseorid;

    // Find the last sectionnum among existing sections.
    if ($skipcheck) {
        $lastsection = $position - 1;
    } else {
        $lastsection = (int)$DB->get_field_sql('SELECT max(section) from {course_sections} WHERE course = ?', [$courseid]);
    }

    // First add section to the end.
    $cw = new stdClass();
    $cw->course   = $courseid;
    $cw->section  = $lastsection + 1;
    $cw->summary  = '';
    $cw->summaryformat = FORMAT_HTML;
    $cw->sequence = '';
    $cw->name = null;
    $cw->visible = $visible;
    $cw->availability = null;
    $cw->timemodified = time();
    $cw->id = $DB->insert_record("course_sections", $cw);

    // Now move it to the specified position.
    if ($position > 0 && $position <= $lastsection) {
        $course = is_object($courseorid) ? $courseorid : get_course($courseorid);
        move_section_to($course, $cw->section, $position, true);
        $cw->section = $position;
    }

    core\event\course_section_created::create_from_section($cw)->trigger();

    rebuild_course_cache($courseid, true);
    return $cw;
}