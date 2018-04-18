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
 * This file contains functions for the local barcode plugin
 *
 * An XMLRPC client based on a web service example by Jerome Mouneyrac
 * @see https://github.com/moodlehq/moodle-local_wstemplate
 *
 * This script does not depend of any Moodle code,
 * and it can be called from a browser.
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../../../lib/moodlelib.php');
require_once('../../../lib/filelib.php');
require_once('../locallib.php');

$id                = optional_param('id', 0, PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');
$context           = context_module::instance($cm->id);

require_login($course, true, $cm);

/**
 * Upload physical submission via a web service
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_submission {

    /**
     * The name of the plugin function to use for submissions
     * @var string
     */
    private $functionname = 'local_barcode_save_barcode_submission';
    /**
     * The domain name for the application. eg. http://www.example.com
     * @var string
     */
    private $domainname;
    /**
     * The auth token parameter
     * @var string
     */
    private $token;
    /**
     * The barcode parameter
     * @var string
     */
    private $barcode;


    /**
     * Set the domain name, token and the barcode values
     */
    public function __construct() {
        $this->domainname = $this->get_domainname();
        $this->token      = $this->get_token();
        $this->barcode    = $this->get_barcode();
    }


    /**
     * Get the barcode url parameter
     * @return string
     */
    public function get_barcode() {
        return required_param('barcode', PARAM_ALPHANUM);
    }


    /**
     * Get the auth token for the local plugin
     *
     * During installation the admin will authorise an admin user which will
     * produce a token for the plugin. This function gets the token which is
     * passed as a url query parameter
     * @return string   string token for authorisation
     */
    public function get_token() {
        return get_wstoken();
    }


    /**
     * Get the root domain eg. http://www.example.com
     *
     * @return string
     */
    public function get_domainname() {
        global $CFG;
        return $CFG->wwwroot;
    }


    /**
     * Save the barcode submission in the database
     *
     * @return object   Status object confirming either 200 or 404
     */
    public function save_submission() {
        $barcode = required_param('barcode', PARAM_ALPHANUM);

        header('Content-Type: text/plain');
        $serverurl = $this->domainname . '/webservice/xmlrpc/server.php'. '?wstoken=' . $this->token;
        $curl = new curl;
        $post = xmlrpc_encode_request($this->functionname, array($this->barcode));
        $resp = xmlrpc_decode($curl->post($serverurl, $post));

        echo json_encode($resp);
    }
}

// Save the new submission to the database.
$upload = new upload_submission();
$upload->save_submission();
