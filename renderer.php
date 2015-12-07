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
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the peerform module.
 *
 * @package mod-peerform
 * @copyright 2013 Howard Miller
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

define('MAX_SUBMISSION_COUNT', 30);
define('SUBMISSIONS_PERPAGE', 10);

class mod_peerform_renderer extends plugin_renderer_base {

    /**
     * Display edit button
     */
    public function define_button($id) {
        echo "<div>";
        $entryurl = new moodle_url('/mod/peerform/define.php', array('id' => $id, 'review' => 0));
        echo "<a class=\"btn btn-warning\" href=\"$entryurl\">".get_string('defineentry', 'peerform')."</a>";
        $reviewurl = new moodle_url('/mod/peerform/define.php', array('id' => $id, 'review' => 1));
        echo "&nbsp;<a class=\"btn btn-warning\" href=\"$reviewurl\">".get_string('definereview', 'peerform')."</a>";
        echo "</div>";
    }

    /**
     * Show table of defined fields
     * @param int $cmid course module if
     * @param array $fields fields from db
     * @param boolean $review showing review fields (true) or submit fields
     */
    public function view_fields($cmid, $fields, $review) {
        global $OUTPUT;

        echo "<div class=\"alert alert-info\">";
        if ($review) {
            echo '<h4>' . get_string('reviewfields', 'peerform') . '</h4>';
            $class = 'alert-success';
        } else {
            echo '<h4>' . get_string('entryfields', 'peerform') . '</h4>';
            $class = 'alert-info';
        }
        $table = new html_table();
        $count = 1;
        if ($fields) {
            foreach ($fields as $field) {
                $orderhtml = '';
                if ($count > 1) {
                    $uplink = new moodle_url('/mod/peerform/view.php', array('id' => $cmid, 'up' => $field->id));
                    $orderhtml .= "<a href=\"$uplink\"><img src=\"" . $OUTPUT->pix_url('t/up') . "\" alt=\"".
                            get_string('up') . "\"></a>&nbsp;";
                }
                if ($count < count($fields)) {
                    $downlink = new moodle_url('/mod/peerform/view.php', array('id' => $cmid, 'down' => $field->id));
                    $orderhtml .= "<a href=\"$downlink\"><img src=\"" . $OUTPUT->pix_url('t/down') . "\" alt=\"".
                            get_string('down') . "\"></a>";
                }
                $required = $field->required ? '<small>' . get_string('required') . '</small>' : '';
                $table->data[] = array("<strong>$count</strong>", $field->description, $required, $orderhtml);
                $count++;
            }
            echo html_writer::table($table);
        } else {
            echo '<p class="text-warning">' . get_string('nofieldsdefined', 'peerform') . '</p>';
        }
        echo "</div>";
    }

    /**
     * Create submit form link
     * @param int $id Peerform id
     */
    public function submitlink($id) {
        if (peerformlib::fieldsdefined($id)) {
            $submiturl = new moodle_url('/mod/peerform/submit.php', array('id' => $id));
            echo "<div><a class=\"btn btn-success\" href=\"$submiturl\">" . get_string('submitform', 'peerform') . '</a></div>';
        } else {
            echo '<div class="alert alert-danger">' . get_string('activitynotconfigured', 'peerform') . '</div>';
        }
    }

    /**
     * Create submit review form link
     * @param int $id Peerform id
     */
    public function submitreviewlink($id, $submissionid) {
        if (peerformlib::fieldsdefined($id, true)) {
            $submiturl = new moodle_url('/mod/peerform/submit.php',
                    array('id' => $id, 'parent' => $submissionid, 'review' => 1, 'tab' => 'review'));
            echo "<p><a class=\"btn btn-success\" href=\"$submiturl\">" . get_string('submitreviewform', 'peerform') . '</a></p>';
        } else {
            echo '<div class="alert alert-warning">' . get_string('activitynotconfigured', 'peerform') . '</div>';
        }
    }

