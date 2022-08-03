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
 * Class for easily caching capabilities.
 *
 * @package   mod_moodleoverflow
 * @copyright 2022 Justus Dieckmann WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use context;

/**
 * Class for easily caching capabilities.
 *
 * @package   mod_moodleoverflow
 * @copyright 2022 Justus Dieckmann WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capabilities {

    const ADD_INSTANCE = 'mod/moodleoverflow:addinstance';
    const VIEW = 'mod/moodleoverflow:view';
    const VIEW_DISCUSSION = 'mod/moodleoverflow:viewdiscussion';
    const REPLY_POST = 'mod/moodleoverflow:replypost';
    const START_DISCUSSION = 'mod/moodleoverflow:startdiscussion';
    const EDIT_ANY_POST = 'mod/moodleoverflow:editanypost';
    const DELETE_OWN_POST = 'mod/moodleoverflow:deleteownpost';
    const DELETE_ANY_POST = 'mod/moodleoverflow:deleteanypost';
    const DELETE_ANY_RATING = 'mod/moodleoverflow:viewanyrating';
    const RATE_POST = 'mod/moodleoverflow:ratepost';
    const MARK_SOLVED = 'mod/moodleoverflow:marksolved';
    const MANAGE_SUBSCRIPTIONS = 'mod/moodleoverflow:managesubscriptions';
    const ALLOW_FORCE_SUBSCRIBE = 'mod/moodleoverflow:allowforcesubscribe';
    const CREATE_ATTACHMENT = 'mod/moodleoverflow:createattachment';
    const REVIEW_POST = 'mod/moodleoverflow:reviewpost';

    private static $cache = [];

    public static function has(string $capability, context $context, $userid = null): bool {
        global $USER;
        if (!$userid) {
            $userid = $USER->id;
        }

        $key = "$userid:$context->id:$capability";

        if (!isset($cache[$key])) {
            $cache[$key] = has_capability($capability, $context, $userid);
        }

        return $cache[$key];
    }

}
