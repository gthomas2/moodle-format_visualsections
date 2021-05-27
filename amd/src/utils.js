/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Utility lib.
 *
 * @package   local_tlcore
 * @copyright Copyright (c) 2019 Titus Learning.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return new function() {

        /**
         * When evaluateFunction returns true.
         * @author Guy Thomas
         * @param evaluateFunction
         * @param maxIterations
         * @returns {promise} jQuery promise
         */
        this.whenTrue = function(evaluateFunction, maxIterations) {

            maxIterations = !maxIterations ? 10 : maxIterations;

            var dfd = $.Deferred();
            var i = 0;

            var iv = setInterval(function() {
                i = !i ? 1 : i + 1;
                if (i > maxIterations) {
                    clearInterval(iv);
                    dfd.reject();
                }
                if (evaluateFunction()) {
                    clearInterval(iv);
                    dfd.resolve();
                }
            }, 200);

            return dfd.promise();
        };
    };
});
