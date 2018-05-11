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
 * This file contains the class for uploading barcode submissions
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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
     * The revert to draft value
     * @var string
     */
    private $revert;
    /**
     * Mark the submission as on time, rather than late
     * @var string
     */
    private $ontime;

    /**
     * Set the domain name, token and the barcode values
     */
    public function __construct() {
        $this->domainname = $this->get_domainname();
        $this->token      = $this->get_token();
        $this->barcode    = $this->get_barcode();
        $this->revert     = $this->get_revert();
        $this->ontime     = $this->get_ontime();
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
     * Get the revert url parameter value
     * @return string Returns '1' for a revert to draft or '0' for a submission
     */
    protected function get_revert() {
        return required_param('revert', PARAM_TEXT);
    }


    /**
     * Get the ontime url parameter
     */
    protected function  get_ontime() {
        return required_param('ontime', PARAM_TEXT);
    }


    /**
     * Save the barcode submission in the database
     *
     * @return object   Status object confirming either 200 or 404
     */
    public function save_submission() {
        header('Content-Type: text/plain');
        $serverurl = $this->domainname . '/webservice/xmlrpc/server.php'. '?wstoken=' . $this->token;
        $curl = new curl;
        $post = xmlrpc_encode_request($this->functionname, array($this->barcode, $this->revert, $this->ontime));
        $resp = xmlrpc_decode($curl->post($serverurl, $post));

        echo json_encode($resp);
    }
}
