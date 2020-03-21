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
 * Renderer for outputting the visualsections course format.
 *
 * @package format_visualsections
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();

use format_visualsections\service\section;
use format_visualsections\model\imageurl;
use format_visualsections\adminsetting\subsectiontypes;
use format_visualsections\model\topics_svg_circles;
use format_visualsections\model\topic;
use format_visualsections\model\subsection;

require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for visualsections format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_visualsections_renderer extends format_section_renderer_base {

    /**
     * @var \format_visualsections\service\section;
     */
    private $sectionservice;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        $this->sectionservice = section::instance();

        // Since format_visualsections_renderer::section_edit_control_items() only displays the 'Highlight' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Initialise code to manage subsections.
     * @throws dml_exception
     */
    protected function init_subsection_types() {
        global $PAGE, $COURSE;
        $PAGE->requires->js_call_amd('format_visualsections/subsections', 'init', [$COURSE->id]);
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        global $PAGE, $COURSE;

        $PAGE->requires->js_call_amd('format_visualsections/sectioncircle', 'applySegments');
        $this->init_subsection_types();

        $data = get_config('format_visualsections', 'subsectiontypes');
        $typesarr = subsectiontypes::config_to_types_array($data);

        $imageurls = [];
        foreach ($typesarr as $type) {
            $imageurls[] = new imageurl($type->code, $type->image);
        }

        $modinfo = get_fast_modinfo($COURSE);
        $sections = $modinfo->get_section_info_all();

        $format = course_get_format($COURSE);
        $sectionhierarchy = $format->get_section_hierarchy();

        $topics = [];
        foreach ($sections as $section) {
            if ($section->section === 0) {
                continue;
            }
            if (!$section->uservisible) {
                continue;
            }
            if (!empty($sectionhierarchy[$section->parentid])) {
                // Skip sub sections.
                continue;
            }
            $progress = 50; // TODO.

            $subsections = $sectionhierarchy[$section->id]->children ?? [];

            $subtopics = [];
            $ss = 0;
            foreach ($subsections as $subsection) {
                $subtopics[] = new subsection($section->id, $subsection->typecode, $subsection->name, $subsection->id);
                $ss ++;
                if ($ss >= 5) {
                    // Max five subtopics.
                    break;
                }
            }
            $topics[] = new topic($progress, json_encode($subtopics));
        }
        $data = new topics_svg_circles($imageurls, $topics);

        $visualsections = $this->render_from_template('format_visualsections/topics_svg_circles', $data);


        return $visualsections.html_writer::start_tag('ul', array('class' => 'visualsections'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                                               'name' => $highlightoff,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                                               'name' => $highlight,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Display section and all its activities and subsections (called recursively)
     *
     * @param int|stdClass $course
     * @param int|section_info $section
     * @param int $sr section to return to (for building links)
     * @param int $level nested level on the page (in case of 0 also displays additional start/end html code)
     */
    public function display_section($course, $section, $sr, $level = 0) {
        global $PAGE;

        $section = <<<SECTION
        <section>
        TEST
        </section>
SECTION;

        echo $section;
    }

    /**
     * Renders subsections for a specific sectionid.
     * Note - we only have one level of subsections - nested not allowed.
     * @param int $sectionid
     * @return string
     */
    public function render_add_subsections(int $sectionid): string {
        global $PAGE;

        $context = (object) [
            'editmode' => $PAGE->user_is_editing(),
            'sectionid' => $sectionid
        ];
        $subsections = $this->render_from_template('format_visualsections/add_subsections', $context);
        return $subsections;
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        $format = course_get_format($course);

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = $format->get_last_section_number();
        $sectionparentids = $format->get_sections_with_parentid();

        $sectionhierarchy = $format->get_section_hierarchy();
        $sections = $modinfo->get_section_info_all();
        foreach ($sections as $thissection) {
            if (!empty($sectionparentids[$thissection->id]->parentid)) {
                // Skip sub sections.
                continue;
            }
            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    // For now section_add_menus is just here to satisfy JS for moving sections.
                    echo '<div class="section_add_menus"></div>';

                    $subsections = $sectionhierarchy[$thissection->id]->children ?? [];
                    $subsectionsid = 'subsections'.$thissection->id;
                    echo '<div class="subsections accordion>" id="'.$subsectionsid.'">';
                    $sectiontitle = get_section_name($course, $thissection);
                    if (!empty($subsections)) {
                        $scount = 0;
                        foreach ($subsections as $subsection) {
                            $scount ++;

                            $subsectiontitle = !empty($subsection->name) ? $subsection->name : $sectiontitle . '.' . $scount;

                            $headingid = $subsectionsid.'heading'.$subsection->id;
                            $subsectionid = 'subsection'.$subsection->id;
                            $collapseid = 'section-'.$subsection->section;
                            $subsectionclass = 'typeclass-'.$subsection->typecode;
                            $cardbody = $this->courserenderer->course_section_cm_list($course, $subsection->section, null);
                            $cardbody .= $this->courserenderer->course_section_add_cm_control($course, $subsection->section, null);
                            // TODO - template.
                            $subsectionhtml = <<<HTML
  <div id ="$subsectionid" class="card subsection $subsectionclass">
    <div class="card-header" id="$headingid">
      <h2 class="mb-0">
        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#$collapseid" aria-expanded="true" aria-controls="$collapseid">
          $subsectiontitle
        </button>
      </h2>
    </div>

    <div id="$collapseid" class="section collapse" aria-labelledby="$headingid" data-parent="#$subsectionsid">
      <div class="card-body">
        $cardbody
      </div>
    </div>
  </div>
HTML;

                            echo $subsectionhtml;
                        }
                    }
                    echo '</div>';

                    echo $this->render_add_subsections($thissection->id);
                }
                echo $this->section_footer();
            }
        }

        /*foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if (empty($sectionswithparents[$thissection->id])) {
                continue;
            }
            $fullinfo = $sectionswithparents[$thissection->id];
            if ($fullinfo->parentid) {
                continue;
            }
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                (!$thissection->visible && !$course->hiddensections);
            if (!$showsection) {
                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    // For now section_add_menus is just here to satisfy JS for moving sections.
                    echo '<div class="section_add_menus"></div>';

                    echo $this->render_subsections($thissection->id);
                    //echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    //echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }*/

    }
}
