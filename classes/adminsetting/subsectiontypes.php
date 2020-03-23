<?php

namespace format_visualsections\adminsetting;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/adminlib.php');

use format_visualsections\model\subsectiontype;
use format_visualsections\service\section;

/**
 * Class sectionconfig
 */
class subsectiontypes extends \admin_setting_configtext {

    /**
     * Validate data before storage
     *
     * @param string $data data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        $parentvalidation = parent::validate($data);
        if ($parentvalidation === true) {
            return true;
        } else {
            return $parentvalidation;
        }
    }

    /**
     * Return an XHTML string for the setting
     * @return string Returns an XHTML string
     */
    public function output_html($data, $query='') {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd('format_visualsections/adminsetting_subsectiontypes', 'init');

        $svc = section::instance();

        $types = array_values($svc->config_to_types_array($data));

        $default = $this->get_defaultsetting();
        $context = (object) [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'types' => $types,
            'data' => $data,
            'forceltr' => $this->get_force_ltr(),
        ];
        $element = $OUTPUT->render_from_template('format_visualsections/subsectionconfig', $context);
        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', $default, $query);
    }
}