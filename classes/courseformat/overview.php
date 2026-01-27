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

namespace mod_moodleoverflow\courseformat;


use cm_info;
use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\output\renderer_helper;
use core\url;
use core_courseformat\activityoverviewbase;
use core_courseformat\local\overview\overviewitem;
use mod_moodleoverflow\readtracking;

/**
 * Checklist overview integration (for Moodle 5.0+)
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends activityoverviewbase {
    /**
     * Constructor.
     *
     * @param cm_info $cm the course module instance.
     * @param renderer_helper $rendererhelper the renderer helper.
     */
    public function __construct(cm_info $cm, renderer_helper $rendererhelper) {
        parent::__construct($cm);
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        $url = new url('/mod/moodleoverflow/view.php', ['id' => $this->cm->id]);

        $text = get_string('view');

        if (
            class_exists(button::class) &&
            (new \ReflectionClass(button::class))->hasConstant('BODY_OUTLINE')
        ) {
            $bodyoutline = button::BODY_OUTLINE;
            $buttonclass = $bodyoutline->classes();
        } else {
            $buttonclass = "btn btn-outline-secondary";
        }

        $content = new action_link($url, $text, null, ['class' => $buttonclass]);
        return new overviewitem(get_string('actions'), $text, $content, text_align::CENTER);
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            'unread_posts' => $this->get_extra_unread_posts_overview(),
            'readtracking' => $this->get_extra_subscriptions_overview(),
            'subscriptions' => $this->get_extra_readtracking_overview(),
        ];
    }

    /**
     * Get overview item for unread posts.
     *
     * @return overviewitem|null
     */
    private function get_extra_unread_posts_overview(): ?overviewitem {
        $unreadcount = readtracking::moodleoverflow_count_unread_posts_moodleoverflow($this->cm);
        if ($unreadcount === 0) {
            return null;
        }

        $content = new action_link(
            url: new url('/mod/moodleoverflow/view.php', ['id' => $this->cm->id]),
            text: $unreadcount,
            attributes: ['class' => button::SECONDARY_OUTLINE->classes()],
        );

        return new overviewitem(
            name: get_string('unreadposts', 'moodleoverflow'),
            value: $unreadcount,
            content: $content,
            textalign: text_align::CENTER,
        );
    }

   /**
    * Get overview item for subscriptions. A user can choose to (un)subscribe to a moodleoverflow if possible.
    * @return overviewitem|null
    */
    private function get_extra_subscriptions_overview(): ?overviewitem {
        return null;
    }

    /**
    * Get overview item for readtracking. A user can choose to enable/disable readtracking for a moodleoverflow if possible.
    * @return overviewitem|null
     */
    private function get_extra_readtracking_overview(): ?overviewitem {
        return null;
    }
}
