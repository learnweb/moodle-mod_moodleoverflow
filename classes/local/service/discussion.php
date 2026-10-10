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

namespace mod_moodleoverflow\local\service;

use core_search\manager;
use dml_exception;
use Exception;
use mod_moodleoverflow\local\models;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\ratings;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Service class for use cases that concern a discussion as a whole.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion {
    /**
     * Moves a discussion to another moodleoverflow of the same course.
     *
     * The discussion model moves its own data (files, ratings, read records, discussion subscriptions, mail digest quere)
     * and triggers the event. This method adds what lies outside the discussion: the grades of both instances and the search index.
     *
     * @param models\discussion $discussion The discussion to move
     * @param models\moodleoverflow $destination The moodleoverflow the discussion is moved to
     * @param int $userid The user who moves the discussion
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function move(models\discussion $discussion, models\moodleoverflow $destination, int $userid): void {
        global $DB;
        permissions::ensure(permissions::can_move_discussion($discussion, $destination, $userid), 'invalidmovedestination');
        $source = $discussion->get_moodleoverflow();

        $transaction = $DB->start_delegated_transaction();
        try {
            $discussion->move_discussion($destination);

            // The reputation of authors and raters changes in both instances, so their grades change as well.
            $affectedusers = array_filter(
                array_unique(array_merge(
                    $DB->get_fieldset_select('moodleoverflow_posts', 'userid', 'discussion = ?', [$discussion->get_id()]),
                    $DB->get_fieldset_select('moodleoverflow_ratings', 'userid', 'discussionid = ?', [$discussion->get_id()])
                )),
                fn($userid) => $userid != 0,
            );
            foreach ([$source, $destination] as $modflow) {
                foreach ($affectedusers as $affecteduserid) {
                    $reputation = ratings::get_reputation($modflow->id, $affecteduserid, true);
                    moodleoverflow_update_user_grade($modflow, $reputation, $affecteduserid);
                }
            }
            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
        // The indexed posts keep the context of the source; reindexing the destination replaces them.
        manager::request_index($destination->get_context(), 'mod_moodleoverflow-post');
    }
}
