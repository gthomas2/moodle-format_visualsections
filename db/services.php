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
 * Services
 * @author    Guy Thomas
 * @copyright Copyright (c) 2020 Citricity Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'format_visualsections_add_subtopic' => [
        'classname'     => format_visualsections\webservice\add_subtopic::class,
        'methodname'    => 'service',
        'description'   => 'Add sub topic to topic',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true
    ],
    'format_visualsections_subtopic_addimage' => [
        'classname'     => format_visualsections\webservice\subtopictype_addimage::class,
        'methodname'    => 'service',
        'description'   => 'Add sub topic image',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true
    ]
];