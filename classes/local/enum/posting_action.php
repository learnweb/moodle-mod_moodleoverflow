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

namespace mod_moodleoverflow\local\enum;

use core\exception\moodle_exception;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;

/**
 * What the post form does.
 *
 * The value is the name of the parameter that carries the id of the object (in the url of post.php and the hidden
 * field of the post form).
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum posting_action: string {
    // Start a discussion in the moodleoverflow with the id.
    case CREATE = 'moodleoverflow';

    // Reply to the post with the id (an answer to the question or a comment to an answer).
    case REPLY = 'reply';

    // Edit the post with the id.
    case EDIT = 'edit';

    /**
     * Finds the action in the parameters: exactly one of them carries an id.
     *
     * @param callable $param fn(string $name): int, the value of a parameter, 0 if it is missing
     * @return array [posting_action $action, int $id]
     * @throws moodle_exception
     */
    public static function from_params(callable $param): array {
        $found = [];
        foreach (self::cases() as $action) {
            $id = (int) $param($action->value);
            if ($id > 0) {
                $found[] = [$action, $id];
            }
        }
        if (count($found) !== 1) {
            throw new moodle_exception('wrongparametercount', 'moodleoverflow');
        }
        return $found[0];
    }

    /**
     * Loads the objects of the action.
     *
     * @param int $id The id from the parameter of the action
     * @return array [moodleoverflow $modflow, ?post $post]: the post is the parent for REPLY, the edited post for EDIT,
     *               null for CREATE
     * @throws moodle_exception
     */
    public function load_objects(int $id): array {
        if ($this === self::CREATE) {
            return [moodleoverflow::from_id($id), null];
        }
        $post = post::from_id($id);
        return [$post->get_moodleoverflow(), $post];
    }

    /**
     * The error code when the user may not do the action.
     *
     * @return string
     */
    public function get_error_code(): string {
        return match ($this) {
            self::CREATE => 'nopostmoodleoverflow',
            self::REPLY => 'cannotreply',
            self::EDIT => 'cannoteditposts',
        };
    }
}
