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
 * Move subtopic webservice
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
 * Move subtopic webservice
 * @package     format_visualsections
 * @author      Guy Thomas
 * @copyright   Copyright (c) 2020 Citricity Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_subtopic extends \external_api {
    /**
     * @return \external_function_parameters
     */
    public static function service_parameters() {
        $parameters = [
            'request' => new \external_single_structure([
                'parentsectionid' => new \external_value(PARAM_INT, 'Parent section id', VALUE_REQUIRED),
                'srcsectionid' => new \external_value(PARAM_INT, 'Soruce section id', VALUE_REQUIRED),
                'targetsectionid' => new \external_value(PARAM_INT, 'Target section id', VALUE_OPTIONAL)
            ])
        ];
        return new \external_function_parameters($parameters);
    }

    /**
     * @return \external_single_structure
     */
    public static function service_returns() {
        $keys = [
            'success' => new \external_value(PARAM_BOOL, 'Was the sub-section successfully added', VALUE_REQUIRED)
        ];

        return new \external_single_structure($keys, 'subsectionresult');
    }

    /**
     * Main service method.
     * @param $params
     * @return \format_visualsections\service\stdClass
     * @throws \invalid_parameter_exception
     */
    public static function service($request) {
        global $DB;

        $service = section::instance();
        $args = (object) self::validate_parameters(self::service_parameters(), ['request' => $request])['request'];

        $parentsection = $DB->get_record('course_sections', ['id' => $args->parentsectionid]);
        if (!$parentsection) {
            throw new \coding_exception('Invalid parent section id '.$args->parentsectionid);
        }

        require_capability('moodle/course:movesections', \context_course::instance($parentsection->course));

        $success = $service->subtopic_move($args->parentsectionid, $args->srcsectionid, $args->targetsectionid ?? null);
        return ['success' => $success];
    }
}
