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
use context_module;
use core\context\module;
use core\exception\moodle_exception;
use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\output\renderer_helper;
use core\url;
use core_courseformat\activityoverviewbase;
use core_courseformat\local\overview\overviewitem;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\subscriptions;

/**
 * Checklist overview integration (for Moodle 5.0+)
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends activityoverviewbase {
    /** @var object The moodleoverflow that is represented by the overview */
    public object $moodleoverflow;

    /** @var module The module context. Important for different operations */
    public module $modulecontext;

    /**
     * Constructor.
     *
     * @param cm_info $cm the course module instance.
     * @param renderer_helper $rendererhelper the renderer helper.
     */
    public function __construct(cm_info $cm, renderer_helper $rendererhelper) {
        global $DB;
        parent::__construct($cm);

        // Build important objects.
        $this->moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $this->modulecontext = context_module::instance($this->cm->id);
    }

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        $url = new url('/mod/moodleoverflow/view.php', ['id' => $this->cm->id]);

        if (
            class_exists(button::class) &&
            (new \ReflectionClass(button::class))->hasConstant('BODY_OUTLINE')
        ) {
            $bodyoutline = button::BODY_OUTLINE;
            $buttonclass = $bodyoutline->classes();
        } else {
            $buttonclass = "btn btn-outline-secondary";
        }

        $content = new action_link($url, get_string('view'), null, ['class' => $buttonclass]);
        return new overviewitem(get_string('actions'), get_string('view'), $content, text_align::CENTER);
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        global $CFG;
        return $CFG->branch <= 500 ? [] : [
            'subscriptions' => $this->get_extra_subscriptions_overview(),
            'readtracking' => $this->get_extra_readtracking_overview(),
            'unread_posts' => $this->get_extra_unread_posts_overview(),
        ];
    }

    /**
     * Get overview item for unread posts.
     *
     * @return overviewitem|null
     */
    private function get_extra_unread_posts_overview(): ?overviewitem {
        global $OUTPUT, $USER;
        $mustachedata = [
            'itemid' => 'moodleoverflow-markpostsread-' . $this->moodleoverflow->id,
            'domain' => 'moodleoverflow',
            'instanceid' => $this->moodleoverflow->id,
            'userid' => $USER->id,
            'unreadlink' => new url('/mod/moodleoverflow/view.php', ['id' => $this->cm->id]),
            'unreadamount' => readtracking::moodleoverflow_count_unread_posts_moodleoverflow($this->cm),
        ];
        $name = get_string('unreadposts', 'moodleoverflow');
        return new overviewitem($name, $name, $OUTPUT->render_from_template('mod_moodleoverflow/readtracking', $mustachedata));
    }

    /**
     * Get overview item for subscriptions. A user can choose to (un)subscribe to a moodleoverflow if possible.
     * @return overviewitem|null
     */
    private function get_extra_subscriptions_overview(): ?overviewitem {
        global $USER, $PAGE;

        // Check the subscription status of the user and if it's changable.
        $subscribed = subscriptions::is_subscribed($USER->id, $this->moodleoverflow, $this->modulecontext);
        $changeable = subscriptions::is_subscribable($this->moodleoverflow, $this->modulecontext);

        // Build the content.
        $itemid = 'moodleoverflow-subscription-toggle-' . $this->moodleoverflow->id;
        $content = $this->render_toggle_template([
            'itemid' => $itemid,
            'checked' => $subscribed,
            'disabled' => !$changeable,
            'datatype' => 'moodleoverflow-subscription-toggle',
            'setting' => $subscribed,
        ]);

        // Add js to change subscription.
        $PAGE->requires->js_call_amd('mod_moodleoverflow/overview_toggle_item', 'init', [$itemid, "subscription"]);
        $name = get_string('subscribed', 'mod_moodleoverflow');
        return new overviewitem($name, $name, $content);
    }

    /**
     * Get overview item for readtracking. A user can choose to enable/disable readtracking for a moodleoverflow if possible.
     * @return overviewitem|null
     */
    private function get_extra_readtracking_overview(): ?overviewitem {
        global $PAGE;
        // Check if the user tracks the moodleoverflow currently.
        $tracked = readtracking::moodleoverflow_is_tracked($this->moodleoverflow);
        $changeable = $this->moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL;

        // Build the content.
        $itemid = 'moodleoverflow-readtracking-toggle-' . $this->moodleoverflow->id;
        $content = $this->render_toggle_template([
            'itemid' => $itemid,
            'checked' => $tracked,
            'disabled' => !$changeable,
            'datatype' => 'moodleoverflow-readtracking-toggle',
            'setting' => $tracked,
        ]);

        // Add js to change subscription.
        $PAGE->requires->js_call_amd('mod_moodleoverflow/overview_toggle_item', 'init', [$itemid, "readtracking"]);
        $name = get_string('trackingtype', 'mod_moodleoverflow');
        return new overviewitem($name, $name, $content);
    }

    // Helper functions.

    /**
     * Renders a toggle icon.
     * @param array $templateparams
     * @return bool|string
     * @throws moodle_exception
     */
    private function render_toggle_template(array $templateparams): bool|string {
        global $USER, $PAGE;
        $renderer = $PAGE->get_renderer('core_reportbuilder');
        return $renderer->render_from_template(
            'core/toggle',
            [
                'extraclasses' => $templateparams['datatype'],
                'id' => $templateparams['itemid'],
                'checked' => $templateparams['checked'],
                'disabled' => $templateparams['disabled'],
                'extraattributes' => [
                    ['name' => 'data-type', 'value' => $templateparams['datatype']],
                    ['name' => 'data-action', 'value' => 'toggle'],
                    ['name' => 'data-cmid', 'value' => $this->cm->id],
                    ['name' => 'data-moodleoverflowid', 'value' => $this->moodleoverflow->id],
                    ['name' => 'data-userid', 'value' => $USER->id],
                    ['name' => 'data-setting', 'value' => $templateparams['setting'] ? 'true' : 'false'],
                ],
            ]
        );
    }
}
