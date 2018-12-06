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
 * This file contains the class for sending group emails informing them that the assignemnt has been reverted to draft
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_barcode\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/lib/grouplib.php');


/**
 * For group assignments that have been reverted to draft status, inform each group member.
 *
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_group_revert_to_draft extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $CFG;

        $data = $this->get_custom_data();
        if ($groupmembers = groups_get_members($data->groupid)) {
            foreach ($groupmembers as $member) {
                $email = new \stdClass();
                $email->userto          = $member;
                $email->replyto         = $CFG->noreplyaddress;
                $email->replytoname     = get_string('reverttodraftreplyname', 'local_barcode');
                $email->userfrom        = get_string('reverttodraftfromuser', 'local_barcode');
                $email->subject         = get_string('reverttodraftemailsubject', 'local_barcode');
                $email->fullmessage     = get_string('reverttodraftemailnonhtml',
                                                     'local_barcode',
                                                     ['linkurl' => $data->emaildata->linkurl, 'linktext' => $data->emaildata->inktext]);
                $email->fullmessagehtml = '<p>' .
                                          get_string('reverttodraftemail',
                                                     'local_barcode',
                                                     ['linkurl' => $data->emaildata->linkurl, 'linktext' => $data->emaildata->linktext]) .
                                          '</p>';
                email_to_user($email->userto, $email->userfrom, $email->subject, $email->fullmessage, $email->fullmessagehtml);
            }
        }
    }

}
