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
 * @package    mod_peerform
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// 30 minutes editing time.
define('PEERFORM_EDIT_TIME', 30 * 60);

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function peerform_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the peerform into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $peerform An object from the form in mod_form.php
 * @param mod_peerform_mod_form $mform
 * @return int The id of the newly inserted peerform record
 */
function peerform_add_instance(stdClass $peerform, mod_peerform_mod_form $mform = null) {
    global $DB;

    $peerform->timecreated = time();

    return $DB->insert_record('peerform', $peerform);
}

/**
 * Updates an instance of peerform in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $peerform An object from the form in mod_form.php
 * @param mod_peerform_mod_form $mform
 * @return boolean Success/Fail
 */
function peerform_update_instance(stdClass $peerform, mod_peerform_mod_form $mform = null) {
    global $DB;

    $peerform->timemodified = time();
    $peerform->id = $peerform->instance;

    return $DB->update_record('peerform', $peerform);
}

/**
 * Removes an instance of the peerform from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function peerform_delete_instance($id) {
    global $DB;

    if (! $peerform = $DB->get_record('peerform', array('id' => $id))) {
        return false;
    }

    // Delete fields.
    $DB->delete_records('peerform_field', array('peerformid' => $id));

    // Delete answers.
    if ($submissions = $DB->get_records('peerform_submission', array('peerformid' => $id))) {
        foreach ($submissions as $submission) {
            $DB->delete_records('peerform_answer', array('submissionid' => $submission->id));
        }
    }

    // Delete submissions.
    $DB->delete_records('peerform_submission', array('peerformid' => $id));

    // Finally, delete peerform.
    $DB->delete_records('peerform', array('id' => $peerform->id));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function peerform_user_outline($course, $user, $mod, $peerform) {
    global $DB, $CFG;

    // Get user grades.
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'peerform', $peerform->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
        if ($grade->str_grade == '-') {
            $grade = false;
        }
    }

    $params = array(
        'peerformid' => $peerform->id,
        'userid' => $user->id
    );

    // Get submissions for current user.
    $sql = "SELECT peerformid, modified FROM {peerform_submission} ps ".
            "WHERE ps.peerformid = :peerformid ".
            "AND ps.userid = :userid ".
            "AND ps.review = 0 ".
            "ORDER BY modified ASC ";

    $submissions = $DB->get_records_sql($sql, $params);

    $rsql = "SELECT peerformid, modified FROM {peerform_submission} ps ".
            "WHERE ps.peerformid = :peerformid ".
            "AND ps.userid = :userid ".
            "AND ps.review = 1 ".
            "ORDER BY modified ASC ";

    $reviews = $DB->get_records_sql($rsql, $params);

    $result = null;

    if (!empty($submissions)) {
        $result = new stdClass();
        $result->info = get_string('numsubmissions', 'peerform', count($submissions));
        $lastsubtime = end($submissions)->modified;

        $lastreviewtime = 0;
        if (!empty($reviews)) {
            $result->info .= ", " . get_string('numreviews', 'peerform', count($reviews));
            $lastreviewtime = end($reviews)->modified;
        }

        $result->time = max($lastsubtime, $lastreviewtime);

        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
    } else if (!empty($reviews)) {
        $result = new stdClass();
        $result->info = get_string('numreviews', 'peerform', count($reviews));
        $result->time = end($reviews)->modified;

        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
        // If grade was last modified by the user themselves use date graded. Otherwise use date submitted.
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }
    }

    return $result;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $peerform the module instance record
 * @return void, is supposed to echp directly
 */
function peerform_user_complete($course, $user, $mod, $peerform) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in peerform activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function peerform_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false.
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link newmodule_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function peerform_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see newmodule_get_recent_mod_activity()}
 * @return void
 */
function peerform_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

function peerform_get_view_actions() {
    return array('view', 'view all');
}

function peerform_get_post_actions() {
    return array('submit');
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 **/
function peerform_cron () {
    global $DB;

    // All submissions >30mins old are locked.
    $sql = "UPDATE {peerform_submission} ".
            "SET locked = 1 ".
            "WHERE modified < ? ".
            "AND locked = 0";
    $DB->execute($sql, array(time() - PEERFORM_EDIT_TIME));
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function peerform_get_extra_capabilities() {
    return array();
}

