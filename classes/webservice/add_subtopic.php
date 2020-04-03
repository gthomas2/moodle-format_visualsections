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
 * Add subtopic webservice
 * @package     format_visualsections
 * @author      Guy Thomas
 * @copyright   Copyright (c) 2020 Citricity Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_visualsections\webservice;

defined('MOODLE_INTERNAL') || die();

use format_visualsections\service\section;

require_once(__DIR__ . '/../../../../../lib/externallib.php');

/**
 * Add subtopic webservice
 * @package     format_visualsections
 * @author      Guy Thomas
 * @copyright   Copyright (c) 2020 Citricity Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_subtopic extends \external_api {
    /**
     * @return \external_function_parameters
     */
    public static function service_parameters() {
        $parameters = [
            'parentsectionid' => new \external_value(PARAM_INT, 'Parent section id', VALUE_REQUIRED),
            'subsectiontype' => new \external_value(PARAM_ALPHANUMEXT, 'Sub section type code', VALUE_REQUIRED)
        ];
        return new \external_function_parameters($parameters);
    }

    /**
     * @return \external_single_structure
     */
    public static function service_returns() {
        $keys = [
            'success' => new \external_value(PARAM_BOOL, 'Was the sub-section successfully added', VALUE_REQUIRED),
            'subsectionid' => new \external_value(PARAM_INT, 'Id of newly created subsection', VALUE_REQUIRED)
        ];

        return new \external_single_structure($keys, 'subsectionresult');
    }

    /**
     * Main service method.
     * @param $params
     * @return \format_visualsections\service\stdClass
     * @throws \invalid_parameter_exception
     */
    public static function service(int $parentsectionid, string $subsectiontype) {
        global $DB;

        $service = section::instance();

        $params = ['parentsectionid' => $parentsectionid, 'subsectiontype' => $subsectiontype];
        $params = self::validate_parameters(self::service_parameters(), $params);

        $parentsection = $DB->get_record('course_sections', ['id' => $params['parentsectionid']]);
        if (!$parentsection) {
            throw new \coding_exception('Invalid parent section id '.$params['parentsectionid']);
        }

        require_capability('moodle/course:update', \context_course::instance($parentsection->course));

        return $service->upsert_subsection($params['parentsectionid'], $params['subsectiontype']);
    }
}
