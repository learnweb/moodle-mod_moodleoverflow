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
 * Define all the backup steps that will be used by the backup_moodleoverflow_activity_task
 *
 * @package   mod_moodleoverflow
 * @category  backup
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define the complete moodleoverflow structure for backup, with file and id annotations
 *
 * @package   mod_moodleoverflow
 * @category  backup
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_moodleoverflow_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the moodleoverflow instance.
        $moodleoverflow = new backup_nested_element('moodleoverflow', array('id'), array(
            'name', 'intro', 'introformat', 'grade',
            'forcesubscribe', 'trackingtype', 'timemodified',
            'ratingpreference', 'coursewidereputation', 'allownegativereputation'));

        // Define each element separated
        $discussions = new backup_nested_element('discussions');
        $discussion = new backup_nested_element('discussion', array('id'), array(
            'name', 'firstpost', 'userid', 'timemodified', 'usermodified', 'timestart'));

        $posts = new backup_nested_element('posts');

        $post = new backup_nested_element('post', array('id'), array(
            'parent', 'userid', 'created', 'modified',
            'mailed', 'message', 'messageformat'));

        $ratings = new backup_nested_element('ratings');

        $rating = new backup_nested_element('rating', array('id'), array(
            'userid', 'rating', 'firstrated', 'lastchanged'));

        $discussionsubs = new backup_nested_element('discussion_subs');

        $discussionsub = new backup_nested_element('discussion_sub', array('id'), array(
            'userid',
            'preference',
        ));
        //Hier weitermachen.


        // Build the tree.
        $moodleoverflow->add_child($discussions);
        $discussions->add_child($discussion);

        $discussion->add_child($posts);
        $posts->add_child($post);

        $post->add_child($ratings);
        $ratings->add_child($rating);

        $discussion->add_child($discussionsubs);
        $discussionsubs->add_child($discussionsub);

        // Define data sources.
        $moodleoverflow->set_source_table('moodleoverflow', array('id' => backup::VAR_ACTIVITYID));

        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $discussion->set_source_sql('
                SELECT *
                  FROM {moodleoverflow_discussions}
                 WHERE moodleoverflow = ?',
                array(backup::VAR_PARENTID));

            // Need posts ordered by id so parents are always before childs on restore
            $post->set_source_table('moodleoverflow_posts', array('discussion' => backup::VAR_PARENTID), 'id ASC');
        }

        // Define id annotations
        $post->annotate_ids('user', 'userid');

        // Define file annotations (we do not use itemid in this example).
        $moodleoverflow->annotate_files('mod_moodleoverflow', 'intro', null);
        $post->annotate_files('mod_moodleoverflow', 'post', 'id');

        // Return the root element (moodleoverflow), wrapped into standard activity structure.
        return $this->prepare_activity_structure($moodleoverflow);
    }
}
