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
require_once(dirname(__FILE__).'/comment_form.php');

// Parameters.
$id = required_param('id', PARAM_INT);
$submissionid = required_param('submission', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$peerform  = $DB->get_record('peerform', array('id' => $id), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $peerform->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('peerform', $peerform->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerform:comment', $context);

// Print the page header.
$PAGE->set_url('/mod/peerform/comment.php', array('id' => $cm->id, 'submission' => $submissionid));
$PAGE->set_title(format_string($peerform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Get/check data.
$submission = $DB->get_record('peerform_submission', array('id' => $submissionid), '*', MUST_EXIST);
if ($submission->peerformid != $id) {
        print_error('invalidsubmission', 'peerform');
}

// Set up renderer.
$output = $PAGE->get_renderer('mod_peerform');

// Set up form.
$customdata = array();
$customdata['id'] = $id;
$customdata['submissionid'] = $submissionid;
$customdata['submission'] = $submission;
$mform = new comment_form(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/peerform/view.php', array('id' => $cm->id)));
} else if ($data = $mform->get_data()) {

    // Update submission table.
    $submission->comment = $data->comment['text'];
    $DB->update_record('peerform_submission', $submission);

    $subid = $submission->review ? $submission->parentid : $submissionid;
    $tab = peerformlib::userownssubmission($submissionid) ? 'submit' : 'all';
    redirect(new moodle_url('/mod/peerform/view.php',
        array('id' => $cm->id, 'tab' => $tab, 'submission' => $subid, 'page' => $page)));
} else {
    echo $OUTPUT->header();
    $output->viewsubmission($peerform->id, $submissionid, $context);
    $mform->display();
    echo $OUTPUT->footer();
}
