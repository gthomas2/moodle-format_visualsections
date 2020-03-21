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
 * format_visualsections related unit tests
 *
 * @package    format_visualsections
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

use format_visualsections\model\subsection;

/**
 * base_model_testcase unit tests
 *
 * @package    format_visualsections
 * @copyright  2020 Guy Thomas Citricity Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base_model_testcase extends advanced_testcase {
    public function test_subsection_noid() {
        $data = (object) [
            'parentid' => 3,
            'name' => 'testname',
            'type' => 'testtype'
        ];
        $model = subsection::from_data($data);
        $this->assertEquals($data->parentid, $model->parentid);
        $this->assertEquals($data->name, $model->name);
        $this->assertEquals($data->type, $model->type);
        $this->assertEquals(null, $model->id);
    }
}