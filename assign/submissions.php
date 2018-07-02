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
 * Upload barcode submissions
 *
 * @package    local_barcode
 * @copyright  2018 Coventry University
 * @author     Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir  . '/pagelib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once('../barcode_submission_form.php');
require_once('../locallib.php');
require_once('../classes/barcode_assign.php');
require_once('../classes/upload_submission.php');
require_once('../classes/event/submission_updated.php');

$context = context_system::instance();
$id      = $context->id;

require_login();
require_capability('assignsubmission/barcode:scan', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pageheading', 'local_barcode'));
$PAGE->set_url(new moodle_url('/local/barcode/assign/submission.php'));
$PAGE->navbar->add(get_string('navigationbreadcrumb', 'local_barcode'), new moodle_url('/local/barcode/assign/submission.php'));
$PAGE->requires->js_call_amd('local_barcode/index', 'init', array($id, true));

 // Process the submitted form. Process the barcode and return the user to the grading
 // summary page or set the error to display.
$mform   = new barcode_submission_form();
$error   = '';
$success = '';
$barcode = '';
$isopen  = true;
$multiplescans = '0';

if ($mform->is_cancelled()) {
    $url = new moodle_url('/my/', []);
    redirect($url);
} else if ($formdata = $mform->get_submitted_data()) {
    global $DB;

    if (! empty($formdata->barcode)) {
        $conditions = array('barcode' => $formdata->barcode);
        $record = $DB->get_record('assignsubmission_barcode', $conditions, '*', IGNORE_MISSING);
        // Process the barcode & submission.

        if ($record) {
            // Set the assignment context for declaring a new barcode_assign instance.
            $cmid                          = $record->cmid;
            list($assigncourse, $assigncm) = get_course_and_cm_from_cmid($cmid, 'assign');
            $assigncontext                 = context_module::instance($assigncm->id);

            $assign = new barcode_assign($assigncontext, $assigncm, $assigncourse);
            $isopen = $assign->student_submission_is_open($record->userid, false, false, false);

            $submissionrecord = $DB->get_record('assign_submission', array('id' => $record->submissionid), '*', IGNORE_MISSING);

            if ($isopen) {
                if ($formdata->reverttodraft === '0' && $formdata->submitontime === '0') {
                    $response = save_submission($record, $assign);

                    if ($response['data']['code'] !== 200) {
                        $error = $response['data']['message'];
                    } else {
                        $success = $response['data']['message'];
                        $assign->notify_users($record->userid, $assign);
                        $params = array(
                            'context'       => $assigncontext,
                            'courseid'      => $assigncourse->id,
                            'objectid'      => $record->submissionid,
                            'relateduserid' => $record->userid,
                            'other'         => array(
                                'submissionid'      => $submissionrecord->id,
                                'submissionattempt' => $submissionrecord->attemptnumber,
                                'submissionstatus'  => $submissionrecord->status,
                            ),
                        );
                        $event = local_barcode\event\submission_updated::create($params);
                        $event->trigger();
                    }

                } else if ($formdata->reverttodraft === '1' && $formdata->submitontime === '0') {
                    $assign->revert_to_draft($record->userid);
                    $success = get_string('reverttodraftresponse', 'local_barcode');
                    $assign->notify_users($record->userid, $assign);
                    $params = array(
                        'context'       => $assigncontext,
                        'courseid'      => $assigncourse->id,
                        'objectid'      => $record->submissionid,
                        'relateduserid' => $record->userid,
                        'other'         => array(
                            'submissionid'      => $submissionrecord->id,
                            'submissionattempt' => $submissionrecord->attemptnumber,
                            'submissionstatus'  => $submissionrecord->status,
                        ),
                    );
                    $event = local_barcode\event\submission_updated::create($params);
                    $event->trigger();
                } else if ($formdata->reverttodraft === '0' && $formdata->submitontime === '1') {
                    $response = save_late_submission($record, $assign);

                    if ($response['data']['code'] !== 200) {
                        $error = $response['data']['message'];
                    } else {
                        $success = $response['data']['message'];
                        $assign->notify_users($record->userid, $assign);
                        $params = array(
                            'context'       => $assigncontext,
                            'courseid'      => $assigncourse->id,
                            'objectid'      => $record->submissionid,
                            'relateduserid' => $record->userid,
                            'other'         => array(
                                'submissionid'      => $submissionrecord->id,
                                'submissionattempt' => $submissionrecord->attemptnumber,
                                'submissionstatus'  => $submissionrecord->status,
                            ),
                        );
                        $event = local_barcode\event\submission_updated::create($params);
                        $event->trigger();
                    }

                } else if ($formdata->reverttodraft === '1' && $formdata->submitontime === '1') {
                    $error = get_string('draftandsubmissionerror', 'local_barcode');
                }
            }

            if (! $isopen) {
                if ($formdata->reverttodraft === '0' && $formdata->submitontime === '0') {
                    $error   = get_string('submissionclosed', 'local_barcode');
                    $barcode = $formdata->barcode;
                } else if ($formdata->reverttodraft === '1' && $formdata->submitontime === '0') {
                    $error   = get_string('submissionclosed', 'local_barcode');
                    $barcode = $formdata->barcode;
                } else if ($formdata->reverttodraft === '0' && $formdata->submitontime === '1') {
                    $response = save_late_submission($record, $assign);

                    if ($response['data']['code'] !== 200) {
                        $error = $response['data']['message'];
                    } else {
                        $success = $response['data']['message'];
                        $assign->notify_users($record->userid, $assign);
                        $params = array(
                            'context'       => $assigncontext,
                            'courseid'      => $assigncourse->id,
                            'objectid'      => $record->submissionid,
                            'relateduserid' => $record->userid,
                            'other'         => array(
                                'submissionid'      => $submissionrecord->id,
                                'submissionattempt' => $submissionrecord->attemptnumber,
                                'submissionstatus'  => $submissionrecord->status,
                            ),
                        );
                        $event = local_barcode\event\submission_updated::create($params);
                        $event->trigger();
                    }

                } else if ($formdata->reverttodraft === '1' && $formdata->submitontime === '1') {
                    $error   = get_string('submissionclosed', 'local_barcode');
                    $barcode = $formdata->barcode;
                }
            }

        } else {
            $error   = get_string('barcodenotfound', 'local_barcode');
            $barcode = $formdata->barcode;
        }

    } else {
        $error = get_string('barcodeempty', 'local_barcode');
    }

}

$mform = new barcode_submission_form("./submissions.php?id=$id&action=scanning",
            array(
                'cmid'          => $id,
                'error'         => $error,
                'barcode'       => $barcode,
                'success'       => $success,
                'multiplescans' => $multiplescans,
            ),
            'post',
            '',
            'id="id_barcode_form"');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('barcodeheading', 'local_barcode'), 2, null, 'page_heading');
$mform->display();
echo $OUTPUT->footer();
