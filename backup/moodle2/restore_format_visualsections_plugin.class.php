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
 * Specialised restore for format_visualsections
 *
 * @package   format_visualsections
 * @category  backup
 * @copyright 2017 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Specialised restore for format_visualsections
 *
 * Processes 'numsections' from the old backup files and hides sections that used to be "orphaned"
 *
 * @package   format_visualsections
 * @category  backup
 * @copyright 2020 Citricity Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_visualsections_plugin extends restore_format_topics_plugin {

    /**
     * Executed after course restore is complete
     *
     * This method is only executed if course configuration was overridden
     */
    public function after_restore_course() {
        global $DB;

        $backupinfo = $this->step->get_task()->get_info();
        if ($backupinfo->original_course_format !== 'visualsections') {
            // Backup from another course format or backup.
            return;
        }

        $courseid = $this->task->get_courseid();

        // Get section numbers by original section ids.
        $sectnumsbyid = [];
        foreach ($backupinfo->sections as $key => $section) {
            $path = $this->task->get_basepath();
            $sectionfile = $path.'/'.$section->directory.'/section.xml';
            $sectel = new SimpleXMLElement(file_get_contents($sectionfile));
            $atts = $sectel->attributes();
            $id = (int) $atts['id'][0];
            $numberx = $sectel->xpath('//number');
            $sectionnumber = (int) $numberx[0];
            $sectnumsbyid[$id] = $sectionnumber;
        }

        $format = \format_visualsections::instance($courseid);

        $newsectionidsbynum = $DB->get_records_menu('course_sections',
            ['course' => $courseid], '', 'section, id'
        );

        foreach ($sectnumsbyid as $id => $num) {
            $formatoptions = $format->get_format_options($num);
            if (!empty($formatoptions['parentid'])) {
                $sectionid = $newsectionidsbynum[$num];
                $oldparentid = $formatoptions['parentid'];
                // Get parent section number for old parent section id.
                $sectnum = $sectnumsbyid[$oldparentid];
                // Get new section id.
                $newparentid = $newsectionidsbynum[$sectnum];
                // Update format with new parentid.
                $formatoptions['parentid'] = $newparentid;
                $formatoptions['id'] = $sectionid;
                $format->update_section_format_options($formatoptions);
            }
        }
    }
}
