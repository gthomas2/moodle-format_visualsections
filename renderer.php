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
 * @copyright 2020 Citricity Ltd www.citri.city
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_visualsections_renderer extends format_section_renderer_base {

    /**
     * @var \format_visualsections\service\section;
     */
    private $sectionservice;

    /**
     * @var bool
     */
    private $capedit;

    /**
     * @var \format_visualsections
     */
    private $format;

    /**
     * @var course_modinfo
     */
    private $modinfo;

    /**
     * @var stdClass
     */
    private $course;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        global $COURSE;

        $this->course = $COURSE;
        $this->capedit = has_capability('moodle/course:setcurrentsection', context_course::instance($COURSE->id));
        $this->format = course_get_format($COURSE);
        $this->modinfo = get_fast_modinfo($COURSE);

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
    protected function init_section_js(string $defaultsection, int $courseid) {
        global $PAGE;
        $PAGE->requires->js_call_amd('format_visualsections/subsections', 'init',
            [$courseid, $this->capedit, $defaultsection]);
    }

    /**
     * Go though all section sub sections and get mods.
     * @param $parentsection
     * @return cm_info[]
     */
    protected function get_parent_section_mods($parentsection) {
        $format = $this->format;
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

    /**
     * Taken from snap shared.php section_activity_summary.
     * @param section_info $section
     * @return float -1 means there are no mods that require completion.
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function section_completion_progress(section_info $section): float {
        global $COURSE, $CFG;
        require_once($CFG->libdir.'/completionlib.php');

        $completioninfo = new completion_info($COURSE);
        $mods = section::instance()->get_section_mods($section);

        $total = 0;
        $complete = 0;
        $cancomplete = isloggedin() && !isguestuser();
        foreach ($mods as $thismod) {
            if ($thismod->uservisible) {
                if ($cancomplete && $completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                    $total++;
                    $completiondata = $completioninfo->get_data($thismod, true);
                    if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $complete++;
                    }
                }
            }
        }
        if ($total === 0) {
            return -1;
        }

        return ($complete / $total) * 100;
    }

    /**
     * Taken from snap shared.php section_activity_summary.
     * @param $parentsection
     * @return float|int|string
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function parent_section_completion_progress($parentsection) {
        global $COURSE, $CFG;
        require_once($CFG->libdir.'/completionlib.php');

        $completioninfo = new completion_info($COURSE);

        $mods = $this->get_parent_section_mods($parentsection);

        $total = 0;
        $complete = 0;
        $cancomplete = isloggedin() && !isguestuser();
        foreach ($mods as $thismod) {
            if ($thismod->uservisible) {
                if ($cancomplete && $completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                    $total++;
                    $completiondata = $completioninfo->get_data($thismod, true);
                    if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $complete++;
                    }
                }
            }
        }
        if ($total === 0) {
            return 0;
        }

        return ($complete / $total) * 100;
    }

    protected function parent_section_complete($parentsection) {
        return $this->parent_section_completion_progress($parentsection) >= 100;
    }

    /**
     * Get the next section after $sectionnum
     * @param int $sectionnum
     * @return null|object
     * @throws moodle_exception
     */
    private function next_section(int $sectionnum): ?object {
        $modinfo = $this->modinfo;
        $sections = $modinfo->get_section_info_all();
        $format = $this->format;
        $sectionhierarchy = $format->get_section_hierarchy();
        $foundsection = null;

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
            if (!$this->capedit && !$this->root_section_has_completion($section)) {
                // Skip sections that don't have any activities requiring completion.
                continue;
            }

            if ($foundsection) {
                return $section; // This should be the section following $sectionnum;
            }

            if ($section->section === $sectionnum) {
                $foundsection = $section;
            }
        }

        return null;
    }

    /**
     * Does a root section have any sub sections requiring completion?
     * @param section_info $rootsection
     * @return bool
     */
    public function root_section_has_completion(section_info $rootsection): bool {
        $format = $this->format;
        $modinfo = $this->modinfo;
        $sectionhierarchy = $format->get_section_hierarchy();
        $subsections = $sectionhierarchy[$rootsection->id]->children ?? [];
        if (empty($subsections)) {
            return false;
        } else {
            $hascompletion = false;
            foreach ($subsections as $subsection) {
                $subsectioninfo = $modinfo->get_section_info($subsection->section);

                $completion = $this->section_completion_progress($subsectioninfo);
                if ((int) $completion !== -1) {
                    $hascompletion = true;
                    break;
                }
            }
            if ($hascompletion) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get progression type for specific course id.
     * Use static caching for speed.
     * @param int $courseid
     * @return string
     */
    private function get_progression_type(int $courseid): string {
        static $progressiontypes = [];
        if (!empty($progressiontypes[$courseid])) {
            return $progressiontypes[$courseid];
        }

        $format = $this->format;
        $opts = (object) $format->get_format_options();
        $progtype = $opts->progressiontype ?? 'linear';
        $progressiontypes[$courseid] = $progtype;
        return $progtype;
    }

    private function section_info(int $courseid, int $sectionnum) {
        $modinfo = $this->modinfo;
        $sections = $modinfo->get_section_info_all();
        $format = $this->format;
        $sectionhierarchy = $format->get_section_hierarchy();
        $prevsection = null;
        $unlocked = false;

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
            if (!$this->capedit && !$this->root_section_has_completion($section)) {
                // Skip sections that don't have any activities requiring completion.
                continue;
            }

            $prevsectioncomplete = !empty($prevsection) && $this->parent_section_complete($prevsection);

            if ($section->section === $sectionnum) {
                if ($this->capedit || $prevsectioncomplete) {
                    $unlocked = true;
                }
                break; // Break regardless of whether unlocked or not - we have our section.
            }
            $prevsection = $section;
        }

        if ($this->get_progression_type($courseid) === 'random') {
            $unlocked = true;
        }
        return (object) ['prevsection' => $prevsection, 'unlocked' => $unlocked];
    }

    /**
     * Is a specific section (by section number) unlocked?
     * @param int $courseid
     * @param int $section - section number
     * @return bool
     * @throws moodle_exception
     */
    private function section_unlocked(int $courseid, int $section) {
        $info = $this->section_info($courseid, $section);
        return $info->unlocked;
    }

    /**
     * Get the previous section for a section (by section number).
     * @param int $section - section number
     * @return bool
     * @throws moodle_exception
     */
    private function previous_section(int $section) {
        $info = $this->section_info($this->course, $section);
        return $info->prevsection;
    }

    public function render_carousel(int $courseid, ?bool $initjs = false) {
        global $PAGE, $CFG;

        $data = get_config('format_visualsections', 'subsectiontypes');
        $typesarr = $this->sectionservice->config_to_types_array($data);

        $imageurls = [];
        foreach ($typesarr as $type) {
            $imageurls[] = new imageurl($type->code, $type->image);
        }

        $modinfo = $this->modinfo;
        $sections = $modinfo->get_section_info_all();

        $format = $this->format;
        $sectionhierarchy = $format->get_section_hierarchy();

        $topics = [];
        $firstsection = null;
        $prevsectioncomplete = false;
        $lastunlockedsection = null;
        $prevsection = null;
        $capsegmentnav = has_capability('format/visualsections:segmentnavigation', context_course::instance($courseid));
        $blockaccessforward = false; // If true blocks access to sections forward from this point.
        $vscount = 0; // Visual section count;
        $randomprog = $this->get_progression_type($courseid) === 'random';

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
            if (!$this->capedit && !$this->root_section_has_completion($section)) {
                // Skip sections that don't have any activities requiring completion.
                continue;
            }
            $vscount++;

            $prevsectioncomplete = !empty($prevsection) && $this->parent_section_complete($prevsection);
            if (!$randomprog && !$prevsectioncomplete && $vscount > 1) {
                $blockaccessforward = true;
            }

            $firstsection = $firstsection ?? $section;
            $isfirstsection = $firstsection === $section;

            $progress = $this->parent_section_completion_progress($section);

            $subsections = $sectionhierarchy[$section->id]->children ?? [];

            $subtopics = [];
            $ss = 0;

            $isunlockedsection = $randomprog || $this->capedit || $isfirstsection || ($section->available && !$blockaccessforward);

            foreach ($subsections as $subsection) {
                $link = null;
                if ($capsegmentnav && (!$blockaccessforward || $this->capedit)) {
                    $link = $CFG->wwwroot.'/course/view.php?id='.$courseid.'#subsect'.$subsection->id;
                }
                $subsectionsection = $modinfo->get_section_info($subsection->section);
                $subsectionprogress = $this->section_completion_progress($subsectionsection);
                if ((int) $subsectionprogress === -1) {
                    $subsectionprogress = 0;
                    if (!$this->capedit) {
                        // Students will not be allowed to see sub sections that do not contain activities with
                        // completion enabled.
                        continue;
                    }
                }

                $subtopics[] = new subsection(
                    $section->id,
                    $subsection->typecode,
                    $subsection->size ?? 's',
                    $subsectionprogress,
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
            $lastunlockedsection = $isunlockedsection ? $section->section : $lastunlockedsection;
            $strokecolor = $isunlockedsection ? '#f00' : '#eee';

            $link = null;
            $tooltip = null;
            if ($isunlockedsection) {
                $link = new moodle_url('/course/view.php', ['id' => $courseid], 'section-'.$section->section);
            } else {
                $tooltip = get_string('sectionlocked', 'format_visualsections');
            }
            $nextsection = $this->next_section($section->section);
            $nextlocked = false;
            if ($nextsection) {
                $nextlocked = !$this->section_unlocked($courseid, $nextsection->section);
            }
            $title = get_section_name($courseid, $section);
            $maxlen = 40;
            if (strlen($title) > $maxlen) {
                $title = substr($title, 0, $maxlen).'...';
            }
            $arialabel = empty($tooltip) ? get_string('navigatetosection', 'format_visualsections', $title) : null;
            $topics[] = new topic(
                $section->id,
                $title,
                $section->section,
                $progress,
                json_encode($subtopics),
                $cssclass,
                $strokecolor,
                $link,
                $tooltip,
                $arialabel,
                !$isunlockedsection,
                $nextlocked,
                $lastitem = !$nextsection
            );
            $prevsection = $section;
        }

        if ($initjs) {
            $this->init_section_js('section-'.$lastunlockedsection, $courseid);
        }

        $topicgroups = array_chunk($topics, 3); // Three topics per group.

        $activeidx = 0;
        if (!$this->capedit) {
            foreach ($topicgroups as $idx => $topics) {
                foreach ($topics as $topic) {
                    if ($topic->number === $lastunlockedsection) {
                        $activeidx = $idx;
                    }
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
        $data = new topics_svg_circles($imageurls, $topicgroups, count($topicgroups) > 1);

        return $this->render_from_template('format_visualsections/topics_svg_circles', $data);
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        global $COURSE;

        static $carouseloutput = false; // Only output circle nav once.

        $extraclass = $this->capedit ? ' capcourseupdate' : '';

        if ($carouseloutput) {
            return html_writer::start_tag('ul', array('class' => 'visualsections'.$extraclass));
        }

        $carouseloutput = true; // Only output circle nav once.

        $classcanupdate = $this->capedit ? 'capcourseupdate' : '';
        $carousel = '<div id="section-carousel-content">'.$this->render_carousel($COURSE->id, true).'</div>';
        return $carousel.html_writer::start_tag('ul', array('class' => 'visualsections '.$classcanupdate));
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
        return $this->render($this->format->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render($this->format->inplace_editable_render_section_name($section, false));
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
     * @param section_info $section
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $OUTPUT;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if ($this->format->is_section_current($section)) {
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

        if ($this->capedit && $section->section !== 0) {
            if (empty($section->parentid)) {
                // Top level section.
                // Show warning if section doesn't contain any sub sections containing activities that require completion.
                $showwarning = !$this->root_section_has_completion($section);
                if ($showwarning) {
                    $o .= $OUTPUT->notification(
                        get_string('warnnosectioncompletion', 'format_visualsections'), 'warning');
                }
            } else {
                // Sub section.
                // Show warning if subsection doesn't contain activities requiring completion.
                $completion = $this->section_completion_progress($section);
                if ((int) $completion === -1) {
                    $o .= $OUTPUT->notification(
                        get_string('warnnosubsectioncompletion', 'format_visualsections'), 'warning');
                }
            }
        }

        $o .= html_writer::end_tag('div'); // Close summary.

        return $o;
    }

    public function render_format_footer(int $course, ?int $section = null) {
        $nextunlocked = false;
        $nextsection = null;
        $tooltip = null;
        $prevsection = null;
        if ($section) {
            $nextsection = $this->next_section($section);
            $prevsection = $this->previous_section($section);
            if ($nextsection) {
                $nextunlocked = $this->section_unlocked($course, $nextsection->section);
                $tooltip = $nextunlocked ? null : get_string('nextsectionlocked', 'format_visualsections');
            }
        }
        return $this->render_from_template('format_visualsections/format_footer', [
            'navprev' => $prevsection != null,
            'prevsection' => $prevsection ? $prevsection->section : null,
            'navnext' => $nextunlocked,
            'nextsection' => $nextsection ? $nextsection->section : null,
            'tooltip' => $tooltip,
            'hasnext' => $nextsection != null
        ]);
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
        global $PAGE, $OUTPUT;

        $format = $this->format;

        $modinfo = $this->modinfo;
        $course = $format->get_course();

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

                if ($this->capedit) {
                    $cmlist = $this->courserenderer->course_section_cm_list($course, $thissection, null);
                    if (strpos($cmlist, '<li') !== false) {
                        echo '<div class="toplevelactivities">';
                        echo $OUTPUT->notification(get_string('warnmodulesinsection', 'format_visualsections'), 'warning');
                        echo $cmlist;
                        echo '</div>';
                    } else {
                        echo $cmlist;
                    }
                }

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
                            $type = $subsectiontypes[$typecode] ?? null;
                            $imageurl = $type ? $type->image : '';

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

                            $sizeimageurl = null;
                            $size = $subsection->size ?? 's';
                            $sizeicon = $OUTPUT->pix_icon('size-'.$size,
                                get_string('size:'.$size, 'format_visualsections'),
                                'format_visualsections',
                                ['class' => 'size-icon']);

                            $sectionsubsection = new sectionsubsection(
                                $subsectionid,
                                $subsectionsid,
                                $subsectionclass,
                                $headingid,
                                $collapseid,
                                $imageurl,
                                $sizeicon,
                                $subsectiontitle,
                                $cardbody,
                                $allowmoveup,
                                $allowmovedown,
                                $deleteurl,
                                $PAGE->user_is_editing()
                            );
                            if (count($subsections) === 1 ) {
                                if (!has_capability('moodle/grade:viewall', $context)) {
                                    $sectionsubsection->show = true;
                                }
                            }
                            $subsectionhtml = $this->render_from_template('format_visualsections/sectionsubsection',
                                $sectionsubsection);

                            echo $subsectionhtml;
                        }
                    }
                    echo '</div>';

                    echo $this->render_add_subsections($thissection->id);
                }
                echo $this->section_footer();
            }
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            echo $this->change_number_sections($course, 0);
        }

        echo $this->render_format_footer($course->id);

    }
}
