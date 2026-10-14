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
 * Internal library of functions for module moodleoverflow
 *
 * All the moodleoverflow specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/lib.php');

/**
 * Modifies the session to return back to where the user is coming from.
 *
 * @param object $default
 *
 * @return mixed
 */
function moodleoverflow_go_back_to($default) {
    global $SESSION;
    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Updates all grades.
 * @return void
 * @throws coding_exception|dml_exception
 */
function moodleoverflow_update_all_grades(): void {
    global $DB;
    foreach ($DB->get_records('moodleoverflow') as $record) {
        service\rating::update_all_grades(moodleoverflow::from_record($record));
    }
}

/**
 * Caches all language strings keys so react components can access them.
 * @return void
 */
function moodleoverflow_cache_strings(): void {
    global $PAGE;
    $strings = get_string_manager()->load_component_strings('mod_moodleoverflow', current_language());
    $PAGE->requires->strings_for_js(array_keys($strings), 'mod_moodleoverflow');
    $PAGE->requires->strings_for_js(['loading', 'noresults', 'nothingtodisplay', 'posts'], 'core');
}
