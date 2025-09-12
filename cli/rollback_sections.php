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
 * CLI script to rollback section changes tracked in local_rollover_wizard_sectionlog.
 *
 * This script reverts course section summaries from newsummary back to oldsummary
 * and removes the corresponding records from the section log table.
 *
 * @package    local_rollover_wizard
 * @copyright  2025 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

// Get CLI options.
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'course' => null,
        'section' => null,
        'dry-run' => false,
        'verbose' => false,
        'confirm' => false,
        'force' => false,
    ],
    [
        'h' => 'help',
        'c' => 'course',
        's' => 'section',
        'd' => 'dry-run',
        'v' => 'verbose',
        'f' => 'force',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Rollback section changes tracked in rollover wizard section log.

This script reverts course section summaries from their current state back
to their original state and removes the tracking records from the log table.

Options:
-h, --help              Print out this help
-c, --course=ID         Rollback only sections in specific course (optional)
-s, --section=ID        Rollback only specific section ID (optional)
-d, --dry-run           Show what would be rolled back without making changes
-v, --verbose           Show detailed output
-f, --force             Force rollback even if content has been modified
    --confirm           Confirm rollback action (required for actual rollback)

Examples:
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/rollback_sections.php --dry-run
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/rollback_sections.php --course=123 --confirm
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/rollback_sections.php --section=456 --confirm --verbose
\$sudo -u www-data /usr/bin/php local/rollover_wizard/cli/rollback_sections.php --confirm --force
";

    echo $help;
    die;
}

// Validate parameters.
$courseid = null;
$sectionid = null;

if ($options['course']) {
    $courseid = (int)$options['course'];

    // Validate course exists.
    if (!$DB->record_exists('course', ['id' => $courseid])) {
        cli_error("Error: Course with ID $courseid does not exist.");
    }
}

if ($options['section']) {
    $sectionid = (int)$options['section'];

    // Validate section exists.
    if (!$DB->record_exists('course_sections', ['id' => $sectionid])) {
        cli_error("Error: Section with ID $sectionid does not exist.");
    }
}

// Start the process.
cli_heading('Rollover Wizard Section Rollback Tool');

// Build query conditions.
$sql = "SELECT rsl.id, rsl.sectionid, rsl.courseid, rsl.oldsummary, rsl.newsummary, 
               rsl.timecreated, cs.section as sectionnumber, c.fullname as coursename
        FROM {local_rollover_wizard_sectionlog} rsl
        JOIN {course_sections} cs ON cs.id = rsl.sectionid
        JOIN {course} c ON c.id = rsl.courseid
        WHERE 1=1";

$params = [];

if ($courseid) {
    $sql .= " AND rsl.courseid = :courseid";
    $params['courseid'] = $courseid;
}

if ($sectionid) {
    $sql .= " AND rsl.sectionid = :sectionid";
    $params['sectionid'] = $sectionid;
}

$sql .= " ORDER BY rsl.courseid, cs.section";

// Get records to process.
$records = $DB->get_records_sql($sql, $params);

if (empty($records)) {
    cli_writeln("No section log records found for rollback.");
    if ($courseid) {
        cli_writeln("Course filter: ID {$courseid}");
    }
    if ($sectionid) {
        cli_writeln("Section filter: ID {$sectionid}");
    }
    exit(0);
}

$totalrecords = count($records);
cli_writeln("Found {$totalrecords} section log record(s) to process");

// Display summary information.
if ($options['verbose']) {
    cli_writeln('');
    cli_heading('Records to Process');

    $currentcourse = null;
    foreach ($records as $record) {
        if ($currentcourse !== $record->courseid) {
            $currentcourse = $record->courseid;
            cli_writeln("Course: {$record->coursename} (ID: {$record->courseid})");
        }

        $logdate = userdate($record->timecreated);
        cli_writeln("  Section $record->sectionnumber (ID: $record->sectionid) - Logged: $logdate");

        if ($options['verbose']) {
            // Show preview of content changes.
            $oldpreview = mb_substr(strip_tags($record->oldsummary ?? ''), 0, 100);
            $newpreview = mb_substr(strip_tags($record->newsummary ?? ''), 0, 100);

            cli_writeln("    OLD: " . ($oldpreview ? $oldpreview . '...' : '[Empty]'));
            cli_writeln("    NEW: " . ($newpreview ? $newpreview . '...' : '[Empty]'));
        }
    }
}

