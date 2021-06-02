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
 * This file contains main class for the course format Topic
 *
 * @since     Moodle 2.0
 * @package   format_visualsections
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use format_visualsections\form\subsection;
use format_visualsections\model\subsection as subsectionmodel;
use format_visualsections\service\section;

require_once($CFG->dirroot.'/course/format/lib.php');
require_once($CFG->dirroot.'/course/format/topics/lib.php');

/**
 * Main class for the Visual Sections course format
 *
 * @package    format_visualsections
 * @copyright  2020 Guy Thomas <dev@citri.city>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_visualsections extends format_topics {

    /**
     * SQL for getting sections with parents.
     * @return string
     */
    private function sql_section_parents(): string {
        $sql = "FROM {course_sections} cs
           LEFT JOIN {course_format_options} fo ON fo.sectionid = cs.id AND fo.name='parentid'
           LEFT JOIN {course_format_options} fo2 ON fo2.sectionid = cs.id AND fo2.name='typecode'
           LEFT JOIN {course_format_options} fo3 ON fo3.sectionid = cs.id AND fo3.name='size'
               WHERE cs.course = ?";
        return $sql;
    }

    /**
     * Get last section number.
     * @return int
     * @throws dml_exception
     */
    public function get_last_section_number(): int {
        global $DB;

        // Count non-parent rows.
        $parentsql = $this->sql_section_parents();
        $sql = "SELECT count(cs.id) 
                $parentsql AND fo.id IS NULL";

        return $DB->count_records_sql($sql, [$this->courseid]);
    }

    /**
     * Get sections including the parentid field.
     *
     * @param bool $nocache;
     * @return array|null
     * @throws dml_exception
     */
    public function get_sections_with_parentid($nocache = false): ?array {
        global $DB;

        // Static caching for performance.
        static $rs = null;

        if ($rs !== null && !PHPUNIT_TEST && !CLI_SCRIPT && !$nocache) {
            return $rs;
        }

        $parentsql = $this->sql_section_parents();
        $sql = "SELECT cs.*, fo.value AS parentid, fo2.value as typecode, fo3.value as size, cs.section
                $parentsql
                ORDER BY cs.section";

        $rs = $DB->get_records_sql($sql, [$this->courseid]);
        return $rs ? $rs : null;
    }

    /**
     * Note, that hierarchy is only one sub section deep.
     * @return array
     */
    public function get_section_hierarchy(): array {
        static $rootsections = null;
        
        if ($rootsections !== null) {
            return $rootsections;
        }
        
        $subsections = [];
        $rootsections = [];

        $sections = $this->get_sections_with_parentid();
        foreach ($sections as $section) {
            if (!empty($section->parentid)) {
                if (empty($subsections[$section->parentid])) {
                    $subsections[$section->parentid] = [];
                }
                $subsections[$section->parentid][$section->id] = $section;
            } else {
                $rootsections[$section->id] = $section;
            }
        }
        foreach ($rootsections as $rootsection) {
            if (isset($subsections[$rootsection->id])) {
                $rootsection->children = $subsections[$rootsection->id];
            }
        }
        return $rootsections;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        parent::extend_course_navigation($navigation, $node);

        // We want to remove sub sections.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_section_info_all();
        $hierarchy = $this->get_section_hierarchy();
        foreach ($sections as $section) {
            if (!empty($hierarchy[$section->parentid])) {
                // Remove subsection from navigation.
                $node->get($section->id, navigation_node::TYPE_SECTION)->remove();
            }
        }
    }

    public function section_format_options($foreditform = false) {
        $options = [
            'parentid' => [
                'default' => 0,
                'type' => PARAM_INT,
                'label' => get_string('label:parentid', 'format_visualsections'),
            ],
            'typecode' => [
                'default' => '',
                'type' => PARAM_ALPHANUMEXT,
                'label' => get_string('label:typecode', 'format_visualsections')
            ],
            'size' => [
                'default' => 's',
                'type' => PARAM_ALPHA,
                'label' => get_string('label:size', 'format_visualsections')
            ]
        ];
        return $options;
    }

    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;

        // Save header image.
        $context = context_course::instance($this->courseid);
        file_save_draft_area_files($data['headerimage'], $context->id, 'format_visualsections',
            'headerimage', 0, ['subdirs' => 0, 'maxfiles' => 1]);

        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }
        return parent::update_course_format_options($data, $oldcourse);
    }

    public function course_format_options($foreditform = false) {
        $fileopts = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.jpg', '.gif', '.png'],
            'itemid' => 0
        ];
        $courseformatoptions = parent::course_format_options($foreditform);
        $courseformatoptionsedit = [
            'progressiontype' => [
                'default' => 'linear',
                'label' => new lang_string('progressiontype', 'format_visualsections'),
                'element_type' => 'select',
                'element_attributes' => [
                    [
                        'linear' => new lang_string('progressionlinear', 'format_visualsections'),
                        'random' => new lang_string('progressionrandom', 'format_visualsections')
                    ]
                ],
                'help' => 'progressiontype',
                'help_component' => 'format_visualsections',
            ],
            'headerimage' => [
                'label' => new lang_string('headerimage', 'format_visualsections'),
                'element_type' => 'filepicker',
                'element_attributes' => [null,
                    $fileopts
                ],
                'type' => PARAM_CLEANFILE
            ]
        ];

        return array_merge($courseformatoptions, $courseformatoptionsedit);
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'visualsections' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_visualsections');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_visualsections_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'visualsections'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Serve the edit form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function format_visualsections_output_fragment_subsection_form($args) {
    global $PAGE, $DB;

    $output = $PAGE->get_renderer('core', '', RENDERER_TARGET_GENERAL);

    $data = null;
    $ajaxdata = null;
    if (!empty($args['formdata'])) {
        $data = [];
        parse_str($args['formdata'], $data);
        if ($data) {
            $ajaxdata = $data;
        }
        if (empty($ajaxdata['parentid']) && !empty($ajaxdata['id'])) {
            $subsection = $DB->get_record('course_sections', ['id' => $ajaxdata['id']]);
            $format = \format_visualsections::instance($ajaxdata['course']);
            $options = $format->get_format_options($subsection);
            $ajaxdata = (array) $subsection + $options;
        }
        $data = $ajaxdata;
    }

    $actionurl = '#';
    // Pass the source type as custom data so it can by used to detetmine the type of edit.
    $customdata = null;
    $form = new subsection($actionurl, $customdata,
        'post', '', null, true, $ajaxdata);
    $form->validate_defined_fields(true);
    $form->set_data($data);

    $msg = '';
    if (!empty($ajaxdata)) {
        if ($form->is_validated()) {
            // Add or update a subsection.
            $upsertresult = section::instance()->upsert_subsection(subsectionmodel::from_data($ajaxdata));
            $id = $upsertresult->subsectionid;
            if ($id) {
                $data['id'] = $id;
                // We need to recreate the form so that we can set the id field.
                $form = new subsection($actionurl, $customdata,
                    'post', '', null, true, $data);
                $form->set_data($data);
                $msg = $output->notification(get_string('subsectioncreated', 'mod_tlevent'), 'notifysuccess');
            } else {
                $msg = $output->notification(get_string('subsectioncreatefailed', 'mod_tlevent'), 'notifyproblem');
            }
        }
    }
    return $form->render().$msg;
}

