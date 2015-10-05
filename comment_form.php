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

class comment_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        $data = $this->_customdata;
        $submission = $data['submission'];

        // Hidden fields.
        $mform->addElement('hidden', 'id', $data['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'submission', $data['submissionid']);
        $mform->setType('submission', PARAM_INT);

        // Header.
        $mform->addElement('html', '<h3>' . get_string('commentform', 'peerform') . '</h3>');

        // Comment.
        $mform->addElement('editor', 'comment', get_string('comment', 'peerform'), array('rows' => 8), array(
            'collapsed' => 1,
            'maxfiles' => 0,
            )
        );
        $mform->addRule('comment', get_string('required'), 'required');
        $mform->setDefault('comment', array('text' => $submission->comment));

        $this->add_action_buttons();
    }
}
