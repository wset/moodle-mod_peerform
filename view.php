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
 * Prints a particular instance of peerform
 * @package    mod_peerform
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.
$p  = optional_param('p', 0, PARAM_INT);  // Newmodule instance ID - it should be named as the first character of the module.
$up = optional_param('up', 0, PARAM_INT);
$down = optional_param('down', 0, PARAM_INT);
$tab = optional_param('tab', 'allsubmissions', PARAM_ALPHA);
$submissionid = optional_param('submission', 0, PARAM_INT);
$reviewid = optional_param('review', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('peerform', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $peerform  = $DB->get_record('peerform', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($p) {
    $peerform  = $DB->get_record('peerform', array('id' => $p), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $peerform->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('peerform', $peerform->id, $course->id, false, MUST_EXIST);
} else {
    print_error('coursemisconf');
}

// If submission is supplied, it must be yours or locked.
if ($submissionid) {
    $submission = $DB->get_record('peerform_submission', array('id' => $submissionid), '*', MUST_EXIST);
    if (!$submission->locked && ($USER->id != $submission->userid)) {
        print_error('errorlockeduser', 'peerform');
    }
    if ($submission->peerformid != $peerform->id) {
        print_error('submissionother', 'peerform');
    }
}

// If review supplied.
if ($reviewid) {
    $review = $DB->get_record('peerform_submission', array('id' => $reviewid), '*', MUST_EXIST);
    if ($review->peerformid != $peerform->id) {
        print_error('submissionother', 'peerform');
    }
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerform:view', $context);
$url = new moodle_url('/mod/peerform/view.php', array('id' => $id));

// Course module viewed.
$event = \mod_peerform\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->trigger();

// Teacher operations.
if (has_capability('mod/peerform:addinstance', $context)) {
    if ($up) {
        peerformlib::updown($up, true);
        redirect($url);
    }
    if ($down) {
        peerformlib::updown($down, false);
        redirect($url);
    }
}

// Print the page header.
$PAGE->set_url('/mod/peerform/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($peerform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Set up renderer.
$output = $PAGE->get_renderer('mod_peerform');

// Output starts here.
echo $OUTPUT->header();

// Intro text.
if ($peerform->intro) {
    echo $OUTPUT->box(format_module_intro('peerform', $peerform, $cm->id), 'generalbox mod_introbox', 'peerformintro');
}

// Load comment libraries.
require_once($CFG->dirroot .'/comment/lib.php');
comment::init();

// Tabs.
$output->viewtabs($id, $context, $tab, isset($submission));

// TAB == DEFINE
// Show defined form elements to 'teachers'.
if (($tab == 'define') && has_capability('mod/peerform:addinstance', $context)) {
    $fields = $DB->get_records('peerform_field', array('peerformid' => $peerform->id, 'review' => 0), 'sequence ASC');
    $output->view_fields($cm->id, $fields, false);
    $fields = $DB->get_records('peerform_field', array('peerformid' => $peerform->id, 'review' => 1), 'sequence ASC');
    $output->view_fields($cm->id, $fields, true);
    $output->define_button($peerform->id);
}

// TAB == MY SUBMISSIONS
// Show current user's submissions.
if ($tab == 'mysubmissions') {
    if ($submissionid) {
        $output->viewsubmission($peerform->id, $submissionid, $context);
        if ($reviewid) {
            $output->viewsubmission($peerform->id, $reviewid, $context, $page);
            $output->backtosubmissionreviews($cm->id, $submissionid);
        } else {
            $output->reviews($cm->id, $course->id, $peerform->id, $submissionid, $context);
            $output->backtosubmissions($cm->id);
        }
    } else {
        $output->submitlink($peerform->id, $context);
        $output->allsubmissions($cm->id, $course->id, $peerform->id, $page, $USER->id);
    }
}

// TAB == ALL SUBMISSIONS
// Show latest submissions.
if ($tab == 'allsubmissions') {
    if ($submissionid) {
        $output->viewsubmission($peerform->id, $submissionid, $context);
        if ($reviewid) {
            $output->viewsubmission($peerform->id, $reviewid, $context, $page);
            $output->backtosubmissionreviews($cm->id, $submissionid);
        } else {
            $output->reviews($cm->id, $course->id, $peerform->id, $submissionid, $context);
            $output->backtoreviews($cm->id, $page);
        }
    } else {
        $output->submitlink($peerform->id, $context);
        $output->allsubmissions($cm->id, $course->id, $peerform->id, $page);
    }
}

// TAB = ALL REVIEWS
// Show latest reviews.
if ($tab == 'allreviews') {
    if ($submissionid && $reviewid) {
        $output->viewsubmission($peerform->id, $submissionid, $context);
        $output->viewsubmission($peerform->id, $reviewid, $context, $page);
        $output->backtoallreviews($cm->id, $page);
    } else {
        $output->allreviews($cm->id, $course->id, $peerform->id, $page);
    }
}

// TAB = MY REVIEWS
// Show current user's reviews
if ($tab == 'myreviews') {
    if ($submissionid && $reviewid) {
        $output->viewsubmission($peerform->id, $submissionid, $context);
        $output->viewsubmission($peerform->id, $reviewid, $context, $page);
        $output->backtomyreviews($cm->id, $page);
    } else {
        $output->allreviews($cm->id, $course->id, $peerform->id, $page, $USER->id);
    }
}

// Finish the page.
echo $OUTPUT->footer();
