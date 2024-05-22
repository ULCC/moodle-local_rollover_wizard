<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->dirroot . '/local/rollover_wizard/lib.php';

global $PAGE, $USER, $DB, $CFG, $OUTPUT;

// echo local_rollover_wizard_verify_course(null, 9, false);


$coursesize = local_rollover_wizard_course_filesize_sql(19);
echo "<pre>", var_dump($coursesize), "</pre>";