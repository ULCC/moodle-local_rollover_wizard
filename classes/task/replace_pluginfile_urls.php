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

        // Get initial statistics - more accurate pattern for HTML attributes containing pluginfile.php
        // Exclude sections already processed (logged in sectionlog table)
        $limit = (int) get_config('local_rollover_wizard', 'replace_url_limit');
        $sql = "SELECT COUNT(DISTINCT cs.course) as courses, COUNT(cs.id) as sections
                FROM {course_sections} cs
                LEFT JOIN {local_rollover_wizard_sectionlog} rsl ON rsl.sectionid = cs.id
                WHERE rsl.id IS NULL
                  AND (cs.summary REGEXP :href_pattern
                   OR cs.summary REGEXP :src_pattern) LIMIT {$limit}";
        $params = [
            'href_pattern' => 'href=["\'][^"\']*pluginfile\\.php[^"\']*["\']',
            'src_pattern' => 'src=["\'][^"\']*pluginfile\\.php[^"\']*["\']'
        ];

        $initialstats = $DB->get_record_sql($sql, $params);
        mtrace("Found {$initialstats->sections} sections with pluginfile.php URLs across {$initialstats->courses} courses");

        if ($initialstats->sections == 0) {
            mtrace('No sections require processing.');
            return true;
        }

        try {
            // Execute the enhanced URL replacement with file copying
            local_rollover_wizard_replace_urls_section();

            // Get final statistics using the same improved pattern
            $finalstats = $DB->get_record_sql($sql, $params);
            $processed = $initialstats->sections - $finalstats->sections;

            mtrace("Processing complete. Processed {$processed} sections.");
            mtrace("Remaining sections with pluginfile.php URLs: {$finalstats->sections}");

            if ($finalstats->sections > 0) {
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
