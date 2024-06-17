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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_rollover_wizard
 * @category    upgrade
 * @copyright   2024 Cosector Development <dev@cosector.co.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_rollover_wizard upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_rollover_wizard_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.
    if ($oldversion < 2024041502) {

        // Define table local_rollover_wizard_log to be created.
        $table = new xmldb_table('local_rollover_wizard_log');

        // Adding fields to table local_rollover_wizard_log.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourcecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('targetcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('note', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('cmids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('rolledovercmids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('excludedactivitytypes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_rollover_wizard_log.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_rollover_wizard_log.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Rollover_wizard savepoint reached.
        upgrade_plugin_savepoint(true, 2024041502, 'local', 'rollover_wizard');
    }

    if ($oldversion < 2024041511) {

        // Define field mode to be added to local_rollover_wizard_log.
        $table = new xmldb_table('local_rollover_wizard_log');
        $field = new xmldb_field('mode', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'taskid');

        // Conditionally launch add field mode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('templatecourse', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'targetcourseid');

        // Conditionally launch add field templatecourse.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        
        $field = new xmldb_field('sourcecourseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'mode');

        // Launch change of nullability for field sourcecourseid.
        $dbman->change_field_notnull($table, $field);

        // Rollover_wizard savepoint reached.
        upgrade_plugin_savepoint(true, 2024041511, 'local', 'rollover_wizard');
    }
    if ($oldversion < 2024041517) {

        // Define field instantexecute to be added to local_rollover_wizard_log.
        $table = new xmldb_table('local_rollover_wizard_log');

        
        $field = new xmldb_field('mode', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'taskid');

        // Launch rename field rollovermode.
        $dbman->rename_field($table, $field, 'rollovermode');

        $field = new xmldb_field('instantexecute', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'rollovermode');

        // Conditionally launch add field instantexecute.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rollover_wizard savepoint reached.
        upgrade_plugin_savepoint(true, 2024041517, 'local', 'rollover_wizard');
    }
    if ($oldversion < 2024041518) {

        // Changing type of field note on table local_rollover_wizard_log to text.
        $table = new xmldb_table('local_rollover_wizard_log');
        $field = new xmldb_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null, 'userid');

        // Launch change of type for field note.
        $dbman->change_field_type($table, $field);

        // Rollover_wizard savepoint reached.
        upgrade_plugin_savepoint(true, 2024041518, 'local', 'rollover_wizard');
    }

    if ($oldversion < 2024052901) {

        // Define table rollover_wizard_coursesize to be created.
        $table = new xmldb_table('rollover_wizard_coursesize');

        // Adding fields to table rollover_wizard_coursesize.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeupdated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table rollover_wizard_coursesize.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for rollover_wizard_coursesize.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Rollover_wizard savepoint reached.
        upgrade_plugin_savepoint(true, 2024052901, 'local', 'rollover_wizard');
    }
    if ($oldversion < 2024061401) {

        // Define field selectedsections to be added to local_rollover_wizard_log.
        $table = new xmldb_table('local_rollover_wizard_log');
        $field = new xmldb_field('selectedsections', XMLDB_TYPE_TEXT, null, null, null, null, null, 'cmids');

        // Conditionally launch add field selectedsections.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rollover_wizard savepoint reached.
        upgrade_plugin_savepoint(true, 2024061401, 'local', 'rollover_wizard');
    }
    return true;
}
