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
 * This file contains a renderer for the custom_summary_grading_form class
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');

/**
 * Extend the assign class, allowing access to the assign class while extending it's functionality
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class barcode_assign extends assign {
    /**
     * Is this assignment open for submissions?
     *
     * Check the due date,
     * prevent late submissions,
     * has this person already submitted,
     * is the assignment locked?
     *
     * @param int $userid - Optional userid so we can see if a different user can submit
     * @param bool $skipenrolled - Skip enrollment checks (because they have been done already)
     * @param stdClass $flags - Pre-fetched user flags record (or false to fetch it)
     * @param stdClass $gradinginfo - Pre-fetched user gradinginfo record (or false to fetch it)
     * @return bool
     */
    public function student_submission_is_open($userid = 0,
                                               $skipenrolled = false,
                                               $flags = false,
                                               $gradinginfo = false) {
        $time      = time();
        $dateopen  = true;
        $finaldate = false;

        if ($this->get_instance()->cutoffdate) {
            $finaldate = $this->get_instance()->cutoffdate;
        }

        if ($flags === false) {
            $flags = $this->get_user_flags($userid, false);
        }

        if ($flags && $flags->locked) {
            return false;
        }

        // User extensions.
        if ($finaldate) {
            if ($flags && $flags->extensionduedate) {
                // Extension can be before cut off date.
                if ($flags->extensionduedate > $finaldate) {
                    $finaldate = $flags->extensionduedate;
                }
            }
        }

        if ($finaldate) {
            $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time && $time <= $finaldate);
        } else {
            $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time);
        }

        if (!$dateopen) {
            return false;
        }

        // See if this user grade is locked in the gradebook.
        if ($gradinginfo === false) {
            $gradinginfo = grade_get_grades($this->get_course()->id,
                                            'mod',
                                            'assign',
                                            $this->get_instance()->id,
                                            array($userid));
        }

        if ($gradinginfo &&
                isset($gradinginfo->items[0]->grades[$userid]) &&
                $gradinginfo->items[0]->grades[$userid]->locked) {
            return false;
        }

        return true;
    }


    /**
     * Revert to draft.
     *
     * @param int $userid
     * @return boolean
     */
    public function revert_to_draft($userid) {
        global $USER;

        if ($this->get_instance()->teamsubmission) {
            $submission = $this->get_group_submission($userid, 0, false);
        } else {
            $submission = $this->get_user_submission($userid, false);
        }

        if (!$submission) {
            return false;
        }
        $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $this->update_submission($submission, $userid, true, $this->get_instance()->teamsubmission);

        // Update the modified time on the grade (grader modified).
        $grade = $this->get_user_grade($userid, true);
        $grade->grader = $USER->id;
        $this->update_grade($grade);

        $completion = new completion_info($this->get_course());
        if ($completion->is_enabled($this->get_course_module()) &&
                $this->get_instance()->completionsubmit) {
            $completion->update_state($this->get_course_module(), COMPLETION_INCOMPLETE, $userid);
        }
        \mod_assign\event\submission_status_updated::create_from_submission($this, $submission)->trigger();
        return true;
    }


    /**
     * Notify both student and graders where the submission has notifications enabled
     *
     * @param string $userid The id of the student
     * @param assign $assign The assign object of the particular assignment
     * @return void
     */
    public function notify_users($userid, $assign) {
        $submission = $this->get_user_submission($userid, false);
        // If notifications have to be sent to the graders then send the notification.
        if ($this->get_instance()->sendnotifications) {
            notify_graders($submission);
        }

        // If notifying students.
        if ($this->get_instance()->sendstudentnotifications) {
            notify_student_submission_receipt($submission, $assign);
        }
        return;
    }


    /**
     * Send an email to the student confirming a submmission has been reverted to draft status.
     *
     * @param  object $data The data used to construct the email
     * @return void
     */
    public function send_revert_to_draft_email($data) {
        $email = new stdClass();
        $email->userto          = $data->user;
        $email->replyto         = get_config('noreplyaddress');
        $email->replytoname     = get_string('reverttodraftreplyname', 'local_barcode');
        $email->userfrom        = get_string('reverttodraftfromuser', 'local_barcode');
        $email->subject         = get_string('reverttodraftemailsubject', 'local_barcode');
        $email->fullmessage     = get_string('reverttodraftemailnonhtml',
                                    'local_barcode',
                                    ['linkurl' => $data->linkurl, 'linktext' => $data->linktext]);
        $email->fullmessagehtml = '<p>' .
                                  get_string('reverttodraftemail',
                                    'local_barcode',
                                    ['linkurl' => $data->linkurl, 'linktext' => $data->linktext]) .
                                  '</p>';
        email_to_user($email->userto, $email->userfrom, $email->subject, $email->fullmessage, $email->fullmessagehtml, '', '');
    }
}
