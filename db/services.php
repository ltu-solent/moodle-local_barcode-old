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
 * Strings for component 'local_barcode', language 'en'.
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

$functions = array(
    'local_barcode_save_barcode_submission' => array(
        'classname'    => 'local_barcode_external',
        'methodname'   => 'save_barcode_submission',
        'classpath'    => 'local/barcode/externallib.php',
        'description'  => 'This documentation will be displayed in the generated API documentation
                            (Administration > Plugins > Webservices > API documentation)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'assignsubmission/physical:scan',
        'services'     => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    )
);

$services = array(
    'Barcode Scanning' => array(
        'functions'       => array ('local_barcode_save_barcode_submission'),
        'restrictedusers' => 1,
        'requiredcapability' => 'assignsubmission/physical:scan',
        'enabled'         => 1,
        'shortname'       => 'barcode'
    )
);