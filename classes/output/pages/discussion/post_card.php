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

namespace mod_moodleoverflow\output\pages\discussion;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use html_writer;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\models\post;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\review;
use moodle_url;

/**
 * Class that gathers data for the post card template used in the discussion.php.
 * This class represents a single post that in the discussion view.
 *
 * Attribute für die postcard:
 * - isfirstunread - bool CHECK
 * - postid - int CHECK
 * - postclass ? CHECK
 * - needsreview - bool
 *      - withinreviewperiod - bool
 *          - reviewdelay - string
 * - discussionby (maybe the discussionauthor?) - string
 * - showvotes - bool
 *      - postvoting template
 * - permalink - string (link)
 * - postcontent - string
 * - attachments - bool
 *      - image - bool
 *          - filepath - string
 *      - notimage (same attribute)
 *          - filepath - string
 *          - icon - string
 *          - filename - string
 * - questioner - string
 * - iscomment - bool
 *      - byname - string
 *      - byshortdate - string
 * - isnotcomment (same variable)
 *      - picture - html string
 *      - byname (post author name?) - string
 *      - showreputation - bool
 *          - showrating - bool
 *              - byuserid - int
 *              - byrating - int
 *      - bydate - string
 *      - byshortdate - string
 * - commands - string, an object??
 * - canreview - bool
 *      - needsreview - bool
 *          - withinreviewperiod - bool
 *              - review_buttons template
 *          -not withinreviewperiod
 *              - reviewdelay - int/string
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_card implements named_templatable, renderable {
    /** @var post The post that gets printed */
    public post $post;

    /** @var bool If the post is the first unread post of the discussion */
    public bool $isfirstunread;

    /** @var \context_module The context. Is given by the printing function.*/
    public \context_module $context;

    /**
     * Constructor.
     * @param post $post
     * @param bool $isfirstunread if the post is the first unread post in the discussion.
     * @param \context_module $context
     */
    public function __construct(post $post, bool $isfirstunread, \context_module $context) {
        $this->post = $post;
        $this->isfirstunread = $isfirstunread;
        $this->context = $context;
    }


    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/discussion/post_card';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER;

        // Get important variables for later checks.
        $parentpost = $this->post->moodleoverflow_get_parentpost();
        $discussion = $this->post->get_discussion();
        $moodleoverflow = $discussion->get_moodleoverflow();

        // Build the postclass, which has additional css classes that show if a post solution/helpful marks and readtracking status.
        $ratings = ratings::moodleoverflow_get_rating($this->post->get_id());
        $isread = readtracking::is_post_read($this->post->get_moodleoverflow(), $this->post->get_id(), $USER->id);
        $issolved = $ratings->issolved > 0 ? 'markedsolution' : '';
        $ishelpful = $ratings->ishelpful > 0 ? 'markedhelpful' : '';

        // Get voting data for the voting template as well as reputation rating.
        $allowdisablerating = get_config('moodleoverflow', 'allowdisablerating') == 1;
        $ratingallowed = $allowdisablerating ? $moodleoverflow->allowrating : true;
        $reputationallowed = $allowdisablerating ? $moodleoverflow->allowreputation : true;
        $ratings = $this->post->moodleoverflow_get_post_ratings();
        $userrating = ratings::moodleoverflow_user_rated($this->post->get_id());
        $ratingability = ratings::moodleoverflow_user_can_rate($this->post->get_db_object(), $this->context);

        $showvotes = $ratingallowed ? [
            'postid' => $this->post->get_id(),
            'votes' => $ratings->votesdifference,
            'userupvoted' => $userrating && $userrating->rating == RATING_UPVOTE,
            'userdownvoted' => $userrating && $userrating->rating == RATING_DOWNVOTE,
            'canchange' => $ratingability && $this->post->get_userid() != $USER->id,
        ] : [];
        $showreputation = $reputationallowed && anonymous::user_can_see_post($this->post, $USER->id) ? [
            'userid' => $this->post->get_userid(),
            'userreputation' => ratings::moodleoverflow_get_reputation($moodleoverflow->id, $this->post->get_userid()),
        ] : [];

        // Review data.
        $reviewtime = get_config('moodleoverflow', 'reviewpossibleaftertime');
        $inreviewperiod = (time() - $this->post->created) > $reviewtime;

        // Links.
        $discusspath = '/mod/moodleoverflow/discussion.php';

        // Build the mustache data.
        return (object) [
            'isfirstunread' => $this->isfirstunread,
            'postid' => $this->post->get_id(),
            'postclass' => ' ' . ($isread ? 'read' : 'unread') . ' ' . $ishelpful . ' ' . $issolved,
            'permalink' => (new moodle_url($discusspath, ['d' => $discussion->get_id()], 'p' . $this->post->get_id()))->out(),
            'postcontent' => $this->post->get_message_formatted(),
            'attachments' => $this->post->moodleoverflow_get_attachments(),
            'authorname' => $this->post->get_userlink()['fullname'],
            'authorlink' => $this->post->get_userlink()['link'],
            'authorpicture' => $this->post->get_userpicture(),
            'iscomment' => $parentpost !== null && $parentpost->get_id() != $this->post->get_discussion()->get_firstpostid(),
            'isfirstpost' => $this->post->get_id() == $this->post->get_discussion()->get_firstpostid(),
            'date' => userdate($this->post->modified),
            'shortdate' => userdate($this->post->modified, get_string('strftimedatetimeshort', 'core_langconfig')),
            'questioner' => $this->post->get_userid() == $this->post->get_discussion()->get_userid() ? 'questioner' : '',
            'showvotes' => $showvotes,
            'showreputation' => $showreputation,
            'canreview' => capabilities::has(capabilities::REVIEW_POST, $this->context),
            'needsreview' => !$this->post->reviewed ? ['withinreviewperiod' => $inreviewperiod, 'reviewdelay' => $reviewtime] : [],
            'commands' => $this->build_commands(),
        ];
    }

    /**
     * Builds the HTML string of action commands shown below a post (mark helpful/solved, edit, delete, reply).
     */
    private function build_commands(): string {
        global $USER, $OUTPUT;

        $discussion   = $this->post->get_discussion();
        $moodleoverflow = $discussion->get_moodleoverflow();
        $firstpostid  = $discussion->get_firstpostid();
        $parentpost   = $this->post->moodleoverflow_get_parentpost();

        $isroot    = $this->post->get_id() == $firstpostid;
        $isanswer  = !$isroot && $parentpost !== null && $parentpost->get_id() == $firstpostid;
        $iscomment = !$isroot && !$isanswer;

        $ownpost       = $this->post->get_userid() == $USER->id;
        $age           = time() - $this->post->created;
        $maxeditingtime = get_config('moodleoverflow', 'maxeditingtime');
        $ratings       = $this->post->moodleoverflow_get_post_ratings();

        $commands = [];

        // Mark helpful — discussion starter only, direct answers only.
        if ($isanswer && $USER->id == $discussion->get_userid() && $USER->id != $this->post->get_userid()) {
            if ($ratings->markedhelpful) {
                $label = get_string('marknothelpful', 'moodleoverflow');
            } else if (ratings::moodleoverflow_discussion_is_solved($discussion->get_id(), false)) {
                $label = get_string('alsomarkhelpful', 'moodleoverflow');
            } else {
                $label = get_string('markhelpful', 'moodleoverflow');
            }
            $commands[] = html_writer::tag('a', $label, [
                'class' => 'markhelpful onlyifreviewed',
                'role' => 'button',
                'data-moodleoverflow-action' => 'helpful',
            ]);
        }

        // Mark solved — teachers only, direct answers only.
        if ($isanswer && capabilities::has(capabilities::MARK_SOLVED, $this->context)) {
            if ($ratings->markedsolution) {
                $label = get_string('marknotsolved', 'moodleoverflow');
            } else if (ratings::moodleoverflow_discussion_is_solved($discussion->get_id(), true)) {
                $label = get_string('alsomarksolved', 'moodleoverflow');
            } else {
                $label = get_string('marksolved', 'moodleoverflow');
            }
            $commands[] = html_writer::tag('a', $label, [
                'class' => 'marksolved onlyifreviewed',
                'role' => 'button',
                'data-moodleoverflow-action' => 'solved',
            ]);
        }

        // Edit.
        $caneditown = $ownpost && $age < $maxeditingtime
            && (!review::should_post_be_reviewed($this->post->get_db_object(), $moodleoverflow) || !$this->post->reviewed);
        if ($caneditown || capabilities::has(capabilities::EDIT_ANY_POST, $this->context)) {
            $commands[] = html_writer::link(
                new moodle_url('/mod/moodleoverflow/post.php', ['edit' => $this->post->get_id()]),
                get_string('edit', 'moodleoverflow')
            );
        }

        // Delete.
        $candeleteown = $ownpost && $age < $maxeditingtime
            && capabilities::has(capabilities::DELETE_OWN_POST, $this->context);
        if ($candeleteown || capabilities::has(capabilities::DELETE_ANY_POST, $this->context)) {
            $commands[] = html_writer::link(
                new moodle_url('/mod/moodleoverflow/post.php', ['delete' => $this->post->get_id()]),
                get_string('delete', 'moodleoverflow')
            );
        }

        // Reply.
        if (moodleoverflow_user_can_post($this->context, $this->post->get_db_object(), false)) {
            if ($isroot) {
                // Check limitedanswer window.
                $hasstarttime = !empty($moodleoverflow->la_starttime);
                $hasendtime   = !empty($moodleoverflow->la_endtime);
                $islimited    = ($hasstarttime && $moodleoverflow->la_starttime > time())
                    || ($hasendtime && $moodleoverflow->la_endtime < time());

                if (($hasstarttime || $hasendtime) && $islimited) {
                    if (!has_capability('mod/moodleoverflow:addinstance', $this->context)) {
                        $helpicon  = $OUTPUT->help_icon('la_student_helpicon', 'moodleoverflow');
                        $commands[] = html_writer::tag(
                            'span',
                            html_writer::tag('span', get_string('replyfirst', 'moodleoverflow') . '    ' . $helpicon),
                            ['class' => 'onlyifreviewed text-muted']
                        );
                    } else {
                        $helpicon   = $OUTPUT->help_icon('la_teacher_helpicon', 'moodleoverflow');
                        $replyurl   = new moodle_url(
                            '/mod/moodleoverflow/post.php#mformmoodleoverflow',
                            ['reply' => $this->post->get_id()]
                        );
                        $answerlink = html_writer::link(
                            $replyurl,
                            get_string('replyfirst', 'moodleoverflow'),
                            ['class' => 'onlyifreviewed answerbutton']
                        );
                        $commands[] = html_writer::tag('span', $answerlink . '    ' . $helpicon, ['class' => 'onlyifreviewed']);
                    }
                } else {
                    $commands[] = html_writer::link(
                        new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', ['reply' => $this->post->get_id()]),
                        get_string('replyfirst', 'moodleoverflow'),
                        ['class' => 'onlyifreviewed']
                    );
                }
            } else if ($isanswer) {
                $commands[] = html_writer::link(
                    new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', ['reply' => $this->post->get_id()]),
                    get_string('reply', 'moodleoverflow'),
                    ['class' => 'onlyifreviewed']
                );
            } else {
                // Comment: reply targets the parent answer, not this comment.
                $commands[] = html_writer::link(
                    new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', ['reply' => $parentpost->get_id()]),
                    get_string('reply', 'moodleoverflow'),
                    ['class' => 'onlyifreviewed']
                );
            }
        }

        return implode('', $commands);
    }
}
