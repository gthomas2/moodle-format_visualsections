<?php
namespace format_visualsections\service;

defined('MOODLE_INTERNAL') || die;

use format_visualsections\model\subsection;

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
                'name' => $data->name
            ];
            $success = $format->update_section_format_options($formatoptions);
        }
        return (object) [
            'success' => $success,
            'subsectionid' => $subsectionid,
            'html' => 'TODO'
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
}