    /**
     * Display the tabs (on view page)
     * @param int $cmid
     * @param object $context the course context
     * @param string $selected currently selected tab
     */
    public function viewtabs($cmid, $context, $selected) {
        global $OUTPUT;

        $tabs = array();
        if (has_capability('mod/peerform:review', $context) || has_capability('mod/peerform:comment', $context)) {
            $tabs['all'] = get_string('allsubmissions', 'peerform');
        }
        if (has_capability('mod/peerform:submit', $context)) {
            $tabs['submit'] = get_string('mysubmissions', 'peerform');
        }
        if (has_capability('mod/peerform:review', $context) || has_capability('mod/peerform:comment', $context)) {
            $tabs['reviews'] = get_string('reviews', 'peerform');
        }
        if (has_capability('mod/peerform:addinstance', $context)) {
            $tabs['define'] = get_string('define', 'peerform');
        }

        // Build list.
        $rows = array();
        foreach ($tabs as $key => $tab) {
            $rows[] = new tabobject(
                $key,
                new moodle_url('/mod/peerform/view.php', array('tab' => $key, 'id' => $cmid)),
                $tab
            );
        }
        echo $OUTPUT->tabtree($rows, $selected);
    }

    /**
     * Show a single submission
     * @param int $peerformid
     * @param int $submissionid
     * @param object $context
     */
    public function viewsubmission($peerformid, $submissionid, $context, $page = 0, $echo = true) {
        global $DB, $USER, $OUTPUT, $CFG;

        // Basic data.
        $submission = $DB->get_record('peerform_submission', array('id' => $submissionid), '*', MUST_EXIST);
        $review = $submission->review;
        $fields = $DB->get_records('peerform_field', array('peerformid' => $peerformid, 'review' => $review), 'sequence ASC');
        $user = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);

        // The current user can view hidden field IF
        // they are the owner of the submission, and/or
        // they have reviewed the submission, and/or
        // they have mod/peerform:viewunreviewed cap.
        $ownsubmission = peerformlib::userownssubmission($submissionid);
        $canseehidden = $ownsubmission ||
            peerformlib::userhasreviewed($submissionid) ||
            has_capability('mod/peerform:viewunreviewed', $context);

        // Heading.
        if ($review) {
            $outputtxt = '<div class="peerform_review">';
        } else {
            $outputtxt = '<div class="peerform_submission">';
        }

        // Create table view.
        $table = new html_table();
        $table->head = array(
            get_string('question', 'peerform'),
            get_string('answer', 'peerform'),
        );
        foreach ($fields as $field) {
            $question = "<small class=\"text-success\">{$field->description}</small>";
            $answer = $DB->get_record('peerform_answer',
                array('fieldid' => $field->id, 'submissionid' => $submissionid));
            if ($field->required && empty($answer->answer)) {
                print_error('errorrequiredmissing', 'peerform');
            }
            if ($review || !$field->hide || $canseehidden) {
                $table->data[] = array(
                    $question,
                    $answer->answer,
                );
            }
        }

        // Display comment if there is one.
        require_once($CFG->dirroot  . '/comment/lib.php');
        if ($submission->locked) {
            if (!empty($CFG->usecomments)) {
                list($thiscontext, $course, $cm) = get_context_info_array($context->id);
                $cmt = new stdClass();
                $cmt->context = $thiscontext;
                $cmt->course  = $course;
                $cmt->cm      = $cm;
                $cmt->area    = 'peerform_submission';
                $cmt->itemid  = $submission->id;
                $cmt->showcount = true;
                $cmt->component = 'mod_peerform';
                $comment = new comment($cmt);
                $table->data[] = array(
                '<small class="text-info"><strong>' . get_string('tutorcomment', 'peerform') . '</strong></small>',
                    $comment->output(true),
                );
            }
        }

        $outputtxt .= html_writer::table($table);

        // Edit link?
        $editall = has_capability('mod/peerform:editall', $context);
        if (!$review && (!$submission->locked || $editall)) {
            $editlink = new moodle_url('/mod/peerform/submit.php',
                array('id' => $peerformid, 'submission' => $submission->id));
            $outputtxt .= "<p><a class=\"btn btn-info\" href=\"$editlink\" role=\"button\">" .
                get_string('editsubmission', 'peerform') . "</a></p>";
        } else if ($review && $editall) {
            $editlink = new moodle_url('/mod/peerform/submit.php',
                array('parent' => $submission->parentid, 'submission' => $submission->id, 'review' => 1, 'id' => $peerformid));
                $outputtxt .= "<p><a class=\"btn btn-info\" href=\"$editlink\" role=\"button\">" .
                    get_string('editreview', 'peerform') . "</a></p>";
        }

