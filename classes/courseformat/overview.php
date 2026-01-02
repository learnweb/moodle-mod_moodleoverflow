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

use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\url;
use core_courseformat\local\overview\overviewitem;
use mod_moodleoverflow\manager;

/**
 * Checklist overview integration (for Moodle 5.1+)
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {
    /**
     * @var manager the ratingallocate manager.
     */
    private manager $manager;

    /**
     * Constructor.
     *
     * @param \cm_info $cm the course module instance.
     * @param \core\output\renderer_helper $rendererhelper the renderer helper.
     */
    public function __construct(
        \cm_info $cm,
        \core\output\renderer_helper $rendererhelper
    ) {
        parent::__construct($cm);
        $this->manager = manager::create_from_coursemodule($cm);
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        $url = new url(
            '/mod/moodleoverflow/view.php',
            ['id' => $this->cm->id],
        );

        $text = get_string('view');

        if (defined('button::BODY_OUTLINE')) {
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
        ];
    }

    /**
     * Get overview item for unread posts.
     *
     * @return overviewitem|null
     */
    private function get_extra_unread_posts_overview(): ?overviewitem {
        $unreadcount = $this->manager->count_unread_posts_for_user();
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
}
