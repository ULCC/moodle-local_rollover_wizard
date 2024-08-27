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
 defined('MOODLE_INTERNAL') || die();

 define('ROLLOVER_WIZARD_SUCCESS', 'Successful');
 define('ROLLOVER_WIZARD_UNSUCCESS', 'Unsuccessful');
 define('ROLLOVER_WIZARD_CANCELLED', 'Cancelled');
 define('ROLLOVER_WIZARD_NOTSTARTED', 'Not-started');
 define('ROLLOVER_WIZARD_INPROGRESS', 'In-progress');
 define('ROLLOVER_WIZARD_PARTLYSUCCESS', 'Partly-Successful');

 require_once($CFG->dirroot . '/course/lib.php');
 require_once($CFG->dirroot . '/lib/blocklib.php');
 require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
 require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');


 /**
  * Extends the navigation for a course during the rollover wizard.
  *
  * This function is likely called by the rollover wizard process to modify
  * the course navigation structure.
  *
  * @param navigation_node $navigation The navigation node object.
  * @param stdClass $course The course object.
  * @param stdClass $context The context object.
  * @return void
  */
function local_rollover_wizard_extend_navigation_course(navigation_node $navigation, $course, $context) {
    global $PAGE, $CFG;

    if (has_capability('local/rollover_wizard:edit', $context)) {
        $sessiondata = [];
        if (isset($_SESSION['local_rollover_wizard'])) {
            foreach ($_SESSION['local_rollover_wizard'] as $key => $object) {
                if (is_array($object)) {
                    $targetcourse = $object['target_course'];
                    if ($targetcourse->id == $course->id) {
                        unset($_SESSION['local_rollover_wizard'][$key]);
                        break;
                    }
                }
            }
        }
        $sessiondata['target_course'] = $course;
        $sessiondata['source_course'] = null;
        $sessiondata['selected_activity'] = null;
        $sessiondata['mode'] = null;
        $sessiondata['import_course_setting'] = false;
        $key = time();
        $_SESSION['local_rollover_wizard'][$key] = $sessiondata;
        $_SESSION['local_rollover_wizard']['key'] = $key;
        $PAGE->requires->js( new moodle_url($CFG->wwwroot . '/local/rollover_wizard/script/app.js') );
        $PAGE->requires->css( new moodle_url($CFG->wwwroot . '/local/rollover_wizard/script/app.css') );
        $navigation->add(get_string(
            'importcourse',
            'local_rollover_wizard'),
             '#',
              navigation_node::TYPE_SETTING, null,
             'rolloverwizard', null);
    }

}

/**
 * Verifies a course during the rollover wizard process.
 *
 * Checks for potential issues or conflicts during the course rollover.
 *
 * @param int $sourcecourseid The ID of the source course.
 * @param int $targetcourseid The ID of the target course.
 * @param bool $fullwarning Whether to return full warning messages or just a count.
 * @param string $mode The rollover mode (e.g., 'reverse').
 * @return string A warning message or an empty string if no issues found.
 */
