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
use format_visualsections\model\topics_svg_circles;
use format_visualsections\model\topic;
use format_visualsections\model\subsection;
use format_visualsections\model\sectionsubsection;

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
     * Initialise code to navigate / manage subsections.
     * @throws dml_exception
     */
    protected function init_section_js(string $defaultsection) {
        global $PAGE, $COURSE;
        $capcourseupdate = has_capability('moodle/course:update', context_course::instance($COURSE->id));
        $PAGE->requires->js_call_amd('format_visualsections/subsections', 'init', [$COURSE->id, $capcourseupdate, $defaultsection]);
    }

    /**
     * Go though all section sub sections and get mods.
     * @param $parentsection
     * @return cm_info[]
     */
    protected function get_section_mods($parentsection) {
        global $COURSE;

        $format = course_get_format($COURSE);
        $modinfo = $parentsection->modinfo;
        $mods = [];
        $sectionhierarchy = $format->get_section_hierarchy();
        $subsections = $sectionhierarchy[$parentsection->id]->children ?? [];
        if (!empty($subsections)) {
            foreach ($subsections as $subsection) {
                if (!empty($modinfo->sections[$subsection->section])) {
                    foreach ($modinfo->sections[$subsection->section] as $modnumber) {
                        $mods[] = $modinfo->cms[$modnumber];
                    }
                }
            }
        }
        return $mods;
    }

    protected function section_completion_progress($parentsection) {
        global $COURSE, $USER;
        $mods = $this->get_section_mods($parentsection);
        $completion = new completion_info($COURSE);
        $countcomplete = 0;
        if (!empty($mods)) {
            foreach ($mods as $mod) {
                $activitycompletiondata = $completion->get_data($mod, true);
                $complete = $activitycompletiondata->completionstate === COMPLETION_COMPLETE;
                $countcomplete += $complete ? 1 : 0;
            }
        } else {
            return false; // Must have mods to be completable.
        }
        if ($countcomplete === 0) {
            return false;
        }
        return ($countcomplete / count($mods)) * 100;
    }

    protected function section_complete($parentsection) {
        return $this->section_completion_progress($parentsection) === 100;
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        global $PAGE, $COURSE, $CFG;

        static $sectioncirclesoutput = false; // Only output circle nav once.

        if ($sectioncirclesoutput) {
            return html_writer::start_tag('ul', array('class' => 'visualsections'));
        }

        $PAGE->requires->js_call_amd('format_visualsections/sectioncircle', 'applySegments', [$COURSE->id]);

        $data = get_config('format_visualsections', 'subsectiontypes');
        $typesarr = $this->sectionservice->config_to_types_array($data);

        $imageurls = [];
        foreach ($typesarr as $type) {
            $imageurls[] = new imageurl($type->code, $type->image);
        }

        $modinfo = get_fast_modinfo($COURSE);
        $sections = $modinfo->get_section_info_all();

        $format = course_get_format($COURSE);
        $sectionhierarchy = $format->get_section_hierarchy();

        $topics = [];
        $firstsection = null;
        $prevsectioncomplete = false;
        $lastunlockedsection = null;
        $prevsection = null;
        $capcourseupdate = has_capability('moodle/course:update', context_course::instance($COURSE->id));
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

            $prevsectioncomplete = !empty($prevsection) && $this->section_complete($prevsection);

            $firstsection = $firstsection ?? $section;
            $isfirstsection = $firstsection === $section;

            $progress = $this->section_completion_progress($section);

            $subsections = $sectionhierarchy[$section->id]->children ?? [];

            $subtopics = [];
            $ss = 0;
            foreach ($subsections as $subsection) {
                $link = null;
                if ($capcourseupdate) {
                    $link = $CFG->wwwroot.'/course/view.php?id='.$COURSE->id.'#subsection'.$subsection->id;
                }
                $subtopics[] = new subsection(
                    $section->id,
                    $subsection->typecode,
                    $subsection->size ?? 's',
                    $subsection->name,
                    $subsection->id,
                    $link
                );
                $ss ++;
                if ($ss >= 5) {
                    // Max five subtopics.
                    break;
                }
            }
            $cssclass = $isfirstsection ? 'active' : '';
            $isunlockedsection = $isfirstsection || ($section->available && $prevsectioncomplete);
            $lastunlockedsection = $isunlockedsection ? $section->section : $lastunlockedsection;
            $strokecolor = $isunlockedsection ? '#f00' : '#eee';

            $link = null;
            $tooltip = null;
            if ($isunlockedsection) {
                $link = new moodle_url('/course/view.php', ['id' => $COURSE->id], 'section-'.$section->section);
            } else {
                // TODO localise.
                $tooltip = get_string('sectionlocked', 'format_visualsections');
            }
            $title = get_section_name($COURSE, $section);
            $topics[] = new topic($section->id, $title, $section->section, $progress, json_encode($subtopics), $cssclass, $strokecolor, $link, $tooltip, !$isunlockedsection);
            $prevsection = $section;
        }

        $this->init_section_js('section-'.$lastunlockedsection);

        $topicgroups = array_chunk($topics, 3); // Three topics per group.


        $activeidx = 0;
        foreach ($topicgroups as $idx => $topics) {
            foreach ($topics as $topic) {
                if ($topic->number === $lastunlockedsection) {
                    $activeidx = $idx;
                }
            }
        }

        foreach ($topicgroups as $idx => $group) {
            $cssclass = $activeidx === $idx ? 'active' : '';
            $topicgroups[$idx] = (object) [
                'topics' => $group,
                'cssclass' => $cssclass,
                'index' => $idx
            ];
        }
        $data = new topics_svg_circles($imageurls, $topicgroups);

        $visualsections = $this->render_from_template('format_visualsections/topics_svg_circles', $data);

        $sectioncirclesoutput = true; // Only output circle nav once.

        $classcanupdate = $capcourseupdate ? 'capcourseupdate' : '';
        return $visualsections.html_writer::start_tag('ul', array('class' => 'visualsections '.$classcanupdate));
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
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $o.= html_writer::start_tag('li', array('id' => 'section-'.$section->section,
            'data-section-id' => $section->id,
            'class' => 'section main clearfix'.$sectionstyle, 'role'=>'region',
            'aria-label'=> get_section_name($course, $section)));

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag('span', get_section_name($course, $section), array('class' => 'hidden sectionname'));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));
        $o.= $this->output->heading($sectionname, 3, 'sectionname' . $classes);

        $o .= $this->section_availability($section);

        $o .= html_writer::start_tag('div', array('class' => 'summary'));
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o .= html_writer::end_tag('div');

        return $o;
    }

    /**
     * Multiple section page only!
     * @param stdClass $course
     * @param array $sections
     * @param array $mods
     * @param array $modnames
     * @param array $modnamesused
     * @param int $displaysection
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        return $this->print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
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

        $subsectiontypes = $this->sectionservice->config_to_types_array();

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
        $s = 0;
        foreach ($sections as $thissection) {
            $s ++;
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

                            $subsectioninfo = $modinfo->get_section_info($subsection->section);

                            $subsectiontitle = !empty($subsection->name) ? $subsection->name : $sectiontitle . '.' . $scount;

                            $headingid = $subsectionsid.'heading'.$subsection->id;
                            $subsectionid = $subsection->id;
                            $collapseid = 'div-section-'.$subsection->section;
                            $subsectionclass = 'typeclass-'.$subsection->typecode;
                            $cardbody = $this->courserenderer->course_section_cm_list($course, $subsection->section, null);
                            $cardbody .= $this->courserenderer->course_section_add_cm_control($course, $subsection->section, null);
                            $typecode = $subsection->typecode;
                            $type = $subsectiontypes[$typecode];
                            $imageurl = $type->image;

                            $sectionheader = $this->section_header($subsectioninfo, $course, false, 0);
                            $sectionfooter = $this->section_footer();
                            $cardbody = $sectionheader.$cardbody.$sectionfooter; // This is here purely to satisfy SECTIONLI in actions.js

                            $allowmoveup = $thissection->section > 1 || $scount > 1;
                            $allowmovedown = $thissection->section < $numsections || $scount < count($subsections);

                            $deleteurl = new moodle_url('/course/editsection.php',
                                    [
                                        'id' => $subsection->id,
                                        'sr' => '',
                                        'delete' => 1,
                                        'sesskey' => sesskey()
                                    ]
                                ).'';

                            $sectionsubsection = new sectionsubsection(
                                $subsectionid,
                                $subsectionsid,
                                $subsectionclass,
                                $headingid,
                                $collapseid,
                                $imageurl,
                                $subsectiontitle,
                                $cardbody,
                                $allowmoveup,
                                $allowmovedown,
                                $deleteurl,
                                $PAGE->user_is_editing()
                            );
                            if (count($subsections) === 1 ) {
                                if (!has_capability('moodle/course:update', $context)) {
                                    $sectionsubsection->show = true;
                                }
                            }
                            $subsectionhtml = $this->render_from_template('format_visualsections/sectionsubsection', $sectionsubsection);

                            echo $subsectionhtml;
                        }
                    }
                    echo '</div>';

                    echo $this->render_add_subsections($thissection->id);
                }
                echo $this->section_footer();
            }
        }

    }
}
