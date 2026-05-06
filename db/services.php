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
 * Moodleoverflow external functions and service definitions.
 *
 * @package    mod_moodleoverflow
 * @category   external
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_moodleoverflow_record_vote' => [
        'classname' => 'mod_moodleoverflow\external\record_vote',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/record_vote.php',
        'description' => 'Records a vote and updates the reputation of a user',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/moodleoverflow:ratepost',
    ],
    'mod_moodleoverflow_review_approve_post' => [
        'classname' => 'mod_moodleoverflow\external\approve_post',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/approve_post.php',
        'description' => 'Approves a post',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_moodleoverflow_review_reject_post' => [
        'classname' => 'mod_moodleoverflow\external\review_reject_post',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/review_reject_post.php',
        'description' => 'Rejects a post',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_moodleoverflow_change_subscription_mode' => [
        'classname' => 'mod_moodleoverflow\external\change_subscription_mode',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/change_subscription_mode.php',
        'description' => 'Changes subscription mode',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_moodleoverflow_change_readtracking_mode' => [
        'classname' => 'mod_moodleoverflow\external\change_readtracking_mode',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/change_readtracking_mode.php',
        'description' => 'Changes readtracking mode',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_moodleoverflow_mark_post_read' => [
        'classname' => 'mod_moodleoverflow\external\mark_post_read',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/mark_post_read.php',
        'description' => 'Marks all posts in a discussion or moodleoverflow as read',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_moodleoverflow_move_discussion' => [
        'classname' => 'mod_moodleoverflow\external\move_discussion',
        'methodname' => 'execute',
        'classpath' => 'mod/moodleoverflow/classes/external/move_discussion.php',
        'description' => 'Moves a discussion to another moodleoverflow',
        'type' => 'write',
        'ajax' => true,
    ],
];