function local_rollover_wizard_verify_course($sourcecourseid, $targetcourseid, $fullwarning = true, $mode = 'reverse') {
    global $DB, $CFG, $USER;
    $setting = get_config('local_rollover_wizard');
    $excludedactivitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
    $excludedactivitytypes = array_map('trim', $excludedactivitytypes);

    $warnings = '';
    $warningcount = 0;
    $sql = "SELECT DISTINCT targetcourseid, timecreated, userid
            FROM {local_rollover_wizard_log} WHERE status IN('Successful', 'Partly-Successful')
            GROUP BY targetcourseid";
    $rolledovertargetcourses = $DB->get_records_sql($sql);
    $rolledovertargetcoursekeys = array_keys($rolledovertargetcourses);

    if (in_array($targetcourseid, $rolledovertargetcoursekeys)) {
        $userid = $rolledovertargetcourses[$targetcourseid]->userid;
        $user = $DB->get_record('user', ['id' => $userid]);
        $warnings = 'This course had content imported on ' .
        userdate($rolledovertargetcourses[$targetcourseid]->timecreated, '%d-%m-%y') .
        " by ". fullname($user);
        $warningcount++;
    }
    if ($fullwarning) {
        $sourcecourseactivities = course_modinfo::get_array_of_activities($DB->get_record('course', ['id' => $sourcecourseid]));
        $targetcourseactivities = course_modinfo::get_array_of_activities($DB->get_record('course', ['id' => $targetcourseid]));

        $sourcecourseactivities = array_filter($sourcecourseactivities, static function ($element) {return $element->mod != 'forum';
        }); // Forum added by default in all courses.
        $targetcourseactivities = array_filter($targetcourseactivities, static function ($element) {return $element->mod != 'forum';
        }); // Forum added by default in all courses.

        $sourcecourseactivitynames = array_column($sourcecourseactivities, 'name');
        $targetcourseactivitynames = array_column($targetcourseactivities, 'name');
        $duplicatenames = null;
        $diffs = null;

        $duplicatenames = array_intersect($targetcourseactivitynames, $sourcecourseactivitynames);
        $diffs = array_diff($sourcecourseactivitynames, $targetcourseactivitynames);
        if (!empty($duplicatenames) || !empty($diffs)) {
            $warnings .= (empty($warnings) ? '' : '<br>') . 'Content exists in target course';
            $warningcount++;
        }
        $diffs = null;

        $diffs = array_diff(array_column($targetcourseactivities, 'mod'), $excludedactivitytypes);
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
    } else {
        $warnings = "&nbsp;";
    }
    return $warnings;
}

/**
 * Executes the rollover wizard process.
 *
 * This function initiates the course rollover process based on the specified mode.
 *
 * @param int $mode The rollover mode (default is 1).
 * @return void
 */
