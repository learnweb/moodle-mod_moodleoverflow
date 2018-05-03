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
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_generator extends testing_module_generator {

    /**
     * @var int keep track of how many moodleoverflow discussions have been created.
     */
    protected $discussioncount = 0;

    /**
     * @var int keep track of how many moodleoverflow posts have been created.
     */
    protected $postcount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->discussioncount = 0;
        $this->postcount = 0;

        parent::reset();
    }

    /**
     * Creates a moodleoverflow instance.
     *
     * @param null       $record
     * @param array|null $options
     *
     * @return stdClass
     */
    public function create_instance($record = null, array $options = null) {

        // Transform the record.
        $record = (object) (array) $record;

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
            $record->forcesubscribe = MOODLEOVERFLOW_CHOOSESUBSCRIBE;
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Creates a moodleoverflow discussion.
     *
     * @param null $record
     *
     * @return bool|int
     * @throws coding_exception
     */
    public function create_discussion($record = null) {
        global $DB;

        // Increment the discussion count.
        $this->discussioncount++;

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
            $record['name'] = 'Discussion ' . $this->discussioncount;
        }
        if (!isset($record['subject'])) {
            $record['subject'] = 'Subject for discussion ' . $this->discussioncount;
        }
        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'Message for discussion ' . $this->discussioncount);
        }
        if (!isset($record['messageformat'])) {
            $record['messageformat'] = editors_get_preferred_format();
        }
        if (!isset($record['timestart'])) {
            $record['timestart'] = "0";
        }
        if (!isset($record['timeend'])) {
            $record['timeend'] = "0";
        }
        if (isset($record['mailed'])) {
            $mailed = $record['mailed'];
        }
        if (isset($record['timemodified'])) {
            $timemodified = $record['timemodified'];
        }
        $record['attachments'] = null;

        // Transform the array into an object.
        $record = (object) $record;

        // Get the module context.
        $cm = $DB->get_record('course_modules', array('module' => 15));
        $modulecontext = \context_module::instance($cm->id);

        // Add the discussion.
        $record->id = moodleoverflow_add_discussion($record, $modulecontext, $record->userid);

        if (isset($timemodified) || isset($mailed)) {
            $post = $DB->get_record('moodleoverflow_posts', array('discussion' => $record->id));

            if (isset($mailed)) {
                $post->mailed = $mailed;
            }

            if (isset($timemodified)) {
                $record->timemodified = $timemodified;
                $post->modified = $post->created = $timemodified;
                $DB->update_record('moodleoverflow_discussions', $record);
            }

            $DB->update_record('moodleoverflow_discussions', $record);
        }

        // Return the id of the discussion.
        return $record->id;
    }

    /**
     * Function to create a dummy post.
     *
     * @param array|stdClass $record
     * @return stdClass the post object
     */
    public function create_post($record = null) {
        global $DB;
        // Increment the forum post count.
        $this->postcount++;
        // Variable to store time.
        $time = time() + $this->postcount;
        $record = (array) $record;
        if (!isset($record['discussion'])) {
            throw new coding_exception('discussion must be present in phpunit_util::create_post() $record');
        }
        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_post() $record');
        }
        if (!isset($record['parent'])) {
            $record['parent'] = 0;
        }
        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'Forum message post ' . $this->forumpostcount);
        }
        if (!isset($record['created'])) {
            $record['created'] = $time;
        }
        if (!isset($record['modified'])) {
            $record['modified'] = $time;
        }
        if (!isset($record['mailed'])) {
            $record['mailed'] = 0;
        }
        if (!isset($record['messageformat'])) {
            $record['messageformat'] = 0;
        }
        if (!isset($record['attachment'])) {
            $record['attachment'] = "";
        }

        $record = (object) $record;
        // Add the post.
        $record->id = $DB->insert_record('moodleoverflow_posts', $record);

        // Update the last post.
        moodleoverflow_discussion_update_last_post($record->discussion);
        return $record;
    }

    /**
     * Function to create a dummy rating.
     *
     * @param array|stdClass $record
     * @return stdClass the post object
     */
    public function create_rating($record = null) {
        global $DB;

        $time = time();
        $record = (array) $record;
        if (!isset($record['moodleoverflowid'])) {
            throw new coding_exception('moodleoverflowid must be present in phpunit_util::create_rating() $record');
        }
        if (!isset($record['discussionid'])) {
            throw new coding_exception('discussionid must be present in phpunit_util::create_rating() $record');
        }
        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_rating() $record');
        }
        if (!isset($record['postid'])) {
            throw new coding_exception('postid must be present in phpunit_util::create_rating() $record');
        }
        if (!isset($record['rating'])) {
            $record['rating'] = 1;
        }
        if (!isset($record['firstrated'])) {
            $record['firstrated'] = $time;
        }
        if (!isset($record['lastchanged'])) {
            $record['lastchanged'] = $time;
        }

        $record = (object) $record;
        // Add the post.
        $record->id = $DB->insert_record('moodleoverflow_ratings', $record);

        return $record;
    }
}
