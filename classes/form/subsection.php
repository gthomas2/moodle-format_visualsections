<?php

namespace format_visualsections\form;

defined('MOODLE_INTERNAL') || die();

use moodleform;

require_once($CFG->libdir.'/formslib.php');

class subsection extends moodleform {

    /**
     * Get sub section types from config.
     * @return array
     * @throws \dml_exception
     */
    private function subsectiontypes(): array {
        $typesstr = get_config('format_visualsections', 'subsectiontypes');
        $typesstr = trim($typesstr);
        $types = [];
        $tmparr = explode("\n", $typesstr);
        foreach ($tmparr as $row) {
            $tmparr2 = explode('|', $row);
            $val = $tmparr2[0];
            $title = $tmparr2[1];
            $types[$val] = $title;
        }
        return $types;
    }

    /**
     * Form definition.
     * @throws \HTML_QuickForm_Error
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'parentid');

        $mform->addElement('header', 'definesubsection', get_string('definesubsection', 'format_visualsections'), '');
        $mform->addElement('text', 'name', get_string('subsectionname', 'format_visualsections'));
        $mform->addRule('name', get_string('required'), 'required');
        $opts = $this->subsectiontypes();
        $mform->addElement('select', 'typecode', get_string('subsectiontype', 'format_visualsections'), $opts);
        $mform->addRule('typecode', get_string('required'), 'required');
        $opts = [
            's' => get_string('size:s', 'format_visualsections'),
            'm' => get_string('size:m', 'format_visualsections'),
            'l' => get_string('size:l', 'format_visualsections')
        ];
        $mform->addElement('select', 'size', get_string('subsectionsize', 'format_visualsections'), $opts);
        $mform->addRule('size', get_string('required'), 'required');
    }
}
