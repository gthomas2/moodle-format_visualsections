<?php

namespace format_visualsections\lib;

defined('MOODLE_INTERNAL') || die;

use lang_string;

class local {
    /**
     * Get activity choices.
     * @return array
     */
    public static function get_activity_choices() {
        $acts    = get_module_types_names();
        $choices = ['_' => new lang_string('noselection', 'format_visualsections')];
        foreach ($acts as $key => $act) {
            $choices[$key] = $act;
        }
        return $choices;
    }
}