<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\output;

use core\output\renderer_base;
use core\output\templatable;
use core_user;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use moodle_url;

/**
 * The author of a post as a user sees it: the name (or a pseudonym), and the profile link and picture only where the
 * user may know who wrote the post. This is the only place that loads the author's user record for display.
 *
 * Template data: name, profileurl (null if hidden), picture (HTML, empty if hidden or anonymous).
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class author implements templatable {
    /**
     * Constructor.
     * @param post $post
     * @param int $userid The user who sees the post
     */
    public function __construct(
        /** @var post The post whose author is shown */
        private post $post,
        /** @var int The user who sees the post */
        private int $userid
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $authorid = $this->post->get_userid();
        $modflow = $this->post->get_moodleoverflow();
        if (!permissions::can_view_author($this->post, $this->userid)) {
            // Hidden by the anonymity setting, or no author any more (userid 0, set by the privacy provider).
            $name = $authorid
                ? $this->post->get_discussion()->get_pseudonym($authorid)
                : get_string('privacy:anonym_user_name', 'mod_moodleoverflow');
            return ['name' => $name, 'profileurl' => null, 'picture' => ''];
        }

        $profileurl = (new moodle_url('/user/view.php', ['id' => $authorid, 'course' => $modflow->course]))->out(false);
        if ($authorid === $this->userid && permissions::is_author_hidden($this->post)) {
            // The user's own post, which the others see anonymously.
            return ['name' => get_string('anonym_you', 'mod_moodleoverflow'), 'profileurl' => $profileurl, 'picture' => ''];
        }

        $user = core_user::get_user($authorid, '*', MUST_EXIST);
        return [
            'name' => fullname($user, has_capability('moodle/site:viewfullnames', $modflow->get_context(), $this->userid)),
            'profileurl' => $profileurl,
            'picture' => $output->user_picture($user, ['courseid' => $modflow->course, 'link' => false]),
        ];
    }
}
