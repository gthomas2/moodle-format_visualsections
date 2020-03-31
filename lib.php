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

require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the Visual Sections course format
 *
 * @package    format_visualsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_visualsections extends format_base {

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * SQL for getting sections with parents.
     * @return string
     */
    private function sql_section_parents(): string {
        $sql = "FROM {course_sections} cs
           LEFT JOIN {course_format_options} fo ON fo.sectionid = cs.id AND fo.name='parentid'
           LEFT JOIN {course_format_options} fo2 ON fo2.sectionid = cs.id AND fo2.name='typecode'
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
     * @return array|null
     * @throws dml_exception
     */
    public function get_sections_with_parentid(): ?array {
        global $DB;

        // Static caching for performance.
        static $rs = null;

        if ($rs !== null) {
            return $rs;
        }

        $parentsql = $this->sql_section_parents();
        $sql = "SELECT cs.*, fo.value AS parentid, fo2.value as typecode
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
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the visualsections course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_visualsections');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // if section is specified in course/view.php, make sure it is expanded in navigation
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // check if there are callbacks to extend course navigation
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
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

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Visual Sections format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                )
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = array(
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'visualsections', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
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
        return $this->update_format_options($data);
    }

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_visualsections');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_visualsections', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
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

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
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

    if ($context->contextlevel != CONTEXT_SYSTEM) {
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