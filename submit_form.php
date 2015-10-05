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
 * English strings for peerform
 *
 * @package    mod_peerform
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class submit_form extends moodleform {

    private $fields = null;

    public function definition() {
        global $DB, $OUTPUT;

        $mform = $this->_form;

        $data = $this->_customdata;
        $this->fields = $data['fields'];

        // Hidden fields.
        $mform->addElement('hidden', 'id', $data['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'submission', $data['submissionid']);
        $mform->setType('submission', PARAM_INT);
        $mform->addElement('hidden', 'parent', $data['parentid']);
        $mform->setType('parent', PARAM_INT);
        $mform->addElement('hidden', 'review', $data['review']);
        $mform->setType('review', PARAM_INT);

        // Header.
        if ($data['review']) {
            $mform->addElement('html', '<h3>' . get_string('reviewform', 'peerform') . '</h3>');
            $mform->addElement('html', '<div class="alert alert-info">' . get_string('reviewhelp', 'peerform') . '</div>');
        } else if ($data['submissionid']) {
            $mform->addElement('html', '<h3>' . get_string('submitformedit', 'peerform') . '</h3>');
            $mform->addElement('html', '<div class="alert alert-info">' . get_string('submissionhelp', 'peerform') . '</div>');
        } else {
            $mform->addElement('html', '<h3>'.get_string('submitform', 'peerform').'</h3>');
            $mform->addElement('html', '<div class="alert alert-info">' . get_string('submissionhelp', 'peerform') . '</div>');
        }

        // Add submission fields.
        $requiredfields = false;
        foreach ($this->fields as $field) {
            $fieldname = 'submission' . $field->id;
            $mform->addElement('editor', $fieldname, $field->description, array('rows' => 8), array(
                'collapsed' => 1,
                'maxfiles' => 0,
                )
            );
            if ($field->required) {
                $mform->addRule($fieldname, get_string('required'), 'required');
                $requiredfields = true;
            }
            if ($answer = $DB->get_record('peerform_answer',
                    array('submissionid' => $data['submissionid'], 'fieldid' => $field->id))) {
                $mform->setDefault($fieldname, array('text' => $answer->answer));
            }
        }

        if ($requiredfields) {
            $req = '<img class="req" src="' . $OUTPUT->pix_url('req') . '" />';
            $mform->addElement('html', '<p>' . $req . ' = ' . get_string('requireddesc', 'peerform') . '</p>');
        }

        if ($data['reviewself'] && !$data['review'] && !$data['submissionid']) {
            $mform->addElement('html', '<div class="alert alert-info">' . get_string('optiontosubmit', 'peerform') . '</div>');
        }

        $this->add_action_buttons();
    }

}

