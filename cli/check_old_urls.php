<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/../../../config.php');

$courseid = isset($argv[1]) ? (int)$argv[1] : null;

if ($courseid) {
    echo "Checking course ID: {$courseid}\n";
    $sql = "SELECT * FROM {course_sections} WHERE course = :courseid AND summary LIKE :pattern";
    $sections = $DB->get_records_sql($sql, ['courseid' => $courseid, 'pattern' => '%pluginfile.php%']);
} else {
    echo "Checking all courses\n";
    $sql = "SELECT * FROM {course_sections} WHERE summary LIKE :pattern";
    $sections = $DB->get_records_sql($sql, ['pattern' => '%pluginfile.php%']);
}

echo "Found " . count($sections) . " sections with pluginfile.php URLs\n\n";

foreach($sections as $section) {
    echo "Course: {$section->course}, Section: {$section->section} (ID: {$section->id})\n";
    
    // Extract pluginfile URLs
    preg_match_all('/https?:\/\/[^\s"\']*pluginfile\.php[^\s"\']*/i', $section->summary, $matches);
    
    if (!empty($matches[0])) {
        foreach($matches[0] as $url) {
            echo "  URL: " . substr($url, 0, 80) . "...\n";
        }
    }
    echo "---\n";
}