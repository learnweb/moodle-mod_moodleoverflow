<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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

namespace mod_moodleoverflow\output;

use core\output\notification;
use core\output\plugin_renderer_base;

/**
 * Default plugin renderer
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Prepares and renders React component.
     *
     * @param string $component The component in js/esm, e.g. 'UserPage'.
     * @param object $props The properties passed to the component.
     * @return string
     */
    public function render_react(string $component, object $props): string {
        global $CFG;
        if ($CFG->branch < 503) {
            return $this->output->notification(
                get_string('featurenotavailable', 'mod_moodleoverflow'),
                notification::NOTIFY_WARNING
            );
        }
        // Preload all plugin strings, because React components read them synchronously (M.util.get_string()).
        $strings = get_string_manager()->load_component_strings('mod_moodleoverflow', current_language());
        $this->page->requires->strings_for_js(array_keys($strings), 'mod_moodleoverflow');
        $this->page->requires->strings_for_js(['loading', 'noresults', 'nothingtodisplay', 'posts'], 'core');

        return $this->output->render_react_component("mod_moodleoverflow/$component", $props);
    }
}
