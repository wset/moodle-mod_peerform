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

class restore_peerform_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('peerform', '/activity/peerform');
        $paths[] = new restore_path_element('peerform_field', '/activity/peerform/fields/field');
        if ($userinfo) {
            $paths[] = new restore_path_element('peerform_submission', '/activity/peerform/submissions/submission');
            $paths[] = new restore_path_element('peerform_answer', '/activity/peerform/submissions/submission/answers/answer');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_peerform($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the choice record.
        $newitemid = $DB->insert_record('peerform', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_peerform_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peerformid = $this->get_new_parentid('peerform');

        $newitemid = $DB->insert_record('peerform_field', $data);
        $this->set_mapping('peerform_field', $oldid, $newitemid);
    }

    protected function process_peerform_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peerformid = $this->get_new_parentid('peerform');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->parentid = $this->get_mappingid('peerform_submission', $data->parentid);

        $newitemid = $DB->insert_record('peerform_submission', $data);
        $this->set_mapping('peerform_submission', $oldid, $newitemid);
    }

    protected function process_peerform_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_mappingid('peerform_submission', $data->submissionid);
        $data->fieldid = $this->get_mappingid('peerform_field', $data->fieldid);

        $newitemid = $DB->insert_record('peerform_answer', $data);
    }

    protected function after_execute() {
        // Add peerform related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_peerform', 'intro', null);
    }
}