function local_rollover_wizard_executerollover($mode = 1,$taskid=0) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/course/modlib.php');
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    require_once($CFG->libdir . '/filelib.php');

    $setting = get_config('local_rollover_wizard');
    $excludedactivitytypes = (empty(trim($setting->activities_notberolled)) ? [] : explode(',', $setting->activities_notberolled));
    $excludedactivitytypes = array_map('trim', $excludedactivitytypes);
    $rolloverqueues = [];

    if (!empty($taskid)) {
        $rolloverqueue = $DB->get_record('local_rollover_wizard_log',
         ['taskid' => $taskid,
         'status' => ROLLOVER_WIZARD_NOTSTARTED]
        );
        $rolloverqueues[] = $rolloverqueue;
    } else {
        $rolloverqueues = $DB->get_records('local_rollover_wizard_log',
         ['instantexecute' => 0,
          'status' => ROLLOVER_WIZARD_NOTSTARTED]
        );
        if (empty($rolloverqueues)) {
            $rolloverqueues = $DB->get_records('local_rollover_wizard_log',
             ['instantexecute' => 1,
              'status' => ROLLOVER_WIZARD_NOTSTARTED]
            );
        }
    }
    $enabled = get_config("local_rollover_wizard", "update_internal_link");

    foreach ($rolloverqueues as $rolloverqueue) {

        $rolloverqueue = $DB->get_record('local_rollover_wizard_log', ['id' => $rolloverqueue->id]);
        $params = [
            "id"=>$rolloverqueue->sourcecourseid
        ];
        $course=$DB->get_record("course",$params,"*",MUST_EXIST);
      
        mtrace('Content Rollover Wizard Taskid: ' . $rolloverqueue->taskid . ' Started.');

        $sourcecourseid = $rolloverqueue->sourcecourseid;
        $targetcourseid = $rolloverqueue->targetcourseid;
        $useridrollover = $rolloverqueue->userid;
        if (!empty($rolloverqueue->cmids)) {
            $curexcludedactivitytypes = json_decode($rolloverqueue->excludedactivitytypes);
            if ($rolloverqueue->status != ROLLOVER_WIZARD_NOTSTARTED) {
                continue;
            }

            mtrace('TaskID ' . $rolloverqueue->taskid . ' Started.');

            $note = '';
            $rolledovercmids = '';
            $includedsections = [];
            $originalsections = [];
            $checksections = false;
            $rolloverqueue->status = ROLLOVER_WIZARD_INPROGRESS;
            $DB->update_record('local_rollover_wizard_log', $rolloverqueue);

            // 1. Proccess activity section to target course.
            try {
               
                // Create backup controller.
                $bc = new backup_controller(
                    backup::TYPE_1COURSE,
                    $sourcecourseid,
                    backup::FORMAT_MOODLE,
                    backup::INTERACTIVE_NO,
                    backup::MODE_IMPORT,
                    $useridrollover
                );
                $settings = $bc->get_plan()->get_settings();
                foreach ($settings as $setting) {
                    $settingname = $setting->get_name();
                    $shouldinclude = true;
                    mtrace("Backup : ".$settingname);
                    foreach ($curexcludedactivitytypes as $excludedactivity) {
                        if (strpos($settingname, $excludedactivity) !== false) {
                            $shouldinclude = false;
                            break;
                        }
                    }
                    if (!$shouldinclude) {
                        $setting->set_value(0);
                        mtrace("Disabled setting: " . $settingname);
                    }
                }
                // Run plan backup.
                $bc->execute_plan();
                $backupid = $bc->get_backupid();
                $bc->destroy();

                // Create restore controller.
                $rc = new restore_controller(
                    $backupid,
                    $targetcourseid,
                    backup::INTERACTIVE_NO,
                    backup::MODE_IMPORT,
                    $useridrollover,
                    backup::TARGET_CURRENT_ADDING
                );
                $settings = $rc->get_plan()->get_settings();
                foreach ($settings as $setting) {
                    $settingname = $setting->get_name();
                    if (in_array($settingname, ['users', 'enrolments','permissions'])) {
                        $setting->set_value(0);
                        mtrace("Disabled restore for: " . $settingname);
                    }
                   
                }
                $rc->execute_precheck();
                $rc->execute_plan();
                $rc->destroy();
            } catch (\Throwable $e) {
                mtrace("import failed: " . $e->getMessage());
                $note .= "import failed: " . $e->getMessage() . '<br>';
                if (trim($e->getMessage()) == 'Table "backup_files_temp" already exists') {
                    $dbman = $DB->get_manager();
                    $dbman->drop_table(new \xmldb_table('backup_ids_temp'));
                    $dbman->drop_table(new \xmldb_table('backup_files_temp'));
                }
            }
            // 2. Proccess Check link to source course

            if (!$enabled) {
                local_rollover_wizard_update_internal_links($rolloverqueue);
            }
            // 3. Proccess import course setting to target course.
            $cmids = json_decode($rolloverqueue->cmids);
            foreach ($cmids as $cmid) {
                if ($cmid === 'coursesettings') {
                    try {
                        $fs = get_file_storage();
                        $oldtargetcourse = $DB->get_record('course', ['id' => $targetcourseid]);
                        $oldhasfile = false;

                        $oldtargetcontext = \context_course::instance($oldtargetcourse->id);

                        $sql = "SELECT itemid, filename
                        FROM {files}
                        WHERE component='course'
                            AND filearea='overviewfiles'
                            AND contextid=:contextid LIMIT 1";
                        $filerecord = $DB->get_record_sql($sql, ['contextid' => $oldtargetcontext->id]);
                        $file = null;
                        if (!empty($filerecord)) {
                            $file = $fs->get_file($oldtargetcontext->id, 'course', 'overviewfiles', 0, '/', $filerecord->filename);
                            $oldhasfile = true;
                        }

                        $backuptempdir = make_backup_temp_directory('');
                        $packer = get_file_packer('application/vnd.moodle.backup');
                        $admin = get_admin();
                        if (!$admin) {
                            mtrace("Error: No admin account was found");
                            $a = new stdclass();
                            $a->userid = $rolloverqueue->userid;
                            $a->courseid = $courseid;
                            $a->capability = 'none';
                            throw new backup_controller_exception('admin_user_missing', $a);
                        }
                        $bc = new \backup_controller(
                            \backup::TYPE_1COURSE,
                             $sourcecourseid,
                            \backup::FORMAT_MOODLE,
                            \backup::INTERACTIVE_NO,
                            \backup::MODE_GENERAL,
                             $admin->id);
                        foreach ($bc->get_plan()->get_settings() as $setting) {
                            if ($setting->get_status() != \base_setting::NOT_LOCKED) {
                                continue;
                            }
                            $name = $setting->get_name();
                            if ($name == 'filename') {
                                continue;
                            }
                            $value = false;
                            $bc->get_plan()->get_setting($name)->set_value($value);
                        }
                        $bc->execute_plan();

                        $results = $bc->get_results();
                        $results['backup_destination']->extract_to_pathname($packer, "$backuptempdir/test_content_rollover");

                        $bc->destroy();
                        unset($bc);

                        $rc = new \restore_controller('test_content_rollover',
                          $targetcourseid,
                          \backup::INTERACTIVE_NO,
                          \backup::MODE_GENERAL,
                           $admin->id, \backup::TARGET_CURRENT_ADDING
                        );
                        foreach ($rc->get_plan()->get_settings() as $setting) {
                            if ($setting->get_status() != \base_setting::NOT_LOCKED) {
                                continue;
                            }
                            $name = $setting->get_name();
                            $value = false;
                            if ($name == 'overwrite_conf') {
                                $value = true;
                            }
                            $rc->get_plan()->get_setting($name)->set_value($value);
                        }

                        if (!$rc->execute_precheck()) {
                            $a = new stdclass();
                            $a->userid = $rolloverqueue->userid;
                            $a->courseid = $courseid;
                            $a->capability = 'none';
                            throw new backup_controller_exception('course_settings_precheck_failed', $a);
                        }
                        $rc->execute_plan();
                        $rc->destroy();
                    } catch (\Throwable $e) {
                        mtrace("Course Settings failed: " . $e->getMessage());
                        $note .= "Course Settings failed: " . $e->getMessage() . '<br>';
                    }
                }
            }

            rebuild_course_cache($rolloverqueue->targetcourseid, true);
            if (empty($note)) {
                $rolloverqueue->status = ROLLOVER_WIZARD_SUCCESS;
            } else {
                $rolloverqueue->status = ROLLOVER_WIZARD_PARTLYSUCCESS;
                $rolloverqueue->note = $note;
            }

            $rolloverqueue->rolledovercmids = $rolledovercmids;

            $DB->update_record('local_rollover_wizard_log', $rolloverqueue);
        }

        // End of Rollover Blocks.
        // Purge Caches.
        rebuild_course_cache($rolloverqueue->targetcourseid, true);
        mtrace('TaskID ' . $rolloverqueue->taskid . ' finished.');
        $setting = get_config('local_rollover_wizard');
        // if (!empty($setting->enable_email_notification) && $setting->enable_email_notification == 1) {
        //     try {
        //         mtrace('Rollover process - Start sending email out.');
        //         local_rollover_wizard_send_email($rolloverqueue);
        //         mtrace('Rollover process - Sending email finished.');
        //     } catch (\Exception $e) {
        //         mtrace("Error at sending email out: " . $e->getMessage());
        //     }

        // }
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
function local_rollover_wizard_course_create_sections_if_missing(
  $targetcourseid,
  $sourcecourseid,
  $checksections = false ,
  $included = [] ) {

    $sections = array_keys(get_fast_modinfo($sourcecourseid)->get_section_info_all());
    if (!is_array($sections)) {
        $sections = [$sections];
    }
    $originalsections = get_fast_modinfo($sourcecourseid)->get_section_info_all();
    $existing = array_keys(get_fast_modinfo($targetcourseid)->get_section_info_all());
    if ($newsections = array_diff($sections, $existing)) {
        foreach ($newsections as $sectionnum) {
            $originalsection = $originalsections[$sectionnum];
            local_rollover_wizard_course_create_section($targetcourseid, $sectionnum, true, $originalsection->visible);
        }
        return true;
    }
    return false;
}

/**
 * Rewrites the summary for a course during the rollover wizard.
 *
 * This function modifies the summary based on the provided source and target sections.
 *
 * @param stdClass $sourcesection The source section object.
 * @param stdClass $targetsection The target section object.
 * @return string The rewritten summary string.
 */
function local_rollover_wizard_rewrite_summary($sourcesection, $targetsection) {
    global $DB;
    $summary = $sourcesection->summary;

    $sourcecontext = \context_course::instance($sourcesection->course);
    $targetcontext = \context_course::instance($targetsection->course);
    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = false;
    @$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $summary . '</div>');
    $xpath = new DOMXpath($doc);
    $rawsrcs = ['//img/@src', '//a/@href'];
    foreach ($rawsrcs as $rawsrc) {
        $srcs = $xpath->query($rawsrc);
        foreach ($srcs as $src) {
            $nameonly = str_replace('@@PLUGINFILE@@/', '', $src->nodeValue);
            $nameonly = explode('?', $nameonly)[0];
            $nameonly = urldecode($nameonly);

            $sql = "SELECT itemid
                    FROM {files}
                    WHERE component='course'
                        AND filearea='section'
                        AND " . $DB->sql_compare_text('filename') . "=" . $DB->sql_compare_text(':filename') . "
                        AND contextid=:contextid LIMIT 1";
            $fileitemid = $DB->get_field_sql($sql, ['contextid' => $targetcontext->id, 'filename' => $nameonly]);
            if (empty($fileitemid)) {

                $fs = get_file_storage();

                // Get Source file.
                $file = $fs->get_file($sourcecontext->id, 'course', 'section', $sourcesection->id, '/', $nameonly);
                if (!$file) {
                    continue;
                }
                $newfilerecord = [
                    'contextid'    => $targetcontext->id,
                    'component'    => $file->get_component(),
                    'filearea'     => $file->get_filearea(),
                    'itemid'       => $targetsection->id,
                    'filepath'     => $file->get_filepath(),
                    'filename'     => $file->get_filename(),
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ];
                $fs->create_file_from_storedfile($newfilerecord, $file);

                $fileitemid = $DB->get_field_sql($sql, ['contextid' => $targetcontext->id, 'filename' => $nameonly]);
            }

            if (!empty($fileitemid)) {
                $imgpath = file_rewrite_pluginfile_urls($src->nodeValue,
                'pluginfile.php',
                 $targetcontext->id,
                'course',
                'section',
                 $fileitemid
                );
                $summary = str_replace($src->nodeValue, $imgpath, $summary);
            }
        }
    }

    return $summary;
}


