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
 * Settings for format_visualsections
 *
 * @package    format_visualsections
 * @copyright  2020 Guy Thomas (citri.city)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use format_visualsections\adminsetting\subsectiontypes;

require_once($CFG->dirroot. '/course/format/singleactivity/settingslib.php');
// Note - even though this is a class that will work with autoloading, we need to load it up using
// require because the autoloader might not have registered the class by the time the settings are
// loaded.
require_once($CFG->dirroot. '/course/format/visualsections/classes/adminsetting/subsectiontypes.php');

if ($ADMIN->fulltree) {
    $settings->add(new subsectiontypes(
        'format_visualsections/subsectiontypes',
        get_string('subsectiontypes', 'format_visualsections'),
        get_string('subsectiontypes_desc', 'format_visualsections'),
        ''
    ));
}