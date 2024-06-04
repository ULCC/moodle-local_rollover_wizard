<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->dirroot . '/local/rollover_wizard/lib.php';

global $PAGE, $USER, $DB, $CFG, $OUTPUT;

rebuild_course_cache(0, true);
purge_all_caches();

$courseid = 9;
$start_time = microtime(true);

$filesize = 0;
$block_query = "SELECT c.id, SUM(f.filesize) AS filesize
FROM {block_instances} bi
JOIN {context} cx1 ON cx1.contextlevel = ".CONTEXT_BLOCK. " AND cx1.instanceid = bi.id
JOIN {context} cx2 ON cx2.contextlevel = ". CONTEXT_COURSE. " AND cx2.id = bi.parentcontextid
JOIN {course} c ON c.id = cx2.instanceid
JOIN {files} f ON f.contextid = cx1.id
WHERE c.id = $courseid
GROUP BY c.id";
if($record = $DB->get_record_sql($block_query)){
    $filesize += $record->filesize;
}

$cm_query = "SELECT c.id, SUM(f.filesize) AS filesize
FROM {course_modules} cm
JOIN {context} cx ON cx.contextlevel = ".CONTEXT_MODULE." AND cx.instanceid = cm.id
JOIN {course} c ON c.id = cm.course
JOIN {files} f ON f.contextid = cx.id
WHERE c.id = $courseid
GROUP BY c.id";
if($record = $DB->get_record_sql($cm_query)){
    $filesize += $record->filesize;
}
$course_query = "SELECT c.id, SUM(f.filesize) AS filesize
FROM {course} c
JOIN {context} cx ON cx.contextlevel = ".CONTEXT_COURSE." AND cx.instanceid = c.id
JOIN {files} f ON f.contextid = cx.id
WHERE c.id = $courseid
GROUP BY c.id";
if($record = $DB->get_record_sql($course_query)){
    $filesize += $record->filesize;
}

$coursesize = new \stdClass();
$coursesize->filesize = $filesize;
$coursesize->id = $courseid;

echo "<pre>", var_dump($coursesize), "</pre>";

echo "Duration : ". (microtime(true) - $start_time). "<br>";


$start_time = microtime(true);

$sqlunion = "UNION ALL
                SELECT c.id, f.filesize
                FROM {block_instances} bi
                JOIN {context} cx1 ON cx1.contextlevel = ".CONTEXT_BLOCK. " AND cx1.instanceid = bi.id
                JOIN {context} cx2 ON cx2.contextlevel = ". CONTEXT_COURSE. " AND cx2.id = bi.parentcontextid
                JOIN {course} c ON c.id = cx2.instanceid
                JOIN {files} f ON f.contextid = cx1.id
            UNION ALL
                SELECT c.id, f.filesize
                FROM {course_modules} cm
                JOIN {context} cx ON cx.contextlevel = ".CONTEXT_MODULE." AND cx.instanceid = cm.id
                JOIN {course} c ON c.id = cm.course
                JOIN {files} f ON f.contextid = cx.id";

$sqlunion = "SELECT id AS course, SUM(filesize) AS filesize
          FROM (SELECT c.id, f.filesize
                  FROM {course} c
                  JOIN {context} cx ON cx.contextlevel = ".CONTEXT_COURSE." AND cx.instanceid = c.id
                  JOIN {files} f ON f.contextid = cx.id {$sqlunion}) x
            GROUP BY id";


$sql = "SELECT c.id, c.fullname, c.category, ca.name as categoryname, rc.filesize
FROM {course} c
JOIN ($sqlunion) rc on rc.course = c.id ";


$sql .= "JOIN {course_categories} ca on c.category = ca.id
WHERE c.id = ".$courseid."
ORDER BY rc.filesize DESC";
$course = $DB->get_record_sql($sql);
echo "<pre>", var_dump($course), "</pre>";


echo "Duration : ". (microtime(true) - $start_time). "<br>";