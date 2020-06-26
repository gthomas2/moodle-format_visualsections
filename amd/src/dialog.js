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
 * @package   format_visualsections
 * @copyright Copyright (c) 2020 Citricity Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["core/modal_factory", "core/modal_events"], function(ModalFactory, ModalEvents) {
    return {
        error: function(title, body, onClose) {
            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: body
            })
            .then(function(modal) {
                modal.setSmall();
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                    if (typeof(onClose) === 'function') {
                        onClose();
                    }
                });
                modal.show();
            });
        }
    };
});