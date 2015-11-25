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
 * Create and edit forms
 * @package    mod_peerform
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/submit_form.php');

// Parameters.
$id = required_param('id', PARAM_INT);
$submissionid = optional_param('submission', 0, PARAM_INT);
$review = optional_param('review', 0, PARAM_INT);
$parentid = optional_param('parent', 0, PARAM_INT);

$peerform  = $DB->get_record('peerform', array('id' => $id), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $peerform->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('peerform', $peerform->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerform:submit', $context);

// Print the page header.
$PAGE->set_url('/mod/peerform/submit.php', array('id' => $cm->id));
$PAGE->set_title(format_string($peerform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// If parent specified, are we allowed to review it.
if ($parentid) {
    $parent = $DB->get_record('peerform_submission', array('id' => $parentid), '*', MUST_EXIST);
    if (!$review || ($parent->peerformid != $peerform->id) || (!$parent->locked && !peerformlib::userownssubmission($parentid))) {
        print_error('invalidsubmission', 'peerform');
    }
}

// Get the data.
$fields = $DB->get_records('peerform_field', array('peerformid' => $peerform->id, 'review' => $review), 'sequence ASC');
if ($submissionid) {
    $submission = $DB->get_record('peerform_submission', array('id' => $submissionid), '*', MUST_EXIST);
    if (!has_capability('mod/peerform:editall', $context) && !$review && ($submission->userid != $USER->id)) {
        print_error('invalidsubmission', 'peerform');
    }
} else {
    $submission = new stdClass();
    $submission->peerformid = $id;
    $submission->userid = $USER->id;
    $submission->review = $review;
    $submission->locked = 0;
    $submission->modified = time();
    $submission->parentid = $parentid;
    $submission->comment = '';
}

// Set up renderer.
$output = $PAGE->get_renderer('mod_peerform');

// Set up form.
$customdata = array();
$customdata['fields'] = $fields;
$customdata['id'] = $id;
$customdata['submissionid'] = $submissionid;
$customdata['review'] = $review;
$customdata['parentid'] = $parentid;
$customdata['reviewself'] = $peerform->reviewself;
$mform = new submit_form(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/peerform/view.php', array('id' => $cm->id)));
} else if ($data = $mform->get_data()) {

    // Add or update submission table.
    if (!$submissionid) {
        $submissionid = $DB->insert_record('peerform_submission', $submission);
        $newsubmission = true;
    } else {
        $DB->update_record('peerform_submission', $submission);
        $newsubmission = false;
    }

    // Update or insert individual answers.
    foreach ($fields as $field) {
        if (!$answer = $DB->get_record('peerform_answer', array('submissionid' => $submissionid, 'fieldid' => $field->id))) {
            $answer = new stdClass();
            $answer->submissionid = $submissionid;
            $answer->fieldid = $field->id;
        }
        $fieldname = 'submission' . $field->id;
        if (empty($data->$fieldname) && $field->required) {
            print_error('errorrequiredmissing', 'peerform');
        }
        $editfield = $data->$fieldname;
        $answer->answer = $editfield['text'];
        if (empty($answer->id)) {
            $DB->insert_record('peerform_answer', $answer);
        } else {
            $DB->update_record('peerform_answer', $answer);
        }
    }

    // Log.
    if ($newsubmission) {
        $event = \mod_peerform\event\submit_create::create(array(
            'objectid' => $PAGE->cm->instance,
            'context' => $PAGE->context,
        ));
    } else {
        $event = \mod_peerform\event\submit_update::create(array(
            'objectid' => $PAGE->cm->instance,
            'context' => $PAGE->context,
        ));
    }
    $event->trigger();

    if ($review) {
        redirect(new moodle_url('/mod/peerform/view.php', array('id' => $cm->id, 'tab' => 'all', 'submission' => $parentid)));
    } else {

        // If this was a new submission they have the option to review (themselves) immediately.
        if ($newsubmission && $peerform->reviewself) {
            echo $OUTPUT->header();
            $output->confirmreview($cm->id, $peerform->id, $submissionid);
            echo $OUTPUT->footer();
            die;
        } else {
            redirect(new moodle_url('/mod/peerform/view.php',
                array('id' => $cm->id, 'tab' => 'submit', 'submission' => $submissionid)));
        }
    }
} else {
    echo $OUTPUT->header();
    if ($review) {
        $output->viewsubmission($peerform->id, $parentid, $context);
    }
    $mform->display();
    echo $OUTPUT->footer();
}