function local_rollover_wizard_rewrite_format_intro($source_module, $target_module) {
    global $DB;

    $summary = $source_module->intro;
    $sourcecontext = context_module::instance($source_module->coursemodule);
    $targetcontext = context_module::instance($target_module->coursemodule);

    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = false;
    @$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $summary . '</div>');
    $xpath = new DOMXpath($doc);
    $rawsrcs = ['//img/@src', '//a/@href'];

    foreach ($rawsrcs as $rawsrc) {
        $srcs = $xpath->query($rawsrc);
        foreach ($srcs as $src) {
            $nameonly = str_replace('@@PLUGINFILE@@/', '', $src->nodeValue);
            $nameonly = explode('?', $nameonly)[0];
            $nameonly = urldecode($nameonly);

            $sql = "SELECT itemid
                    FROM {files}
                    WHERE component='mod_{$target_module->modname}'
                        AND filearea='intro'
                        AND " . $DB->sql_compare_text('filename') . "=" . $DB->sql_compare_text(':filename') . "
                        AND contextid=:contextid LIMIT 1";
            $fileitemid = $DB->get_field_sql($sql, ['contextid' => $targetcontext->id, 'filename' => $nameonly]);

            if (empty($fileitemid)) {
                $fs = get_file_storage();
                $file = $fs->get_file($sourcecontext->id, 'mod_' . $source_module->modname, 'intro', $source_module->id, '/', $nameonly);
                if (!$file) {
                    continue;
                }
                $newfilerecord = [
                    'contextid'    => $targetcontext->id,
                    'component'    => $file->get_component(),
                    'filearea'     => $file->get_filearea(),
                    'itemid'       => $target_module->id,
                    'filepath'     => $file->get_filepath(),
                    'filename'     => $file->get_filename(),
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ];
                $fs->create_file_from_storedfile($newfilerecord, $file);

                $fileitemid = $DB->get_field_sql($sql, ['contextid' => $targetcontext->id, 'filename' => $nameonly]);
            }

            if (!empty($fileitemid)) {
                $path = file_rewrite_pluginfile_urls($src->nodeValue,
                'pluginfile.php',
                 $targetcontext->id,
                'mod_' . $target_module->modname,
                'intro',
                 $fileitemid
                );
                $summary = str_replace($src->nodeValue, $path, $summary);
            }
        }
    }

    return $summary;
}
/**
 * Fixes image references within HTML blocks during a rollover wizard.
 *
 * This function adjusts image paths or URLs within the HTML content.
 *
 * @param int $sourceblockid The ID of the source HTML block.
 * @param int $targetblockid The ID of the target HTML block.
 * @param string $content The HTML content to be processed.
 * @return void The modified HTML content with fixed image references.
 */
