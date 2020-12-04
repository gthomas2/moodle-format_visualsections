<?php
namespace format_visualsections\service;

defined('MOODLE_INTERNAL') || die;

use format_visualsections\model\subsection;
use format_visualsections\model\subsectiontype;
use section_info;
use tool_httpsreplace\form;

require_once __DIR__.'/../../../../../course/lib.php';
require_once __DIR__.'/../../lib.php';

class section extends base_service {

    /**
     * Instance method purely here for IDE completion.
     * @return section
     */
    public static function instance(): base_service {
        return parent::instance();
    }

    /**
     * Add or update a sub section.
     * @param subsection $data
     * @return stdClass
     */
    public function upsert_subsection(subsection $data): \stdClass {
        global $DB;

        $parentsection = $DB->get_record('course_sections', ['id' => $data->parentid]);

        if (empty($data->id)) {
            $subsection = course_create_section($parentsection->course);
            $subsectionid = $subsection->id;
            $success = !empty($subsection);
        } else {
            $subsectionid = $data->id;
            $success = true;
        }

        if ($success) {
            $subsectiondata = (object) [
                'id' => $subsectionid,
                'name' => $data->name
            ];
            $success = $DB->update_record('course_sections', $subsectiondata);
        }


        if ($success) {
            $format = \format_visualsections::instance($parentsection->course);
            $formatoptions = [
                'id' => $subsectionid,
                'parentid' => $data->parentid,
                'typecode' => $data->typecode,
                'name' => $data->name,
                'size' => $data->size
            ];
            // Note, we can't test for success here because it will return false simply
            // if the values are the same.
            $format->update_section_format_options($formatoptions);
        }
        return (object) [
            'success' => $success,
            'subsectionid' => $subsectionid
        ];
    }

    public function subtopictype_addimage($draftitemid, $filename) {
        global $USER;
        $contextid = \context_system::instance()->id;
        $component = 'format_visualsections';
        $filearea = 'subtopictype';

        $usercontextid = \context_user::instance($USER->id)->id;

        $itemid = 0;

        $fs = new \file_storage();
        $newfile = (object) [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => 0,
            'pathname' => '/',
            'filename' => $filename
        ];
        $existingfile = $fs->get_file($contextid, $component, $filearea, $itemid, '/', $filename);
        if ($existingfile) {
            $existingfile->delete();
        }
        $file = $fs->get_file($usercontextid, 'user', 'draft', $draftitemid, '/', $filename);
        $fs->create_file_from_storedfile($newfile, $file);

        $fileurl = \moodle_url::make_pluginfile_url($contextid, $component, $filearea, 0, '/', $filename).'';

        return (object) [
            'success' => true,
            'imagefile' => $fileurl
        ];
    }

    /**
     * Take config data string and convert it to types array.
     * @param null|string $data
     * @return subsectiontype[]
     */
    public function config_to_types_array(?string $data = null): array {
        if ($data === null) {
            $data = get_config('format_visualsections', 'subsectiontypes');
        }
        $types = [];
        $items = explode("\n", $data);
        $i = 0;
        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            $tmparr = explode('|', $item);
            $img = $tmparr[2] ?? null;
            $img = trim($img);
            $img = empty($img) ? null : $img;
            $types[$tmparr[0]] = subsectiontype::from_data([
                'code' => $tmparr[0],
                'name' => $tmparr[1],
                'image' => $img,
                'pos' => $i
            ]);
            $i++;
        }
        return $types;
    }

    public function subtopic_move(int $parentsectionid, int $srcsectionid, ?int $targetsectionid): bool {
        global $DB;

        $parentsection = $DB->get_record('course_sections', ['id' => $parentsectionid]);
        if (!$parentsection) {
            throw new \coding_exception('Invalid parent section id '.$parentsectionid);
        }

        $srcsection = $DB->get_record('course_sections', ['id' => $srcsectionid]);
        if (!$srcsection) {
            throw new \coding_exception('Invalid src section id '.$srcsectionid);
        }

        $format = \format_visualsections::instance($parentsection->course);
        $formatoptions = $format->get_format_options($srcsection);
        $formatoptions['parentid'] = $parentsectionid;
        $formatoptions['id'] = $srcsectionid;


        // Note, we can't test for success here because it will return false simply
        // if the values are the same.
        $format->update_section_format_options($formatoptions);

        if (empty($targetsectionid)) {
            $targetsectionid = $parentsectionid;
        }
        $targetsection = $DB->get_record('course_sections', ['id' => $targetsectionid]);

        $course = get_course($parentsection->course);

        return move_section_to($course, $srcsection->section, $targetsection->section);
    }

    /**
     * Get mods for a specific section.
     * @param \section_info $section
     * @return array
     */
    public function get_section_mods(section_info $section): array {
        $modinfo = $section->modinfo;
        $mods = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mods[] = $modinfo->cms[$modnumber];
            }
        }
        return $mods;
    }
}