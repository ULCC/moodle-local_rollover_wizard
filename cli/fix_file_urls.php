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
 * CLI script to fix broken file URLs in rollover wizard courses.
 *
 * This script identifies and fixes file URLs that have become inaccessible
 * due to context or itemid mismatches after rollover operations.
 *
 * @package    local_rollover_wizard
 * @copyright  2025 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/rollover_wizard/lib.php');

// Get CLI options.
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'course' => null,
        'dry-run' => false,
        'verbose' => false,
    ],
    [
        'h' => 'help',
        'c' => 'course',
        'd' => 'dry-run',
        'v' => 'verbose',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Fix broken file URLs in rollover wizard courses.

This script identifies and repairs file URLs that have become inaccessible
due to context or itemid mismatches after rollover operations.

Options:
-h, --help              Print out this help
-c, --course=ID         Fix URLs only in specific course (optional)
-d, --dry-run           Show what would be fixed without making changes
-v, --verbose           Show detailed output

Example:
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/fix_file_urls.php
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/fix_file_urls.php --course=123
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/fix_file_urls.php --dry-run --verbose
";

    echo $help;
    die;
}

// Start the process.
cli_heading('Rollover Wizard File URL Repair Tool');

$courseid = null;
if ($options['course']) {
    $courseid = (int)$options['course'];
    
    // Validate course exists.
    if (!$DB->record_exists('course', ['id' => $courseid])) {
        cli_error("Error: Course with ID {$courseid} does not exist.");
    }
    
    $course = $DB->get_record('course', ['id' => $courseid]);
    cli_writeln("Target course: {$course->fullname} (ID: {$courseid})");
} else {
    cli_writeln("Processing all courses with @@PLUGINFILE@@ references");
}

if ($options['dry-run']) {
    cli_writeln("DRY RUN MODE: No changes will be made");
}

cli_writeln('');

// Get initial statistics.
$sql = "SELECT COUNT(DISTINCT cs.course) as courses, COUNT(cs.id) as sections
        FROM {course_sections} cs 
        WHERE cs.summary LIKE :summary";
$params = ['summary' => '%@@PLUGINFILE@@%'];

if ($courseid) {
    $sql .= " AND cs.course = :courseid";
    $params['courseid'] = $courseid;
}

$stats = $DB->get_record_sql($sql, $params);
cli_writeln("Found {$stats->sections} sections with file references across {$stats->courses} courses");

// Run the fix function.
if (!$options['dry-run']) {
    $report = local_rollover_wizard_fix_broken_file_urls($courseid);
} else {
    // For dry run, we'll simulate the process
    $report = [
        'sections_processed' => 0,
        'urls_fixed' => 0,
        'errors' => []
    ];
    
    // Get sections that would be processed.
    $sql = "SELECT cs.id, cs.summary, cs.course, cs.section
            FROM {course_sections} cs";
    $params = [];
    
    if ($courseid) {
        $sql .= " WHERE cs.course = :courseid AND cs.summary LIKE :summary";
        $params['courseid'] = $courseid;
    } else {
        $sql .= " WHERE cs.summary LIKE :summary";
    }
    $params['summary'] = '%@@PLUGINFILE@@%';
    
    $sections = $DB->get_records_sql($sql, $params);
    
    foreach ($sections as $section) {
        $report['sections_processed']++;
        
        // Find all @@PLUGINFILE@@ references.
        $pattern = '/@@PLUGINFILE@@\/([^"\'\s]+\.[^"\'\s]+)/i';
        preg_match_all($pattern, $section->summary, $matches, PREG_SET_ORDER);
        
        if ($options['verbose']) {
            cli_writeln("Section {$section->id} (Course {$section->course}): " . count($matches) . " file references");
        }
        
        $coursecontext = \context_course::instance($section->course);
        
        foreach ($matches as $match) {
            $filename = $match[1];
            
            // Check if file is accessible.
            if (!local_rollover_wizard_validate_file_access(
                $coursecontext->id, 
                'course', 
                'section', 
                $section->id, 
                $filename
            )) {
                $report['urls_fixed']++;
                if ($options['verbose']) {
                    cli_writeln("  - Would fix: {$filename}");
                }
            }
        }
    }
}

cli_writeln('');
cli_heading('Results');
cli_writeln("Sections processed: {$report['sections_processed']}");
cli_writeln("URLs fixed: {$report['urls_fixed']}");

if (!empty($report['errors'])) {
    cli_writeln("Errors encountered: " . count($report['errors']));
    if ($options['verbose']) {
        cli_writeln('');
        cli_heading('Errors');
        foreach ($report['errors'] as $error) {
            cli_writeln("  - {$error}");
        }
    }
}

if ($options['dry-run'] && $report['urls_fixed'] > 0) {
    cli_writeln('');
    cli_writeln("Run without --dry-run to actually fix these URLs");
}

cli_writeln('');
cli_writeln('Complete!');