function local_rollover_wizard_htmlblokcs_imagefix($sourceblockid, $targetblockid, $content) {
    global $DB;
    $sourcecontext = \context_block::instance($sourceblockid);
    $targetcontext = \context_block::instance($targetblockid);
    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = false;
    @$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $content . '</div>');
    $xpath = new DOMXpath($doc);
    $rawsrcs = ['//img/@src', '//a/@href'];
    foreach ($rawsrcs as $rawsrc) {
        $srcs = $xpath->query($rawsrc);
        foreach ($srcs as $src) {
            $nameonly = str_replace('@@PLUGINFILE@@/', '', $src->nodeValue);
            $nameonly = explode('?', $nameonly)[0];
            $nameonly = urldecode($nameonly);

            $sql = "SELECT itemid
                    FROM {files}
                    WHERE component='course'
                        AND filearea='section'
                        AND " . $DB->sql_compare_text('filename') . "=" . $DB->sql_compare_text(':filename') . "
                        AND contextid=:contextid LIMIT 1";
            $fileitemid = $DB->get_field_sql($sql, ['contextid' => $targetcontext->id, 'filename' => $nameonly]);
            if (empty($fileitemid)) {

                $fs = get_file_storage();

                // Get Source file.
                $file = $fs->get_file($sourcecontext->id, 'block_html', 'content', 0, '/', $nameonly);
                if (!$file) {
                    continue;
                }
                $newfilerecord = [
                    'contextid'    => $targetcontext->id,
                    'component'    => $file->get_component(),
                    'filearea'     => $file->get_filearea(),
                    'itemid'       => 0,
                    'filepath'     => $file->get_filepath(),
                    'filename'     => $file->get_filename(),
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ];
                $fs->create_file_from_storedfile($newfilerecord, $file);

                $fileitemid = $DB->get_field_sql($sql, ['contextid' => $targetcontext->id, 'filename' => $nameonly]);
            }
        }
    }
}
/**
 * Sends an email related to the rollover wizard process.
 *
 * This function dispatches an email based on the provided rollover queue data.
 *
 * @param stdClass $rolloverqueue The rollover queue data.
 * @return void
 */
