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
 * Plugin strings are defined here.
 *
 * @package     local_rollover_wizard
 * @category    string
 * @copyright   2024 Cosector Development <dev@cosector.co.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Content Rollover Wizard';
$string['importcourse'] = 'Import Content';
$string['rollover_wizard:edit'] = 'Allow access to Content Rollover Wizard';
$string['content_option1'] = 'Import a module page template';
$string['content_option2'] = 'Import content from another module page';

$string['setting_page:category'] = 'Rollover Wizard';

$string['emailtemplate'] = '
Dear {FULLNAME},
<br><br>
A course content rollover has been completed. View the results here: {REPORT-LINK}';
$string['wizard_support_company'] = 'LXI';
$string['wizard_support_link'] = 'https://google.com';
$string['wizard_support_text'] = 'The content import did not complete due to : <br>{NOTE}<br><p>Please contact {LINK} for support</p>';

$string['cron_schedulling_description'] = 'Tick this checkbox to enable cron schedulling to make rollover task above threshold setting below run through scheduled task. If this disabled, all rollover task will instantly executed';