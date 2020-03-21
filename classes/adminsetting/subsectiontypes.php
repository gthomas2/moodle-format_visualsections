<?php

namespace format_visualsections\adminsetting;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/adminlib.php');

use format_visualsections\model\subsectiontype;

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
     * Take config data string and convert it to types array.
     * @param null|string $data
     * @return subsectiontype[]
     */
    public static function config_to_types_array(?string $data = null): array {
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
            $types[] = subsectiontype::from_data([
                'code' => $tmparr[0],
                'name' => $tmparr[1],
                'image' => $img,
                'pos' => $i
            ]);
            $i++;
        }
        return $types;
    }

    /**
     * Return an XHTML string for the setting
     * @return string Returns an XHTML string
     */
    public function output_html($data, $query='') {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd('format_visualsections/adminsetting_subsectiontypes', 'init');

        $types = self::config_to_types_array($data);

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