function format_visualsections_output_fragment_filepicker($args) {
    global $PAGE, $OUTPUT;

    $fargs = new \stdClass();
    $fargs->accepted_types = ['.png', '.jpg', '.gif', '.webp', '.svg'];
    $fargs->itemid = file_get_unused_draft_itemid();
    $fargs->maxbytes = 0;
    $fargs->context = \context_system::instance();
    $fargs->buttonname = 'choose';

    $fp = new \file_picker($fargs);
    $options = $fp->options;
    $options->context = $PAGE->context;
    $module = [
        'name' => 'form_filepicker',
        'fullpath' => '/lib/form/filepicker.js',
        'requires' => [
            'core_filepicker',
            'node',
            'node-event-simulate',
            'core_dndupload'
        ]
    ];
    $PAGE->requires->js_init_call('M.form_filepicker.init', array($fp->options), true, $module);
    return $OUTPUT->render($fp);
}

function format_visualsections_output_fragment_carousel(array $args) {
    global $PAGE;
    $output = $PAGE->get_renderer('format_visualsections');

    return $output->render_carousel($args['courseid']);
}

function format_visualsections_output_fragment_footer(array $args) {
    global $PAGE;
    $output = $PAGE->get_renderer('format_visualsections');

    return $output->render_format_footer($args['courseid'], $args['section']);
}

/**
 * Server format visual sections pluginfile.
 * @param $course
 * @param $cm
 * @param context $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @return bool
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function format_visualsections_pluginfile($course,
                           $cm,
                           context $context,
                           $filearea,
                           $args,
                           $forcedownload,
                           array $options=array()) {

    if ($context->contextlevel != CONTEXT_COURSE && $context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login($course, false, $cm);

    $itemid = (int)array_shift($args);
    if ($itemid != 0) {
        return false;
    }

    $args = array_map(function($arg) {
        return urldecode($arg);
    }, $args);

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/format_visualsections/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}