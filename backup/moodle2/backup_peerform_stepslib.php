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

class backup_peerform_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $peerform = new backup_nested_element('peerform', array('id'), array(
            'name', 'intro', 'introformat', 'timecreated', 'timemodified'));

        $fields = new backup_nested_element('fields');

        $field = new backup_nested_element('field', array('id'), array(
            'description', 'required', 'hide', 'sequence', 'review'
        ));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'), array(
            'userid', 'parentid', 'review', 'locked', 'modified', 'comment'
        ));

        $answers = new backup_nested_element('answers');

        $answer = new backup_nested_element('answer', array('id'), array(
            'submissionid', 'fieldid', 'answer'
        ));

        // Build the tree.
        $peerform->add_child($fields);
        $fields->add_child($field);
        $peerform->add_child($submissions);
        $submissions->add_child($submission);
        $submission->add_child($answers);
        $answers->add_child($answer);

        // Define sources.
        $peerform->set_source_table('peerform', array('id' => backup::VAR_ACTIVITYID));
        $field->set_source_table('peerform_field', array('peerformid' => backup::VAR_PARENTID));
        if ($userinfo) {
            $submission->set_source_table('peerform_submission', array('peerformid' => backup::VAR_PARENTID));
            $answer->set_source_table('peerform_answer', array('submissionid' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $submission->annotate_ids('user', 'userid');

        // Define file annotations.
        $peerform->annotate_files('mod_peerform', 'intro', null);

        // Return the root element (peerform), wrapped into standard activity structure.
        return $this->prepare_activity_structure($peerform);
    }
}