function local_rollover_wizard_send_email($rolloverqueue) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/course/lib.php');
    $subject = 'Course content rollover has completed.';

    $htmlemmailtemplate = get_string('emailtemplate', 'local_rollover_wizard');

    $reportlink = $CFG->wwwroot . '/course/view.php?id=' . $rolloverqueue->targetcourseid;
    $reportlink = "<a href='$reportlink'>$reportlink</a>";

    $emailuser = $DB->get_record('user', ['id' => $rolloverqueue->userid]);

    $htmlemmailtemplate = str_replace('{FULLNAME}', "$emailuser->firstname $emailuser->lastname", $htmlemmailtemplate);
    $htmlemmailtemplate = str_replace('{REPORT-LINK}', $reportlink, $htmlemmailtemplate);

    $fromuser = core_user::get_noreply_user();
    // New approach.
    $eventdata = new \core\message\message();
    $eventdata->component = 'local_rollover_wizard';
    $eventdata->name = 'content_rolledover_wizard';
    $eventdata->userfrom = \core_user::get_noreply_user();
    $eventdata->userto = $emailuser;
    $eventdata->subject = $subject;
    $eventdata->fullmessage = $htmlemmailtemplate;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = $htmlemmailtemplate;
    $eventdata->smallmessage = $htmlemmailtemplate;
    $eventdata->notification = 1;
    $eventdata->contexturl = (new moodle_url('/course/view.php', ['id' => $rolloverqueue->targetcourseid]))->out();
    $eventdata->contexturlname = 'Rollover Wizard';
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