        $outputtxt .= "</div>";

        if ($echo) {
            echo $outputtxt;
        } else {
            return $outputtxt;
        }
    }

    /**
     * Generate the back button to submission list
     * @param int $cmid
     */
    public function backtosubmissions($cmid) {
        $backlink = new moodle_url('/mod/peerform/view.php', array('tab' => 'submit', 'id' => $cmid));
        $back = "<a class=\"btn btn-success\" href=\"$backlink\">" . get_string('backtosubmissions', 'peerform') . "<a>";
        echo "<div>$back</div>";
    }

    /**
     * Generate the back button to reviews list
     * @param int $cmid
     * @paam int $page current page number
     */
    public function backtoreviews($cmid, $page = 0) {
        $backlink = new moodle_url('/mod/peerform/view.php', array('tab' => 'all', 'id' => $cmid, 'page' => $page));
        $back = "<a class=\"btn btn-success\" href=\"$backlink\">" . get_string('backtosubmissions', 'peerform') . "<a>";
        echo "<div>$back</div>";
    }

    public function backtosubmissionreviews($cmid, $submissionid) {
        $tab = peerformlib::userownssubmission($submissionid) ? 'submit' : 'all';
        $backlink = new moodle_url('/mod/peerform/view.php',
                array('tab' => $tab, 'id' => $cmid, 'submission' => $submissionid));
        $back = "<a class=\"btn btn-success\" href=\"$backlink\">" . get_string('backtoreviews', 'peerform') . "<a>";
        echo "<div>$back</div>";
    }

    public function backtoallreviews($cmid, $page = 0) {
        $backlink = new moodle_url('/mod/peerform/view.php',
                array('tab' => 'reviews', 'id' => $cmid, 'page' => $page));
        $back = "<a class=\"btn btn-success\" href=\"$backlink\">" . get_string('backtoallreviews', 'peerform') . "<a>";
        echo "<div>$back</div>";
    }

    /**
     * Show table of all submissions
     * (i.e. available for review)
     * @param int $cmid
     * @param int $courseid
     * @param int $peerformid
     */
    public function allsubmissions($cmid, $courseid, $peerformid, $page, $userid = null, $tabulate = true) {
        global $DB, $OUTPUT, $USER;

        if ($userid) {
            $submissions = peerformlib::mysubmissions($peerformid, $userid);
        } else {
            $submissions = peerformlib::allsubmissions($peerformid);
        }

        // Display.
        if (!$submissions) {
            if ($userid === $USER->id) {
                echo '<div class="alert alert-warning">' . get_string('nomysubmissions', 'peerform') . '</div>';
            } else if ($userid) {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                $userlink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
                $userhtml = "<a href=\"$userlink\">" . fullname($user) . "</a>";
                echo '<div class="alert alert-warning">' . get_string('nosubmissionsby', 'peerform', $userhtml) . '</div>';
            } else {
                echo '<div class="alert alert-warning">' . get_string('noothersubmissions', 'peerform') . '</div>';
            }
            return;
        }

        if ($userid === $USER->id) {
            echo '<div class="alert alert-info">' . get_string('mysubmissionsdesc', 'peerform') . '</div>';
        } else if ($userid) {
            $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
            $userlink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
            $userhtml = "<a href=\"$userlink\">" . fullname($user) . "</a>";
            echo '<div class="alert alert-info">' . get_string('submissionsbydesc', 'peerform', $userhtml) . '</div>';
        } else {
            echo '<div class="alert alert-info">' . get_string('othersubmissionsdesc', 'peerform') . '</div>';
        }

        // Prepare paging bar.
        $baseurl = new moodle_url('/mod/peerform/view.php', array('id' => $cmid, 'tab' => 'all', 'page' => $page));
        $perpage = SUBMISSIONS_PERPAGE;
        $pagingbar = new paging_bar(
            count($submissions),
            $page,
            $perpage,
            $baseurl,
            'page'
        );

        $context = context_module::instance($cmid);

        $count = 0;
        $first = $page * $perpage;
        $last = ($page + 1) * $perpage;

        echo $this->render($pagingbar);
        foreach ($submissions as $submission) {
            if (($count < $first) || ($count >= $last) && $tabulate) {
                $count++;
                continue;
            }

            echo '<div class="peerform-submission-full">';

            $user = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);
            $userlink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
            $userhtml = "<a href=\"$userlink\">" . fullname($user) . "</a>";

            if (!$userid) {
                $ownsubmission = peerformlib::userownssubmission($submission->id);
                $userlink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
                if ($ownsubmission) {
                    echo '<h3>' . get_string('mysubmission', 'peerform') . ' </h3>';
                } else {
                    echo '<h3>' . get_string('submissionby', 'peerform', $userhtml) . '</h3>';
                }
                echo '<h4>' . userdate($submission->modified) . '</h4>';
            } else {
                echo '<h3>' . userdate($submission->modified) . '</h3>';
            }

            $this->viewsubmission($peerformid, $submission->id, $context);
            $this->reviews($cmid, $courseid, $peerformid, $submission->id, $context);

            echo '</div>';
            $count++;
        }
    }

    /**
     * Get user link with 'me' for me
     * @param int $userid
     * @param int $courseid
     * @return string link html
     */
    private function userlink($userid, $courseid) {
        global $DB, $USER;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
        $userlink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
        if ($userid == $USER->id) {
            $username = get_string('me', 'peerform');
        } else {
            $username = fullname($user);
        }
        $userhtml = "<a href=\"$userlink\">$username</a>";

        return $userhtml;
    }

    /**
     * Show table of all reviews
     * @param int $cmid
     * @param int $courseid
     * @param int $peerformid
     */
    public function allreviews($cmid, $courseid, $peerformid, $page, $userid = null, $tabulate = true) {
        global $DB, $OUTPUT, $USER;

        if ($userid) {
            $reviews = peerformlib::myreviews($peerformid, $userid);
        } else {
            $reviews = peerformlib::reviews($peerformid);
        }

        echo '<div class="alert alert-info">' . get_string('allreviewsdesc', 'peerform') . '</div>';

        // Display.
        if (!$reviews) {
            echo '<div class="alert alert-warning">' . get_string('noactivityreviews', 'peerform') . '</div>';
            return;
        }

        // Prepare paging bar.
        $baseurl = new moodle_url('/mod/peerform/view.php', array('id' => $cmid, 'tab' => 'reviews', 'page' => $page));
        $perpage = SUBMISSIONS_PERPAGE;
        $pagingbar = new paging_bar(
            count($reviews),
            $page,
            $perpage,
            $baseurl,
            'page'
        );

        $context = context_module::instance($cmid);

        $count = 0;
        $first = $page * $perpage;
        $last = ($page + 1) * $perpage;
        echo $this->render($pagingbar);
        foreach ($reviews as $review) {
            if (($count < $first) || ($count >= $last) && $tabulate) {
                $count++;
                continue;
            }
            $userhtml = self::userlink($review->userid, $courseid);
            $parent = $DB->get_record('peerform_submission', array('id' => $review->parentid), '*', MUST_EXIST);
            $parentuserhtml = self::userlink($parent->userid, $courseid);
            $ownsubmission = peerformlib::userownssubmission($review->parentid);
            $ownreview = peerformlib::userownssubmission($review->id);
            $submissionuser = $DB->get_record('user', array('id' => $parent->userid), '*', MUST_EXIST);
            $reviewuser = $DB->get_record('user', array('id' => $review->userid), '*', MUST_EXIST);

            $submissionuserlink = new moodle_url('/user/view.php', array('id' => $submissionuser->id, 'course' => $courseid));
            $submissionuserhtml = "<a href=\"$submissionuserlink\">" . fullname($submissionuser) . "</a>";
            $reviewuserlink = new moodle_url('/user/view.php', array('id' => $reviewuser->id, 'course' => $courseid));
            $reviewuserhtml = "<a href=\"$reviewuserlink\">" . fullname($reviewuser) . "</a>";

            echo html_writer::start_tag('div', array('class' => 'peerform_subreview'));
            if ($ownreview) {
                if ($ownsubmission) {
                    echo '<h3>' . get_string('myreviewofmysubmission', 'peerform') . '</h3>';
                } else {
                    echo '<h3>' . get_string('myreviewofsubmissionby', 'peerform', $submissionuserhtml) . '</h3>';
                }
            } else {
                if ($ownsubmission) {
                    echo '<h3>' . get_string('reviewbyofmysubmission', 'peerform', $reviewuserhtml) . '</h3>';
                } else {
                    echo '<h3>' . get_string('reviewbyofsubmissionby', 'peerform', array("review" => $reviewuserhtml,
                            "submission" => $submissionuserhtml)) .  '</h3>';
                }
            }
            echo '<h4>' . userdate($review->modified) . '</h4>';

            $collapsedimage = 't/collapsed';
            if (right_to_left()) {
                $collapsedimage = 't/collapsed_rtl';
            } else {
                $collapsedimage = 't/collapsed';
            }

            $viewlink = new moodle_url('/mod/peerform/view.php',
                array(
                    'id' => $cmid,
                    'submission' => $review->parentid,
                    'review' => $review->id,
                    'tab' => 'reviews',
                    'page' => $page
                ));
            echo html_writer::link($viewlink, get_string('viewsubmission', 'peerform'),
                    array('class' => 'peerform-reviewviewsubnonjs'));

            $html = html_writer::start_tag('a', array('class' => 'peerform-review-submission-link',
                    'id' => 'peerform-review-submission-link-'.$review->id, 'href' => '#'));
            $html .= html_writer::empty_tag('img', array('id' => 'peerform-review-submission-img-'.$review->id,
                    'src' => $OUTPUT->pix_url($collapsedimage), 'alt' => get_string('submission', 'peerform'),
                    'title' => get_string('submission', 'peerform')));
            $html .= html_writer::tag('span', get_string('submission', 'peerform'),
                    array('id' => 'peerform-review-submission-link-text-'.$review->id));
            $html .= html_writer::end_tag('a');
            echo $html;
            echo html_writer::start_tag('div', array('id' => 'peerform-review-submission-ctrl'.$review->id,
                    'class' => 'peerform-review-submission-ctrl'));
            $this->viewsubmission($peerformid, $review->parentid, $context);
            echo html_writer::end_tag('div');

            $this->page->requires->js_call_amd('mod_peerform/peerform', 'addSubsToggleListener',
                    array('peerform-review-submission-link-'.$review->id, 'peerform-review-submission-ctrl'.$review->id,
                    'peerform-review-submission-img-'.$review->id));

            $this->viewsubmission($peerformid, $review->id, $context);
            echo html_writer::end_tag('div');

            $count++;
        }
        echo $this->render($pagingbar);

    }

    /**
     * List reviews for given submission
     * @param int $cmid
     * @param int $courseid
     * @param int $peerformid
     * @param int $submissionid
     * @param object $context
     */
    public function reviews($cmid, $courseid, $peerformid, $submissionid, $context) {
        global $DB, $OUTPUT, $USER;

        // Get reviews for supplied submission id.
        $submissions = $DB->get_records('peerform_submission', array('parentid' => $submissionid));
        $parentsubmission = $DB->get_record('peerform_submission', array('id' => $submissionid), '*', MUST_EXIST);

        // You can see reviews for submission ONLY IF...
        // You are the owner of the submission, and/or
        // You have reviewed the submission, and/or
        // You have the viewunreviewed capability.
        $ownsubmission = peerformlib::userownssubmission($submissionid);
        $canview = $ownsubmission ||
            peerformlib::userhasreviewed($submissionid) ||
            has_capability('mod/peerform:viewunreviewed', $context);
        if (!$canview) {
             echo '<div class="alert alert-warning">' . get_string('notreviewed', 'peerform') . '</div>';
             return;
        }

        // Is there anything to display.
        if (!$submissions) {
            echo '<div class="alert alert-warning">' . get_string('noreviews', 'peerform') . '</div>';
        } else {
            echo '<div class="alert alert-info">' . get_string('reviewsdesc', 'peerform') . '</div>';

            $collapsedimage = 't/collapsed';
            if (right_to_left()) {
                $collapsedimage = 't/collapsed_rtl';
            } else {
                $collapsedimage = 't/collapsed';
            }

            $table = new html_table();
            $table->colclasses = array('peerform-review-viewjs', null, 'peerform-review-viewnojs');
            $table->attributes['class'] = 'generaltable peerform-review-table';

            foreach ($submissions as $submission) {
                $tab = $ownsubmission ? 'submit' : 'all';
                $viewlink = new moodle_url('/mod/peerform/view.php',
                        array('id' => $cmid, 'submission' => $submissionid, 'review' => $submission->id, 'tab' => $tab));
                $viewhtml = "<a href=\"$viewlink\">" . get_string('view', 'peerform') . "</a>";

                $jsviewhtml = new html_table_cell;
                $jsviewhtml->id = 'peerform-review-link-'.$submission->id;
                $jsviewhtml->attributes['class'] = 'peerform-review-link';
                $jsviewhtml->text = html_writer::empty_tag('img', array('id' => 'peerform-review-img-'.$submission->id,
                        'src' => $OUTPUT->pix_url($collapsedimage), 'alt' => get_string('submission', 'peerform'),
                        'title' => get_string('submission', 'peerform')));
                $user = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);
                if ($user->id == $USER->id) {
                    $userhtml = get_string('me', 'peerform');
                } else {
                    $userlink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $courseid));
                    $userhtml = "<a href=\"$userlink\">" . fullname($user) . "</a>";
                }

                $rowcontent = html_writer::start_tag('div', array('id' => 'peerform-review-header-'. $submission->id,
                        'class' => 'peerform-review-header', 'href' => '#'));
                $rowcontent .= html_writer::tag('div', $userhtml, array('class' => 'peerform-review-user'));
                $rowcontent .= html_writer::tag('div', userdate($submission->modified), array('class' => 'peerform-review-date'));
                $rowcontent .= html_writer::end_tag('div');

                $subcontent = $this->viewsubmission($peerformid, $submission->id, $context, 0, false);

                $rowcontent .= html_writer::tag('div', $subcontent, array('class' => 'peerform-review-content-ctrl',
                        'id' => 'peerform-review-content-ctrl'.$submission->id));

                $this->page->requires->js_call_amd('mod_peerform/peerform', 'addSubsToggleListener',
                        array('peerform-review-header-'.$submission->id, 'peerform-review-content-ctrl'.$submission->id,
                        'peerform-review-img-'.$submission->id));
                $this->page->requires->js_call_amd('mod_peerform/peerform', 'addSubsToggleListener',
                        array('peerform-review-link-'.$submission->id, 'peerform-review-content-ctrl'.$submission->id,
                        'peerform-review-img-'.$submission->id));

                $row = new html_table_row;

                $row->cells = array(
                    $jsviewhtml,
                    $rowcontent,
                    $viewhtml,
                );

                $row->id = 'peerform-review-row-'.$submission->id;

                $table->rowclasses[] = 'peerform-review-row';
                $table->data[] = $row;
            }
            echo html_writer::table($table);
        }

        if (($parentsubmission->locked || peerformlib::userownssubmission($submissionid))
                && !peerformlib::userhasreviewed($submissionid) ) {
            $this->submitreviewlink($peerformid, $submissionid);
        }
    }

    /**
     * Display screen to invite user to review their own
     * submission
     * @param int cm id
     * @param int peerform id
     * @param int $submissionid
     */
    public function confirmreview($cmid, $peerformid, $submissionid) {
        echo '<div class="alert alert-info">' . get_string('confirmreview', 'peerform') . '</div>';
        echo '<div>';
        $reviewlink = new moodle_url('/mod/peerform/submit.php',
            array('id' => $peerformid, 'parent' => $submissionid, 'review' => 1));
        echo '<a class="btn btn-success" href="' . $reviewlink . '">' . get_string('reviewself', 'peerform') . '</a>';
        $cancellink = new moodle_url('/mod/peerform/view.php', array('id' => $cmid, 'tab' => 'submit'));
        echo '&nbsp;<a class="btn btn-warning" href="' . $cancellink . '">' . get_string('skip', 'peerform') . '</a>';
        echo '</div>';
    }
}
