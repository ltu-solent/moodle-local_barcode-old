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
require_once('../../config.php');
require_once($CFG->libdir  . '/pagelib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once('locallib.php');
require_once('./classes/barcode_assign.php');
require_once('./classes/upload_submission.php');
require_once('./classes/event/submission_updated.php');

$context = context_system::instance();
$id      = $context->id;

require_login();
require_capability('assignsubmission/barcode:scan', $context);
var_dump(get_wstoken());
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pageheading', 'local_barcode'));
$PAGE->set_url(new moodle_url('/local/barcode/assign/submission.php'));
$PAGE->navbar->add(get_string('navigationbreadcrumb', 'local_barcode'), new moodle_url('/local/barcode/assign/submission.php'));



echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('barcodeheading', 'local_barcode'), 2, null, 'page_heading');
echo $OUTPUT->footer();
