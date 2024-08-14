<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->dirroot . '/local/rollover_wizard/lib.php';

global $PAGE, $USER, $DB, $CFG, $OUTPUT;

rebuild_course_cache(0, true);
purge_all_caches();