/**
 * Checks if a course should be processed by a cron task during the rollover wizard.
 *
 * Determines if the course size exceeds the configured threshold for cron processing.
 *
 * @param int $courseid The ID of the course to check.
 * @return bool True if the course should be processed by cron, false otherwise.
 */
function local_rollover_wizard_is_crontask($courseid) {
    global $DB;
    $setting = get_config('local_rollover_wizard');
    $iscron = false;
    if (!empty($setting->enable_cron_schedulling) && $setting->enable_cron_schedulling == 1) {
        $iscron = false;
        $coursesize = $DB->get_record('rollover_wizard_coursesize', ['courseid' => $courseid]);
        if ($coursesize) {

            $maxfilesize = $setting->cron_size_threshold * 1024 ** 3;
            $iscron = ((int) $coursesize->size) >= $maxfilesize;
        }
    }
    return $iscron;
}

/**
 * Calculates the total file size for a course.
 *
 * This function retrieves the size of files associated with blocks, course modules,
 * and the course itself, returning the total size.
 *
 * @param int $courseid The ID of the course to calculate file size for.
 * @return stdClass An object containing the course ID (`id`) and total file size (`filesize`).
 */
function local_rollover_wizard_course_filesize($courseid) {
    global $DB;
    // New Code.
    $filesize = 0;
    $blockquery = "SELECT c.id, SUM(f.filesize) AS filesize
    FROM {block_instances} bi
    JOIN {context} cx1 ON cx1.contextlevel = ".CONTEXT_BLOCK. " AND cx1.instanceid = bi.id
    JOIN {context} cx2 ON cx2.contextlevel = ". CONTEXT_COURSE. " AND cx2.id = bi.parentcontextid
    JOIN {course} c ON c.id = cx2.instanceid
    JOIN {files} f ON f.contextid = cx1.id
    WHERE c.id = $courseid
    GROUP BY c.id";
    if ($record = $DB->get_record_sql($blockquery)) {
        $filesize += $record->filesize;
    }

    $cmquery = "SELECT c.id, SUM(f.filesize) AS filesize
    FROM {course_modules} cm
    JOIN {context} cx ON cx.contextlevel = ".CONTEXT_MODULE." AND cx.instanceid = cm.id
    JOIN {course} c ON c.id = cm.course
    JOIN {files} f ON f.contextid = cx.id
    WHERE c.id = $courseid
    GROUP BY c.id";
    if ($record = $DB->get_record_sql($cmquery)) {
        $filesize += $record->filesize;
    }
    $coursequery = "SELECT c.id, SUM(f.filesize) AS filesize
    FROM {course} c
    JOIN {context} cx ON cx.contextlevel = ".CONTEXT_COURSE." AND cx.instanceid = c.id
    JOIN {files} f ON f.contextid = cx.id
    WHERE c.id = $courseid
    GROUP BY c.id";
    if ($record = $DB->get_record_sql($coursequery)) {
        $filesize += $record->filesize;
    }

    $course = new \stdClass();
    $course->filesize = $filesize;
    $course->id = $courseid;
    return $course;
}

