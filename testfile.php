<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->dirroot . '/local/rollover_wizard/lib.php';

global $PAGE, $USER, $DB, $CFG, $OUTPUT;

// echo local_rollover_wizard_verify_course(null, 9, false);

rebuild_course_cache(0, true);
purge_all_caches();

// $coursesize = local_rollover_wizard_course_filesize(2);
// echo "<pre>", var_dump($coursesize), "</pre>";

// echo "<pre>", var_dump($_SESSION['local_rollover_wizard']), "</pre>";

// $target_course = $DB->get_record('course', ['id' => 11]);

// $matches = [];
// preg_match('/(.*?)([0-9]{6})/', $target_course->idnumber, $matches);
// $target_academic_year = intval((empty($matches[2]) ? 0 : $matches[2]));

// if (!empty($target_academic_year)) {
//     $courseids = [];
//     $academic_years = [];
//     $courses = $DB->get_records('course');
//     foreach($courses as $course){
//         $codeidnumber = preg_replace('/(.*?)([0-9]{6})/', '$1', $course->idnumber);
//         if(!empty($codeidnumber) && $codeidnumber == $matches[1]){
//             $acadidnumber = preg_replace('/(.*?)([0-9]{6})/', '$2', $course->idnumber);
//             $acadidnumber = intval($acadidnumber);
//             $courseids[$acadidnumber] = $course->id;
//             $academic_years[] = $acadidnumber;
//         }
//     }
//     rsort($academic_years);
//     list($target_start_year, $target_end_year) = preg_split('/(?<=.{4})/', $matches[2], 2);
//     $matched_courseid = null;
//     foreach($academic_years as $year){
//         list($source_start_year, $source_end_year) = preg_split('/(?<=.{4})/', (string) $year, 2);
        
//         if($source_start_year < $target_start_year && $source_end_year < $target_end_year){
//             $matched_courseid = $courseids[$year];
//             break;
//         }
//     }
//     if(!empty($matched_courseid)){
//         $session_data['source_course'] = $DB->get_record('course', ['id' => $matched_courseid]);

//     }
// }

// echo "<pre>", var_dump(local_rollover_wizard_is_crontask(15)), "</pre>";


// $url = (new moodle_url('/local/rollover_wizard/workerfile.php'));

// echo "<pre>", var_dump($_SERVER), "</pre>";

// if( \filter_var( \ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOLEAN ) ) {
//     echo "<br>allow url fopen enabled<br>";
// }
// if( \filter_var( \ini_get('openssl'), \FILTER_VALIDATE_BOOLEAN ) ) {
//     echo "<br>openssl enabled<br>";
// }


// $setting = get_config('local_rollover_wizard');
// $coursesize = $DB->get_record('rollover_wizard_coursesize', ['courseid' => 7]);
// if($coursesize){
//     // $max_filesize = ((($setting->cron_size_threshold * 1024) * 1024) * 1024);
//     // $is_cron = $coursesize->filesize >= $max_filesize;

//     $max_filesize = $setting->cron_size_threshold * 1024 ** 3;
//     $is_cron = $coursesize->size >= $max_filesize;

//     echo "<pre>", var_dump($coursesize), "</pre>";
//     echo "<pre>", var_dump("Max Filesize : ".$max_filesize), "</pre>";
//     echo "<pre>", var_dump("Course Filesize : ".$coursesize->size), "</pre>";
//     echo "<pre>", var_dump("Is cron : ".$is_cron), "</pre>";
// }