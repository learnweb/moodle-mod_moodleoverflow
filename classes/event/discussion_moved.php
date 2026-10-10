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

namespace mod_moodleoverflow\event;

use coding_exception;
use moodle_url;

/**
 * The mod_moodleoverflow discussion moved event class.
 *
 * Triggered in the context of the destination moodleoverflow.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int frommoodleoverflowid: The id of the moodleoverflow the discussion was moved from.
 *      - int tomoodleoverflowid: The id of the moodleoverflow the discussion was moved to.
 * }
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_moved extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'moodleoverflow_discussions';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has moved the discussion with id '$this->objectid' from the " .
            "moodleoverflow with id '{$this->other['frommoodleoverflowid']}' to the moodleoverflow with id " .
            "'{$this->other['tomoodleoverflowid']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussionmoved', 'mod_moodleoverflow');
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $this->objectid]);
    }

    /**
     * Custom validation.
     *
     * @return void
     * @throws coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        foreach (['frommoodleoverflowid', 'tomoodleoverflowid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new coding_exception("The '$key' value must be set in other.");
            }
        }
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * Maps the object id when the log is restored.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'moodleoverflow_discussions', 'restore' => 'moodleoverflow_discussion'];
    }

    /**
     * Maps the ids in other when the log is restored.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'frommoodleoverflowid' => ['db' => 'moodleoverflow', 'restore' => 'moodleoverflow'],
            'tomoodleoverflowid' => ['db' => 'moodleoverflow', 'restore' => 'moodleoverflow'],
        ];
    }
}
