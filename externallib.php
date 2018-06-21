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
 * External Web Service For Handling Barcode Scanning
 *
 * @package   local_barcode
 *
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir . "/externallib.php");
require_once('locallib.php');
require_once($CFG->dirroot . '/mod/assign/submission/physical/lib.php');

/**
 * External web service for scanning barcodes
 * @package   local_barcode
 *
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_barcode_external extends external_api {
    /**
     * Save an assignment barcode submission
     * @param  string $barcode The barcode to process`
     * @param  string $revert  Revert to draft indicator
     * @param  string $ontime  Mark the submission as on time
     * @return array  The response with a status code and message
     */
    public static function save_barcode_submission(
        $barcode,
        $revert,
        $ontime
    ) {
        global $DB, $USER;
        // Clense parameters.
        $params = self::validate_parameters(
            self::save_barcode_submission_parameters(),
            array('barcode' => $barcode, 'revert' => $revert, 'ontime' => $ontime));

        // Remove extra params as they aren't used in $DB->get_record_sql, the barcode is.
        $revert = $params['revert'];
        $submitontime = $params['ontime'];
        unset($params['revert']);
        unset($params['ontime']);

        $response = array(
            'data' => array(
                'assignment'            => '',
                'course'                => '',
                'studentname'           => '',
                'idformat'              => '',
                'studentid'             => '',
                'participantid'         => 0,
                'duedate'               => '',
                'submissiontime'        => '',
                'assignmentdescription' => '',
                'islate'                => 0,
                'reverted'              => 0,
            ),
        );

        // Get the user name setting from plugin configs table.
        $conditions = array('plugin' => 'assignsubmission_physical', 'name' => 'usernamesettings');
        $username   = $DB->get_record('config_plugins', $conditions, 'value', IGNORE_MISSING);

        // If the username doesn't exist then return the error.

        if (!$username) {
            $response['data']['code']    = 404;
            $response['data']['message'] = get_string('missinguseridentifier', 'local_barcode');
            return $response;
        }

        // If the barcode returns a record from the database then save the submission
        // and construct the response.
        $sql = 'SELECT b.assignmentid,
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
                       m.id AS participantid
                  FROM {assignsubmission_barcode} b
                  JOIN {assign} a ON b.assignmentid = a.id
                  JOIN {course} c ON b.courseid = c.id
                  JOIN {user} u ON b.userid = u.id
             LEFT JOIN {assign_user_mapping} m ON b.userid = m.userid AND b.assignmentid = m.assignment
                 WHERE b.barcode = ?';

        if ($record = $DB->get_record_sql($sql, $params, IGNORE_MISSING)) {
            // Get the username details.

            if (!$userdetails = get_username(array($record->userid, $username->value))) {
                $response['data']['code']    = 404;
                $response['data']['message'] = get_string('missingstudentid', 'local_barcode');
                return $response;
            }

            // If the group id is not 0 then it's a group submission and the userid
            // Doesn't matter for a submission.

            if ('0' !== $record->groupid) {
                $record->userid = '0';
            }

            $submission = $DB->get_record('assign_submission', array('id' => $record->submissionid), '*', IGNORE_MISSING);

            $now       = new DateTime();
            $timestamp = $now->getTimestamp();

            $response['data']['assignment']            = $record->assignment;
            $response['data']['course']                = $record->course;
            $response['data']['studentname']           = $record->firstname . ' ' . $record->lastname;
            $response['data']['idformat']              = $userdetails->name;
            $response['data']['studentid']             = $userdetails->data;
            $response['data']['participantid']         = $record->participantid;
            $response['data']['duedate']               = date('jS F, \'y G:i', $record->duedate);
            $response['data']['submissiontime']        = date('jS F, \'y g:i:s a', $timestamp);
            $response['data']['assignmentdescription'] = strip_tags($record->assignmentdescription);
            $response['data']['islate']                = (($record->duedate - $timestamp) < 0) ? 1 : 0;

            if (!$submission) {
                settype($submissionquery, 'object');
                $submissionquery->assignment   = $record->assignmentid;
                $submissionquery->userid       = $record->userid;
                $submissionquery->status       = 'submitted';
                $submissionquery->timecreated  = $timestamp;
                $submissionquery->timemodified = $timestamp;
                $submissionquery->latest       = 1;
                $DB->insert_record('assign_submission', $submissionquery);

                $response['data']['code']    = 200;
                $response['data']['message'] = get_string('submissionsaved', 'local_barcode');
                return $response;
            }

            // If the submission has already been submitted, revert to draft.

            if ('1' === $revert && $submission && 'submitted' === $submission->status) {
                $update               = new stdClass();
                $update->id           = $submission->id;
                $update->timemodified = $timestamp;
                $update->status       = 'draft';
                $DB->update_record('assign_submission', $update);

                $response['data']['code']     = 200;
                $response['data']['message']  = get_string('reverttodraftresponse', 'local_barcode');
                $response['data']['reverted'] = 1;
                return $response;
            }

            // Allow late submission.

            if ('1' === $submitontime && $submission && 'submitted' !== $submission->status) {
                $update               = new stdClass();
                $update->id           = $submission->id;
                $update->timemodified = $record->duedate;
                $update->status       = 'submitted';
                $DB->update_record('assign_submission', $update);

                $response['data']['code']    = 200;
                $response['data']['message'] = get_string('submissionontime', 'local_barcode');
                return $response;
            }

            // Check for same day submission.

            if ($submission && 'submitted' === $submission->status) {
                $lastmodified = new DateTime('@' . $submission->timemodified);
                // Difference in days as a string.
                $diff = $now->diff($lastmodified)->format('%a');

                if ('0' === $diff) {
                    $response['data']['code']    = 422;
                    $response['data']['message'] = get_string('barcodesameday', 'local_barcode');
                    return $response;
                }

                $response['data']['code']    = 422;
                $response['data']['message'] = get_string('alreadysubmitted', 'local_barcode');
                return $response;
            }

            if ($submission) {
                // Update the database with the submitted status.
                $update               = new stdClass();
                $update->id           = $submission->id;
                $update->timemodified = $timestamp;
                $update->status       = 'submitted';
                $DB->update_record('assign_submission', $update);

                $response['data']['code']    = 200;
                $response['data']['message'] = get_string('submissionsaved', 'local_barcode');
                return $response;
            }
        }

        // If the barcode was not found then return a 404.

        if (!$record) {
            $response['data']['code']    = 404;
            $response['data']['message'] = 'Barcode not found';
            return $response;
        }

        // A lovely little catch all for the blue moon occasion.
        $response['data']['code']    = 418;
        $response['data']['message'] = get_string('catchall', 'local_barcode');
        return $response;
    }

    /**
     * Returns the description of the method parameters
     * @return external_function_parameters
     */
    public static function save_barcode_submission_parameters() {
        return new external_function_parameters(
            array(
                'barcode' => new external_value(PARAM_TEXT, 'barcode'),
                'revert'  => new external_value(PARAM_TEXT, 'revert'),
                'ontime'  => new external_value(PARAM_TEXT, 'ontime'),
            )
        );
    }

    /**
     * The return status from a request
     * @return array an array with a http code and message
     */
    public static function save_barcode_submission_returns() {
        return new external_function_parameters(
            array(
                'data' => new external_single_structure(
                    array(
                        'code'                  => new external_value(PARAM_INT, 'http status code'),
                        'message'               => new external_value(PARAM_TEXT, 'status message confirming either success or failure'),
                        'assignment'            => new external_value(PARAM_TEXT, 'the assignment name'),
                        'course'                => new external_value(PARAM_TEXT, 'the course name'),
                        'studentname'           => new external_value(PARAM_NOTAGS, 'the name of the student'),
                        'idformat'              => new external_value(PARAM_TEXT, 'the student identifier format'),
                        'studentid'             => new external_value(PARAM_RAW, 'the student identifier'),
                        'participantid'         => new external_value(PARAM_INT, 'if blind marking is in use then replace the student id'),
                        'duedate'               => new external_value(PARAM_TEXT, 'the assignment due date'),
                        'submissiontime'        => new external_value(PARAM_TEXT, 'the current time'),
                        'assignmentdescription' => new external_value(PARAM_RAW, 'assignment description'),
                        'islate'                => new external_value(PARAM_INT, 'whether or not the assignment has been submitted late'),
                        'reverted'              => new external_value(PARAM_INT, 'whether ot not the submission has been reverted to draft'),
                    )
                ),
            )
        );
    }
}
