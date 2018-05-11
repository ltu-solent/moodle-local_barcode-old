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
 * This file contains functions for the local barcode plugin that the non-JavaScript users will use.
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


/**
 * Save barcode submission
 *
 * @param  object $barcoderecord The barcode data related to the submission
 * @param  assign $assign        An assign instance related to the submission
 * @return array                 The response as an array
 */
function save_submission($barcoderecord, assign $assign) {
    global $DB, $USER;

    $response = array();

    $submission = $DB->get_record('assign_submission', array('id' => $barcoderecord->submissionid), '*', IGNORE_MISSING);

    if ($submission && $submission->status !== 'submitted') {
        $update = new stdclass();
        $update->id           = $submission->id;
        $update->timemodified = time();
        $update->status       = 'submitted';
        $DB->update_record('assign_submission', $update, false);

        $response['data']['code']    = 200;
        $response['data']['message'] = get_string('submissionsaved', 'local_barcode');
        return $response;
    }

    if ($submission && $submission->status === 'submitted') {
        $response['data']['code']    = 422;
        $response['data']['message'] = get_string('alreadysubmitted', 'local_barcode');
        return $response;
    }

    if (! $submission) {
        $response['data']['code']    = 404;
        $response['data']['message'] = get_string('submissionnotfound', 'local_barcode');
        return $response;
    }

    // A lovely little catch all for the blue moon occasion.
    $response['data']['code']    = 418;
    $response['data']['message'] = get_string('catchall', 'local_barcode');
    return $response;
}


/**
 * Is the submission being scanned on the same day
 * @param  int $timemodified    Timestamp of the database record to check
 * @return boolean              Returns true if the time modified is the same day as today
 */
function same_day_submission($timemodified) {
    $now          = new DateTime();
    $lastmodified = new DateTime('@' . $timemodified);

    // Difference in days as a string.
    $diff = $now->diff($lastmodified)->format("%a");

    if ($diff === '0') {
        return true;
    }
    return false;
}


/**
 * Get the web service token for authorised users
 * By default there is only one user setup to generate a token, this is the
 * admin user. This function looks for ony the first token, which is returned
 * @return string   Web Service Token
 */
function get_wstoken() {
    global $DB;
    $sql = 'SELECT et.token
              FROM {external_tokens} et
              JOIN {external_services} es ON es.id = et.externalserviceid
             WHERE es.name = ? ';

    return $DB->get_field_sql($sql, array('Barcode Scanning'), IGNORE_MULTIPLE);
}


/**
 * Notify student upon successful submission.
 *
 * @param stdClass $submission  The submission object
 * @param assign   $assign      An assign instance
 * @return void
 */
function notify_student_submission_receipt(stdClass $submission, assign $assign) {
    global $DB, $USER;

    $adminconfig = $assign->get_admin_config();

    if (empty($adminconfig->submissionreceipts)) {
        // No need to do anything.
        return;
    }

    $student = $DB->get_record('user', array('id' => $submission->userid), '*', IGNORE_MISSING);
    $assign->send_notification($USER,
                             $student,
                             'submissionreceiptother',
                             'assign_notification',
                             $submission->timemodified);
}


/**
 * Send notifications to graders upon submissions.
 *
 * @param stdClass $submission  The submission
 * @param assign $assign        An assign instance
 * @return void
 */
function notify_graders(stdClass $submission, assign $assign) {
    global $USER;

    $instance = $assign->get_instance();
    $late     = $instance->duedate && ($instance->duedate < time());

    if (!$instance->sendnotifications && !($late && $instance->sendlatenotifications)) {
        // No need to do anything.
        return;
    }

    if ($notifyusers = $assign->get_notifiable_users($user->id)) {
        foreach ($notifyusers as $notifyuser) {
            $assign->send_notification($USER,
                                     $notifyuser,
                                     'gradersubmissionupdated',
                                     'assign_notification',
                                     $submission->timemodified);
        }
    }
}

function save_late_submission($barcoderecord, assign $assign) {
    global $DB, $USER;

    $response = array();

    $sql = "SELECT b.assignmentid,
                   b.groupid,
                   b.userid,
                   b.barcode,
                   b.courseid,
                   b.submissionid,
                   a.name AS assignment,
                   a.intro AS assignmentdescription,
                   a.duedate,
                   a.blindmarking,
                   c.fullname AS course,
                   u.firstname,
                   u.lastname,
                   m.id AS participantid,
                   s.status
              FROM {assignsubmission_barcode} b
              JOIN {assign} a ON b.assignmentid = a.id
              JOIN {course} c ON b.courseid = c.id
              JOIN {user} u ON b.userid = u.id
              JOIN {assign_submission} s ON b.submissionid = s.id
         LEFT JOIN {assign_user_mapping} m ON b.userid = m.userid AND b.assignmentid = m.assignment
             WHERE b.barcode = ?";

    if ($submission = $DB->get_record_sql($sql, array('barcode' => $barcoderecord->barcode), IGNORE_MISSING)) {

        if ($submission->status !== 'submitted') {
            $update = new stdclass();
            $update->id           = $submission->submissionid;
            $update->timemodified = $submission->duedate;
            $update->status       = 'submitted';
            $DB->update_record('assign_submission', $update, false);

            $response['data']['code']    = 200;
            $response['data']['message'] = get_string('submissionontime', 'local_barcode');
            return $response;
        }

        if ($submission->status === 'submitted') {
            $response['data']['code']    = 422;
            $response['data']['message'] = get_string('alreadysubmitted', 'local_barcode');
            return $response;
        }

    }

    if (! $submission) {
        $response['data']['code']    = 404;
        $response['data']['message'] = get_string('submissionnotfound', 'local_barcode');
        return $response;
    }

    // A lovely little catch all for the blue moon occasion.
    $response['data']['code']    = 418;
    $response['data']['message'] = get_string('catchall', 'local_barcode');
    return $response;
}
