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
 *
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rollover_wizard\task;

/**
 * Scheduled task to replace pluginfile.php URLs with @@PLUGINFILE@@ format.
 *
 * This task processes course sections containing old-format pluginfile.php URLs
 * and converts them to Moodle's standard @@PLUGINFILE@@ placeholder format.
 * This ensures proper file accessibility and prevents redirect issues.
 *
 * @package local_rollover_wizard\task
 */
class replace_pluginfile_urls extends \core\task\scheduled_task {
    /**
     * Gets the name of the task.
     *
     * @return string The name of the task.
     */
    public function get_name() {
        return get_string('replace_pluginfile_urls', 'local_rollover_wizard');
    }

    /**
     * Executes the URL replacement process with file copying.
     *
     * Converts old pluginfile.php URLs to @@PLUGINFILE@@ format across all
     * course sections, with enhanced file copying and progress reporting.
     *
     * @return bool True if the process completed successfully, false otherwise.
     */
    public function execute() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/rollover_wizard/lib.php');

        mtrace('Starting pluginfile URL replacement with file copying...');

        $limit = (int) get_config('local_rollover_wizard', 'replace_url_limit');
        $sql = "SELECT cs.id, cs.summary, cs.course, cs.section, c.fullname
                FROM {course_sections} cs
                JOIN {course} c ON c.id = cs.course
                LEFT JOIN {local_rollover_wizard_sectionlog} rsl ON rsl.sectionid = cs.id
                WHERE rsl.id IS NULL";

        $tobeprocessed = [];
        $count = 0;
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $record) {
            if ($rs->valid()) {
                $string = $record->summary;
                if(preg_match('/(?:href|src)=["\']([^"\']*pluginfile\.php[^"\']*)["\']/i', $string)){
                    $tobeprocessed[] = $record;
                    $count++;
                }
            }
            if($count >= $limit){
                break;
            }
        }
        $rs->close();

        if(empty($tobeprocessed)){
            mtrace('No sections require processing.');
            return true;
        }

        try {
            // Execute the enhanced URL replacement with file copying
            $processedcount = 0;
            $successcount = 0;
            $errorcount = 0;
            $filescopied = [];

            mtrace("Processing " . count($tobeprocessed) . " sections with pluginfile.php URLs");

            foreach ($tobeprocessed as $section) {
                $processedcount++;
                $summary = $section->summary;
                $original = $summary;
                $coursecontext = \context_course::instance($section->course);
                $sectionfilescopied = [];

                // Enhanced pattern to capture full pluginfile.php URLs
                $pattern = '/(\w+)=["\']([^"\']*pluginfile\.php[^"\']*\/([^\/"\']+\.[^\/"\']+))["\']/i';

                $summary = preg_replace_callback($pattern, function ($matches) use ($coursecontext, $section, &$sectionfilescopied) {
                    $attribute = $matches[1];
                    $fullurl = $matches[2];
                    $filename = $matches[3];

                    mtrace("  Processing URL: $fullurl");

                    // Try to copy file to section filearea
                    $result = local_rollover_wizard_copy_file_to_section($fullurl, $coursecontext, $section->id);

                    if ($result['success']) {
                        mtrace("    ✓ File copied successfully: {$result['fileinfo']['filename']}");
                        $sectionfilescopied[] = $result['fileinfo'];
                        return $attribute . '="' . $result['newurl'] . '"';
                    } else {
                        mtrace("    ⚠ File copy failed: {$result['error']}");
                        // Still convert URL format even if file copying failed
                        $urlinfo = local_rollover_wizard_parse_file_url($fullurl);
                        if ($urlinfo && !empty($urlinfo['filename'])) {
                            return $attribute . '="@@PLUGINFILE@@/' . $urlinfo['filename'] . '"';
                        }
                        // Fallback to original behavior
                        $filename = urldecode($filename);
                        return $attribute . '="@@PLUGINFILE@@/' . basename($filename) . '"';
                    }
                }, $summary);

                // Update section if summary changed
                if ($summary !== $original) {
                    $successcount++;

                    // Update the section record
                    $DB->update_record('course_sections', (object)[
                        'id' => $section->id,
                        'summary' => $summary,
                    ]);
                    // Log the change
                    $log = new \stdClass();
                    $log->sectionid = $section->id;
                    $log->courseid = $section->course;
                    $log->oldsummary = $original;
                    $log->newsummary = $summary;
                    $log->timecreated = time();
                    $DB->insert_record('local_rollover_wizard_sectionlog', $log);
                    if (!empty($sectionfilescopied)) {
                        $filescopied = array_merge($filescopied, $sectionfilescopied);
                    }
                    mtrace("✓ Updated section {$section->section} (ID: {$section->id}) in course: {$section->fullname}");
                    rebuild_course_cache($section->course, true);
                } else {
                    mtrace("- No changes needed for section {$section->section} (ID: {$section->id})");
                }
            }

            // Summary report
            mtrace("\n=== Processing Complete ===");
            mtrace("Sections processed: $processedcount");
            mtrace("Sections updated: $successcount");
            mtrace("Files copied: " . count($filescopied));

            if (!empty($filescopied)) {
                mtrace("\nFiles copied:");
                foreach ($filescopied as $file) {
                    mtrace("  - {$file['filename']} ({$file['filesize']} bytes)");
                }
            }

            if (count($tobeprocessed) > 0) {
                $limit = (int) get_config('local_rollover_wizard', 'replace_url_limit');
                if ($limit > 0) {
                    mtrace("Note: Processing limited to {$limit} sections per run. Schedule next execution to continue.");
                }
            }

            return true;

        } catch (\Exception $e) {
            mtrace('Error during URL replacement: ' . $e->getMessage());
            mtrace('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Checks if the task can run.
     *
     * This method always returns true, indicating that the task can run anytime.
     *
     * @return bool True if the task can run, false otherwise.
     */
    public function can_run(): bool {
        return true;
    }
}
