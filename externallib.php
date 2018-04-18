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
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir . "/externallib.php");
require_once('./locallib.php');

/**
 * External web service for scanning barcodes
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_barcode_external extends external_api {
    /**
     * Save an assignment barcode submission
     * @param  string $barcode The barcode to process`
     * @return array           The response with a status code and message
     */
    public static function save_barcode_submission($barcode) {
        global $DB;

        // Clense parameters.
        $params = self::validate_parameters(
                    self::save_barcode_submission_parameters(),
                    array('barcode' => $barcode));

        $response = array();

        // If the barcode returns a record from the database then save the submission
        // and construct the response.
        $sql = "SELECT
                    b.assignmentid,
                    b.groupid,
                    b.userid,
                    b.barcode,
                    b.courseid,
                    a.name AS assignment,
                    c.fullname AS course
                FROM
                    {assignsubmission_barcode} b
                LEFT JOIN
                    {assign} a ON b.assignmentid = a.id
                LEFT JOIN
                    {course} c ON b.courseid = c.id
                WHERE
                    b.barcode = ?
        ";
        $params = array('barcode' => $barcode);

        if ($record = $DB->get_record_sql($sql, $params, IGNORE_MISSING)) {
            // If the group id is not 0 then it's a group submission and the userid
            // Doesn't matter for a submission.
            if ($record->groupid !== '0') {
                $record->userid = '0';
            }

            $submissionquery = array('assignment' => $record->assignmentid,
                                        'groupid' => $record->groupid,
                                        'userid'  => $record->userid);

            $response['data']['code']       = 200;
            $response['data']['message']    = 'Submission saved';
            $response['data']['assignment'] = $record->assignment;
            $response['data']['course']     = $record->course;

            $submission = $DB->get_record('assign_submission', $submissionquery, '*', IGNORE_MISSING);

            // Check for same day submission.
            if ($submission) {
                $now          = new DateTime();
                $lastmodified = new DateTime('@' . $submission->timemodified);
                // Difference in days as a string.
                $diff = $now->diff($lastmodified)->format("%a");

                if ($diff === '0' && $submission->status === 'submitted') {
                    $sameday = true;
                    $response['data']['code']    = 422;
                    $response['data']['message'] = 'Barcode already scanned today';
                } else {
                    $sameday = false;
                }
            }
        }

        // If the record exists in the assignment_barcode table, check the submission
        // exists in the assign_submission table too. It should exists as either 'new'
        // or 'draft' submission before the status is updated to 'submitted'.
        if ($record && $submission && ! $sameday) {
            // Update the database with the submitted status.
            $date                 = new DateTime();
            $timestamp            = $date->getTimestamp();

            $update               = new stdClass();
            $update->id           = $submission->id;
            $update->timemodified = $timestamp;
            $update->status       = 'submitted';
            $update->latest       = 1;
            $DB->update_record('assign_submission', $update);
        }

        if ($record && ! $submission) {
            $date      = new DateTime();
            $timestamp = $date->getTimestamp();

            settype ($submissionquery, 'object');

            $submissionquery->status        = 'submitted';
            $submissionquery->timecreated   = $timestamp;
            $submissionquery->timemodified  = $timestamp;
            $submissionquery->attemptnumber = 1;
            $submissionquery->latest        = 1;
            $DB->insert_record('assign_submission', $submissionquery);

            $response['data']['code']   = 200;
            $response['data']['message'] = 'Submission saved';
        }

        // If the barcode was not found then return a 404.
        if (! $record) {
            $response['data']['code']       = 404;
            $response['data']['message']    = 'Barcode not found';
            $response['data']['assignment'] = '';
            $response['data']['course']     = '';
        }

        return $response;
    }

    /**
     * Returns the description of the method parameters
     * @return external_function_parameters
     */
    public static function save_barcode_submission_parameters() {
        return new external_function_parameters(
            array(
                'barcode' => new external_value(PARAM_TEXT, 'barcode')
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
                        'code'       => new external_value(PARAM_INT, 'http status code'),
                        'message'    => new external_value(PARAM_TEXT, 'status message confirming either success or failure'),
                        'assignment' => new external_value(PARAM_TEXT, 'the assignment name'),
                        'course'     => new external_value(PARAM_TEXT, 'the course name'),
                    )
                )
            )
        );
    }
}