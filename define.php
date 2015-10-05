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
require_once(dirname(__FILE__).'/define_form.php');

// Parameters.
$id = required_param('id', PARAM_INT);
$fieldrepeats = optional_param('field_repeats', 0, PARAM_INT);
$review = optional_param('review', 0, PARAM_INT);

$peerform  = $DB->get_record('peerform', array('id' => $id), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $peerform->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('peerform', $peerform->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerform:addinstance', $context);

// Print the page header.
$PAGE->set_url('/mod/peerform/define.php', array('id' => $cm->id));
$PAGE->set_title(format_string($peerform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Get the fields data.
$fields = $DB->get_records('peerform_field', array('peerformid' => $peerform->id, 'review' => $review), 'sequence ASC');

// Set up renderer.
$output = $PAGE->get_renderer('mod_peerform');

// Custom data for form.
$customdata = array();
$customdata['fields'] = $fields;
$customdata['id'] = $id;
$customdata['context'] = $context;
$customdata['review'] = $review;

// Form stuff.
$mform = new define_form(null, $customdata);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/peerform/view.php', array('id' => $cm->id)));
} else if ($data = $mform->get_data()) {
    for ($i = 0; $i < $fieldrepeats; $i++) {
        $description = $data->fielddesc[$i]['text'];
        $format = $data->fielddesc[$i]['format'];
        $required = $data->required[$i];
        $hide = isset($data->hide[$i]) ? $data->hide[$i] : 1;
        $fieldid = $data->fieldid[$i];
        if ($fieldid) {
            if (!isset($fields[$fieldid])) {
                error('No field in database for id='.$fieldid);
                die;
            }
            $field = $fields[$fieldid];
            if ($field->review != $review) {
                error('Review mismatch for id='.$fieldid);
                die;
            }
            $field->description = $description;
            $field->required = $required;
            $field->hide = $hide;
            $field->sequence = $i + 1;
            $DB->update_record('peerform_field', $field);
        } else if ($description) {
            $field = new stdClass();
            $field->description = $description;
            $field->required = $required;
            $field->hide = $hide;
            $field->peerformid = $id;
            $field->review = $review;
            $field->sequence = $i + 1;
            $DB->insert_record('peerform_field', $field);
        } else {
            continue;
        }
    }
    redirect(new moodle_url('/mod/peerform/view.php', array('id' => $cm->id)));
} else {
    echo $OUTPUT->header();

    $mform->display();

    echo $OUTPUT->footer();
}


