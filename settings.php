<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_rollover_wizard
 * @copyright  2024 Cosector Development <dev@cosector.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    global $CFG;
    
    $ADMIN->add(
        'modules',
        new admin_category(
            'rollover_wizard_settings',
            new lang_string(
                'setting_page:category',
                'local_rollover_wizard'
            )
        )
    );

    // $ADMIN->add('rollover_wizard_settings',new admin_externalpage('rollover_wizard_report',get_string('setting_page:report','local_rollover_wizard'),new moodle_url('/local/rollover_wizard/viewreport.php'), ['moodle/site:config']));

    $settings = new admin_settingpage('local_rollover_wizard', get_string('pluginname', 'local_rollover_wizard'));
    $ADMIN->add('localplugins', $settings);

    $element = new admin_setting_configcheckbox('local_rollover_wizard/enable_email_notification', 'Enable email notification',
                                                'Tick this checkbox to enable email function to notify the author after the rollover completes.', null);
    $settings->add($element);

    // $element = new admin_setting_configtext('local_rollover_wizard/pattern_match_course', 'Pattern Match for Matching Course','', null);
    // $settings->add($element);

    $displaylist = \core_course_category::make_categories_list('moodle/course:create');
    $element = new admin_setting_configselect_autocomplete('local_rollover_wizard/identifier_template_course', 'Identifier for template courses','', 0,  $displaylist);
    // $element = new admin_setting_configtext('local_rollover_wizard/identifier_course_template', 'Identifier for template courses','', null);
    $settings->add($element);

    $element = new admin_setting_configcheckbox('local_rollover_wizard/enable_cron_schedulling', 'Enable CRON Task schedulling',
                                                get_string('cron_schedulling_description', 'local_rollover_wizard'), null);
    $settings->add($element);

    $choices = [1 => "1 GB",
                2 => "2 GB",
                3 => "3 GB",
                4 => "4 GB",
                5 => "5 GB",
                // 10 => "10 GB",
                // 15 => "15 GB",
                // 20 => "20 GB",
            ];
    $element = new admin_setting_configselect('local_rollover_wizard/cron_size_threshold', 'Size threshold for scheduled run',
                                '', 1, $choices);
    $settings->add($element);

    
    $element = new admin_setting_configtextarea('local_rollover_wizard/activities_notberolled', 'Activities not to be rolled over',
                                'Put in activity types separated by commas. Ex: turnitin,forum', null, PARAM_TEXT, '20', '8');
    $settings->add($element);
}
