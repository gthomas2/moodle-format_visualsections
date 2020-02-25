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
require_once($CFG->dirroot. '/course/format/singleactivity/settingslib.php');

if ($ADMIN->fulltree) {
    $acts    = get_module_types_names();
    $choices = ['_' => new lang_string('noselection', 'format_visualsections')];
    foreach ($acts as $key => $act) {
        $choices[$key] = $act;
    }

    $acts = 5;
    for ($a = 1; $a <= $acts; $a++) {
        $settings->add(new admin_setting_configselect(
            'format_visualsections/activitypriority'.$a,
            get_string('activitypriority', 'format_visualsections', $a),
            get_string('activityprioritydesc', 'format_visualsections', $a),
            '_',
            $choices
        ));
    }
}
