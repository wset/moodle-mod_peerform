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
 * Form definition for creating forms
 * @package    mod_peerform
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class define_form extends moodleform {

    private $context = null;
    private $fields = null;
    private $review = null;

    // Add elements to form.
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $data = $this->_customdata;
        $this->context = $data['context'];
        $this->fields = $data['fields'];
        $this->review = $data['review'];

        // Hidden fields.
        $mform->addElement('hidden', 'id', $data['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'review', $data['review']);
        $mform->setType('review', PARAM_INT);

        // Heading.
        if ($this->review) {
            $mform->addElement('html', '<h3>'.get_string('headreview', 'peerform').'</h3>');
        } else {
            $mform->addElement('html', '<h3>'.get_string('headentry', 'peerform').'</h3>');
        }
        $mform->addElement('html', '<div class="alert alert-info">' . get_string('definedesc', 'peerform') . '</div>');

        // Use a repeated input to define the fields.
        $repeatarray = array();
        if ($this->review) {
            $repeatarray[] = $mform->createElement('header', '', get_string('reviewfield', 'peerform').' {no}');
        } else {
            $repeatarray[] = $mform->createElement('header', '', get_string('field', 'peerform').' {no}');
        }
        $repeatarray[] = $mform->createElement('editor', 'fielddesc', get_string('fielddesc', 'peerform'), array('rows' => 8),
            array(
            'collapsed' => 1,
            'maxfiles' => 0,
            )
        );
        $repeatarray[] = $mform->createElement('selectyesno', 'required', get_string('required', 'peerform'));
        if (!$this->review) {
            $repeatarray[] = $mform->createElement('selectyesno', 'hide', get_string('hide', 'peerform'));
        }
        $repeatarray[] = $mform->createElement('hidden', 'fieldid', 0);

        // Number of fields.
        if ($this->fields) {
            $repeatno = count($this->fields);
        } else {
            $repeatno = 5;
        }

        // Options for repeated elements.
        $repeatoptions = array();
        $repeatoptions['fielddesc']['default'] = '';
        $repeatoptions['fielddesc']['type'] = PARAM_RAW;
        $repeatoptions['fielddesc']['helpbutton'] = array('fielddesc', 'peerform');
        $repeatoptions['required']['type'] = PARAM_BOOL;
        $repeatoptions['required']['helpbutton'] = array('required', 'peerform');
        if (!$this->review) {
            $repeatoptions['hide']['type'] = PARAM_BOOL;
            $repeatoptions['hide']['helpbutton'] = array('hide', 'peerform');
        }
        $repeatoptions['fieldid']['type'] = PARAM_INT;

        $this->repeat_elements($repeatarray, $repeatno, $repeatoptions, 'field_repeats', 'field_add_fields', 3);

        $this->add_action_buttons();

        $this->set_data($data);
    }

    public function set_data($defaultvalues) {
        if ($this->fields) {
            $key = 0;
            foreach ($this->fields as $field) {
                $draftid = file_get_submitted_draft_itemid("fielddesc[$key]");
                $defaultvalues["fielddesc[$key]"]['text'] = file_prepare_draft_area(
                    $draftid,               // Draftid.
                    $this->context->id,     // Context.
                    'mod_peerform',             // Component.
                    'fields',             // Filarea.
                    !empty($field->id) ? $field->id : null, // Itemid.
                    null,
                    $field->description // Text.
                );
                $defaultvalues["required[$key]"] = $field->required;
                $defaultvalues["hide[$key]"] = $field->hide;
                $defaultvalues["fieldid[$key]"] = $field->id;
                $key++;
            }
        }
        parent::set_data($defaultvalues);
    }

}
