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
 * mod_moodleoverflow data generator
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Moodleoverflow module data generator class
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_generator extends testing_module_generator {

    /**
     * @var int keep track of how many moodleoverflow discussions have been created.
     */
    protected $moodleoverflowdiscussioncount = 0;

    /**
     * @var int keep track of how many moodleoverflow posts have been created.
     */
    protected $moodleoverflowpostcount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->moodleoverflowdiscussioncount = 0;
        $this->moodleoverflowpostcount = 0;

        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        global $CFG;

        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test MO Instance';
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test Intro';
        }
        if (!isset($record->introformat)) {
            $record->introformat = 1;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }
        if (!isset($record->forcesubscribe)) {
            $record->forcesubscribe = 1;
        }
        if (!isset($record->trackingtype)) {
            $record->trackingtype = 1;
        }

        return parent::create_instance($record, (array)$options);
    }

    public function create_discussion($record = null) {
        global $DB;

        // Increment the discussion count.
        $this->moodleoverflowdiscussioncount++;

        // Create the record.
        $record = (array) $record;

        // Check needed submitted values.
        if (!isset($record['course'])) {
            throw new coding_exception('course must be present in phpunit_util:create_discussion() $record');
        }
        if (!isset($record['moodleoverflow'])) {
            throw new coding_exception('moodleoverflow must be present in phpunit_util:create_discussion() $record');
        }
        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util:create_discussion() $record');
        }

        // Set default values.
        if (!isset($record['name'])) {
            $record['name'] = 'Discussion ' . $this->moodleoverflowdiscussioncount;
        }
        if (!isset($record['subject'])) {
            $record['subject'] = 'Subject for discussion ' . $this->moodleoverflowdiscussioncount;
        }
        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'Message for discussion ' . $this->moodleoverflowdiscussioncount);
        }
        if (!isset($record['messageformat'])) {
            $record['messageformat'] = editors_get_preferred_format();
        }

        // Transform the array into an object.
        $record = (object) $record;

        // Add the discussion.
        $record->id = moodleoverflow_add_discussion($record, $record->userid);

        // Return the id of the discussion.
        return $record->id;
    }


}
