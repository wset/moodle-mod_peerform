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
 * This file keeps track of upgrades to the newmodule module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_peerform
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute newmodule upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_peerform_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015021800) {
        // Define field reviewself to be added to peerform.
        $table = new xmldb_table('peerform');
        $field = new xmldb_field('reviewself', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field reviewself.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Newmodule savepoint reached.
        upgrade_mod_savepoint(true, 2015021800, 'peerform');
    }

    if ($oldversion < 2015112500) {
        require_once(dirname(__FILE__).'/../locallib.php');

        // Get all submissions with comments.
        $sql = "SELECT * FROM {peerform_submission} ps ".
            "WHERE ps.comment <> '' ".
            "ORDER BY modified DESC ";
        $submissions = $DB->get_records_sql($sql);
        
        if( defined('CLI_SCRIPT') && CLI_SCRIPT ) {
            // log in as admin if upgrading on command line to ensure we can write comments.
            \core\session\manager::set_user(get_admin());
        }

        // Foreach submission add comment as new commentlib comment.
        foreach ($submissions as $submission) {
            require_once($CFG->dirroot  . '/comment/lib.php');
            $peerform = $DB->get_record('peerform', array('id' => $submission->peerformid));
            $course = $DB->get_record('course', array('id' => $peerform->course));
            $cm = get_coursemodule_from_instance('peerform', $peerform->id, $course->id);
            $context = context_module::instance($cm->id);

            $cmt = new stdClass();
            $cmt->context = $context;
            $cmt->course  = $course;
            $cmt->cm      = $cm;
            $cmt->area    = 'peerform_submission';
            $cmt->itemid  = $submission->id;
            $cmt->showcount = true;
            $cmt->component = 'mod_peerform';
            $comment = new comment($cmt);

            // Newmodule savepoint reached.
            $comment->add($submission->comment);
        }

        upgrade_mod_savepoint(true, 2015112500, 'peerform');
    }

    if ($oldversion < 2015112600) {

        // Define field comment to be dropped from peerform_submission.
        $table = new xmldb_table('peerform_submission');
        $field = new xmldb_field('comment');

        // Conditionally launch drop field modified.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Newmodule savepoint reached.
        upgrade_mod_savepoint(true, 2015112600, 'peerform');
    }

    return true;
}