/**
 * Retrieves HTML block instances for a specific course.
 *
 * This function retrieves all block instances with the block name "html" associated
 * with the provided course ID.
 *
 * @param int $courseid The ID of the course to retrieve HTML blocks for.
 * @return array An array of objects representing the retrieved HTML block instances.
 */
function local_rollover_wizard_get_htmlblocks_by_course($courseid) {
    global $DB;

    $sql = "SELECT blocks.*
            FROM {course} course
            JOIN {context} context ON context.instanceid = course.id
            JOIN {block_instances} blocks ON context.id = blocks.parentcontextid
            WHERE blocks.blockname IS NOT NULL AND blocks.blockname = 'html' AND course.id = :courseid";

    $coursehtmlblocks = $DB->get_records_sql($sql, ['courseid' => $courseid]);

    return $coursehtmlblocks;
}


/**
 * Updates internal course section links during the rollover wizard process.
 *
 * This function updates target course section names, summaries, and visibility
 * based on the source course sections and rollover mode.
 *
 * @param stdClass $rolloverqueue The rollover queue data object.
 * @return void
 */
function local_rollover_wizard_update_internal_links($rolloverqueue) {
    global $DB;
    if ($rolloverqueue->rollovermode == 'previouscourse' && !empty($rolloverqueue->selectedsections)) {
        $includedsections = json_decode($rolloverqueue->selectedsections);
    }
    $sql = "SELECT id, section, course, name, summary, summaryformat, visible FROM {course_sections} ";
    $sql .= "WHERE course = :courseid ORDER BY section ASC";
    $params = [
    'courseid' => $rolloverqueue->sourcecourseid,
    ];
    $sourcesections = $DB->get_records_sql($sql, $params);
    // Update the name and summary of target sections.
    foreach ($sourcesections as $sourcesection) {
        $targetsection = $DB->get_record('course_sections',
         [
            'course' => $rolloverqueue->targetcourseid,
            'section' => $sourcesection->section,
         ]);
        if (!$targetsection) {
            continue;
        }
        if (!in_array($sourcesection->section, $includedsections) && $rolloverqueue->rollovermode == 'previouscourse') {
            continue;
        }
        $targetsection->name = $sourcesection->name;
        $targetsection->summary = local_rollover_wizard_rewrite_summary($sourcesection, $targetsection);
        $targetsection->summaryformat = $sourcesection->summaryformat;
        $targetsection->visible = $sourcesection->visible;
        $targetsection->timemodified = time();
        $DB->update_record('course_sections', $targetsection);
    }
}

/**
 * Retrieves all activities associated with a specific course section.
 *
 * This function retrieves all course module records for the provided section ID.
 *
 * @param int $sectionid The ID of the course section to retrieve activities for.
 * @return array An array of objects representing the retrieved course module records.
 */
function get_activities_by_section($sectionid) {
    global $DB;
    $contents = $DB->get_records_sql("SELECT * FROM {course_modules} WHERE section = :sectionid", ['sectionid' => $sectionid]);
    return $contents;
}
