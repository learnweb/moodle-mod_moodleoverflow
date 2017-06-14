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

/**
 * Renderer definition
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__. '/lib.php');
require_once($CFG->libdir. '/weblib.php');

class mod_moodleoverflow_renderer extends plugin_renderer_base {

    // Renders a list of discussions.
    public function render_discussion_list($data) {
        return $this->render_from_template('mod_moodleoverflow/discussion_list', $data);
    }

    // Renders a dummy post if capabilities are missing.
    public function render_post_dummy_cantsee($data) {
        return $this->render_from_template('mod_moodleoverflow/post_dummy_cantsee', $data);
    }

    // Renders the initial question of a discussion.
    public function render_question($data) {
        return $this->render_from_template('mod_moodleoverflow/question', $data);
    }

    // Renders an answer of the discussion.
    public function render_answer($data) {
        return $this->render_from_template('mod_moodleoverflow/answer', $data);
    }
}