// Confirmation check.
if (!$options['dry-run'] && !$options['confirm']) {
    cli_writeln('');
    cli_writeln('ERROR: You must specify --confirm to perform actual rollback operations.');
    cli_writeln('Use --dry-run to preview changes without applying them.');
    exit(1);
}

if ($options['dry-run']) {
    cli_writeln('');
    cli_writeln("DRY RUN MODE: No changes will be made");
} else {
    cli_writeln('');
    cli_writeln("ROLLBACK MODE: Changes will be applied");
}

cli_writeln('');

// Process records.
$successcount = 0;
$errorcount = 0;
$errors = [];

foreach ($records as $record) {
    $logmsg = "Section $record->sectionnumber in course '$record->coursename' (Section ID: $record->sectionid)";

    if ($options['verbose']) {
        cli_writeln("Processing: {$logmsg}");
    }

    if (!$options['dry-run']) {
        // Start database transaction.
        $transaction = $DB->start_delegated_transaction();

        try {
            // Get current section data to verify it matches the log.
            $currentsection = $DB->get_record('course_sections', ['id' => $record->sectionid],
                'id, course, summary', MUST_EXIST);

            // Verify the current summary matches what we expect (newsummary).
            if (!$options['force'] && $currentsection->summary !== $record->newsummary) {
                throw new moodle_exception('Section summary has been modified since log entry. ' .
                    'Expected newsummary but found different content. Use --force to override.');
            }

            if ($options['force'] && $currentsection->summary !== $record->newsummary && $options['verbose']) {
                cli_writeln("  ! Warning: Section content has been modified since log entry, forcing rollback anyway");
            }

            // Update the section summary back to original state.
            $updatedata = new stdClass();
            $updatedata->id = $record->sectionid;
            $updatedata->summary = $record->oldsummary;
            $updatedata->timemodified = time();

            $DB->update_record('course_sections', $updatedata);

            // Remove the log record.
            $DB->delete_records('local_rollover_wizard_sectionlog', ['id' => $record->id]);

            // Clear any relevant caches.
            $coursecontext = context_course::instance($record->courseid);
            rebuild_course_cache($record->courseid, true);

            // Commit the transaction.
            $transaction->allow_commit();

            $successcount++;

            if ($options['verbose']) {
                cli_writeln("  ✓ Successfully rolled back");
            }

        } catch (Exception $e) {
            // Rollback the transaction.
            $transaction->rollback($e);

            $errorcount++;
            $errors[] = "{$logmsg}: " . $e->getMessage();

            if ($options['verbose']) {
                cli_writeln("  ✗ Error: " . $e->getMessage());
            }
        }

    } else {
        // Dry run - just show what would happen.
        $successcount++;

        if ($options['verbose']) {
            cli_writeln("  ✓ Would rollback to original summary");
        }
    }
}

// Display results.
cli_writeln('');
cli_heading('Results');
cli_writeln("Records processed: {$totalrecords}");
cli_writeln("Successful rollbacks: {$successcount}");

if ($errorcount > 0) {
    cli_writeln("Errors encountered: {$errorcount}");

    if (!empty($errors)) {
        cli_writeln('');
        cli_heading('Errors');
        foreach ($errors as $error) {
            cli_writeln("  - {$error}");
        }
    }
}

if ($options['dry-run'] && $successcount > 0) {
    cli_writeln('');
    cli_writeln("Run with --confirm to actually perform these rollbacks");
}

cli_writeln('');

if ($errorcount > 0) {
    cli_writeln('Complete with errors!');
    exit(1);
} else {
    cli_writeln('Complete!');
    exit(0);
}
