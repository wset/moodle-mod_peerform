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

class peerformlib {

    /**
     * Move entry up own down in list of fields
     * @param int $fieldid
     * @param boolean $up
     */
    public static function updown($fieldid, $up=true) {
        global $DB;

        // Find the right fields.
        $field = $DB->get_record('peerform_field', array('id' => $fieldid));
        $review = $field->review;
        $peerformid = $field->peerformid;
        $fields = $DB->get_records('peerform_field', array('peerformid' => $peerformid, 'review' => $review), 'sequence ASC');

        // Put into an array we can reorder.
        $newfields = array();
        $count = 1;
        $index = 0;
        foreach ($fields as $field) {
            $newfields[$count] = $field;
            if ($field->id == $fieldid) {
                $index = $count;
            }
            $count++;
        }

        // Switch.
        if ($index) {
            $swapwith = $up ? $index - 1 : $index + 1; // Yes! Think about it.
            $temporder = $newfields[$index]->sequence;
            $newfields[$index]->sequence = $newfields[$swapwith]->sequence;
            $newfields[$swapwith]->sequence = $temporder;

            // And write back.
            $DB->update_record('peerform_field', $newfields[$index]);
            $DB->update_record('peerform_field', $newfields[$swapwith]);
        }
    }

    /**
     * Have the fields been defined (yet)
     * @param int $peerformid
     * @param int $review
     * @return boolean
     */
    public static function fieldsdefined($peerformid, $review=0) {
        global $DB;

        if ($fields = $DB->get_records('peerform_field', array('peerformid' => $peerformid, 'review' => $review))) {
            return true;
        }
        return false;
    }

    /**
     * Get all (locked) submissions
     * @param int $peerformid
     * @return array
     */
    public static function allsubmissions($peerformid) {
        global $DB, $USER;

        // Get locked submissions not belonging to current user.
        $sql = "SELECT * FROM {peerform_submission} ps ".
                "WHERE ps.peerformid = ? ".
                "AND (ps.locked = 1 OR (ps.locked = 0 AND ps.userid = ?))".
                "AND ps.review = 0 ".
                "ORDER BY modified DESC ";
        $submissions = $DB->get_records_sql($sql, array($peerformid, $USER->id));

        // Add in review counts.
        foreach ($submissions as $submission) {
            $count = $DB->count_records('peerform_submission', array('parentid' => $submission->id));
            $submission->count = $count;
        }

        return $submissions;
    }

    /**
     * Get all reviews sorted by descending submission
     * time.
     * @param int $peerformid
     * @return array
     */
    public static function reviews($peerformid) {
        global $DB;

        $reviews = $DB->get_records('peerform_submission', array('peerformid' => $peerformid, 'review' => 1), 'modified DESC');

        // We do not want ones with locked parents
        $filteredreviews = array();
        foreach ($reviews as $id => $review) {
            $parent = $DB->get_record('peerform_submission', array('id' => $review->parentid), '*', MUST_EXIST);
            if ($parent->locked || self::userownssubmission($parent->id)) {
                $filteredreviews[$id] = $review;
            }
        }
   
        return $filteredreviews;
    }

    /**
     * Get submissions made by current user
     * @param int $peerformid
     * @return array
     */
    public static function mysubmissions($peerformid) {
        global $DB, $USER;

        $submissions = $DB->get_records('peerform_submission',
            array('peerformid' => $peerformid, 'userid' => $USER->id, 'review' => 0));

        // Add in review counts.
        foreach ($submissions as $submission) {
            $count = $DB->count_records('peerform_submission', array('parentid' => $submission->id));
            $submission->count = $count;
        }

        return $submissions;
    }

    /**
     * Has the current user reviewed the submission
     * @param int $submissionid
     * @return boolean
     */
    public static function userhasreviewed($submissionid) {
        global $DB, $USER;

        if ($DB->get_records('peerform_submission', array('userid' => $USER->id, 'parentid' => $submissionid))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Does current user own submission
     * @param int $submissionid
     * @return boolean
     */
    public static function userownssubmission($submissionid) {
        global $DB, $USER;

        $submission = $DB->get_record('peerform_submission', array('id' => $submissionid), '*', MUST_EXIST);
        return $submission->userid == $USER->id;
    }

}
