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
 * Tests for the moodleoverflow implementation of the Privacy Provider API.
 *
 * @package    mod_moodleoverflow
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
use \mod_moodleoverflow\privacy\provider;
use mod_moodleoverflow\privacy\data_export_helper;

/**
 * Tests for the moodleoverflow implementation of the Privacy Provider API.
 *
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {
    private $generator;

    /**
     * Test setUp.
     */
    public function setUp() {
        $this->resetAfterTest(true);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
    }

    /**
     * Helper to assert that the forum data is correct.
     *
     * @param   object $expected The expected data in the forum.
     * @param   object $actual   The actual data in the forum.
     */
    protected function assert_forum_data($expected, $actual) {
        // Exact matches.
        $this->assertEquals(format_string($expected->name, true), $actual->name);
    }

    /**
     * Helper to assert that the discussion data is correct.
     *
     * @param   object $expected The expected data in the discussion.
     * @param   object $actual   The actual data in the discussion.
     */
    protected function assert_discussion_data($expected, $actual, $userid) {
        // Exact matches.
        $this->assertEquals(format_string($expected->name, true), $actual->name);
        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->timemodified),
            $actual->timemodified
        );
        $this->assertEquals(
            \core_privacy\local\request\transform::yesno($expected->usermodified == $userid),
            $actual->last_modifier_was_you
        );
    }

    /**
     * Helper to assert that the post data is correct.
     *
     * @param   object                             $expected The expected data in the post.
     * @param   object                             $actual   The actual data in the post.
     * @param   \core_privacy\local\request\writer $writer   The writer used
     */
    protected function assert_post_data($expected, $actual, $writer) {
        // The message should have been passed through the rewriter.
        // Note: The testable rewrite_pluginfile_urls function in the ignores all items except the text.
        $this->assertEquals(
            $writer->rewrite_pluginfile_urls([], '', '', '', $expected->message),
            $actual->message
        );
        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->created),
            $actual->created
        );
        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->modified),
            $actual->modified
        );
    }

    /**
     * Test that a user who is enrolled in a course, but who has never
     * posted and has no other metadata stored will not have any link to
     * that context.
     */
    public function test_user_has_never_posted() {
        // Create a course with moodleoverflow forums.
        list($course, $forum) = $this->create_courses_and_modules(3);
        // Create users.
        list($user, $otheruser) = $this->create_and_enrol_users($course, 2);

        // Post to forum.
        $this->generator->post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);

        // Test that no contexts were retrieved.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $contexts = $contextlist->get_contextids();
        $this->assertCount(0, $contexts);

        // Attempting to export data for this context should return nothing either.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        // The provider should always export data for any context explicitly asked of it, but there should be no
        // metadata, files, or discussions.
        $this->assertEmpty($writer->get_data([get_string('discussions', 'mod_moodleoverflow')]));
        $this->assertEmpty($writer->get_all_metadata([]));
        $this->assertEmpty($writer->get_files([]));
    }

    /**
     * Test that a user who is enrolled in a course, and who has never
     * posted and has subscribed to the forum will have relevant
     * information returned.
     */
    public function test_user_has_never_posted_subscribed_to_forum() {
        // Create a course, with a forum, our user under test, another user, and a discussion + post from the other user.
        list($course, $forum) = $this->create_courses_and_modules(3);
        list($user, $otheruser) = $this->create_and_enrol_users($course, 2);
        list($discussion, $post) = $this->generator->post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);

        // Subscribe the user to the forum.
        \mod_moodleoverflow\subscriptions::subscribe_user($user->id, $forum, $context);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $subcontext = data_export_helper::get_subcontext($forum);
        // There should be one item of metadata.
        $this->assertCount(1, $writer->get_all_metadata($subcontext));
        // It should be the subscriptionpreference whose value is 1.
        $this->assertEquals(1, $writer->get_metadata($subcontext, 'subscriptionpreference'));
        // There should be data about the forum itself.
        $this->assertNotEmpty($writer->get_data($subcontext));
    }

    /**
     * Test that a user who is enrolled in a course, and who has never
     * posted and has subscribed to the discussion will have relevant
     * information returned.
     */
    public function test_user_has_never_posted_subscribed_to_discussion() {
        // Create a course, with a forum, our user under test, another user, and a discussion + post from the other user.
        list($course, $forum) = $this->create_courses_and_modules(3);
        // Create users.
        list($user, $otheruser) = $this->create_and_enrol_users($course, 2);

        // Post twice - only the second discussion should be included.
        $this->generator->post_to_forum($forum, $otheruser);
        list($discussion, $post) = $this->generator->post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);
        // Subscribe the user to the discussion.
        \mod_moodleoverflow\subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // There should be nothing in the forum. The user is not subscribed there.
        $forumsubcontext = data_export_helper::get_subcontext($forum);
        $this->assertCount(0, $writer->get_all_metadata($forumsubcontext));
        $this->assert_forum_data($forum, $writer->get_data($forumsubcontext));
        // There should be metadata in the discussion.
        $discsubcontext = data_export_helper::get_subcontext($forum, $discussion);
        $this->assertCount(1, $writer->get_all_metadata($discsubcontext));
        // It should be the subscriptionpreference whose value is an Integer.
        // (It's a timestamp, but it doesn't matter).
        $metadata = $writer->get_metadata($discsubcontext, 'subscriptionpreference');
        $this->assertGreaterThan(1, $metadata);
        // For context we output the discussion content.
        $data = $writer->get_data($discsubcontext);
        $this->assertInstanceOf('stdClass', $data);
        $this->assert_discussion_data($discussion, $data, $user->id);
        // Post content is not exported unless the user participated.
        $postsubcontext = data_export_helper::get_subcontext($forum, $discussion, $post);
        $this->assertCount(0, $writer->get_data($postsubcontext));
    }

    /**
     * Test that a user who has posted their own discussion will have all
     * content returned.
     */
    public function test_user_has_posted_own_discussion() {
        list($course, $forum) = $this->create_courses_and_modules(3);
        list($user, $otheruser) = $this->create_users($course, 2);
        // Post twice - only the second discussion should be included.
        list($discussion, $post) = $this->generator->post_to_forum($forum, $user);
        list($otherdiscussion, $otherpost) = $this->generator->post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->setUser($user);
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // The other discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data(data_export_helper::get_subcontext($forum, $otherdiscussion)));
        $this->assert_discussion_data($discussion,
            $writer->get_data(data_export_helper::get_subcontext($forum, $discussion)), $user->id);
        $this->assert_post_data($post, $writer->get_data(data_export_helper::get_subcontext($forum, $discussion, $post)), $writer);
    }

    /**
     * Test that a user who has posted a reply to another users discussion
     * will have all content returned.
     */
    public function test_user_has_posted_reply() {
        global $DB;
        // Create several courses and forums. We only insert data into the final one.
        list($course, $forum) = $this->create_courses_and_modules(3);
        list($user, $otheruser) = $this->create_users($course, 2);
        // Post twice - only the second discussion should be included.
        list($discussion, $post) = $this->generator->post_to_forum($forum, $otheruser);
        list($otherdiscussion, $otherpost) = $this->generator->post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);
        // Post a reply to the other person's post.
        $reply = $this->generator->reply_to_post($post, $user);
        // Testing as user $user.
        $this->setUser($user);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // Refresh the discussions.
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
        $otherdiscussion = $DB->get_record('moodleoverflow_discussions', ['id' => $otherdiscussion->id]);
        // The other discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data(data_export_helper::get_subcontext($forum, $otherdiscussion)));
        // Our discussion should have been returned as we did post in it.
        $data = $writer->get_data(data_export_helper::get_subcontext($forum, $discussion));
        $this->assertNotEmpty($data);
        $this->assert_discussion_data($discussion, $data, $user->id);
        // The reply will be included.
        $this->assert_post_data($reply,
            $writer->get_data(data_export_helper::get_subcontext($forum, $discussion, $reply)), $writer);
    }

    /**
     * Test that the rating of another users content will have only the
     * rater's information returned.
     */
    public function test_user_has_rated_others() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('moodleoverflow', [
            'course' => $course->id,
            'scale'  => 100,
        ]);
        list($user, $otheruser) = $this->create_users($course, 2);
        list($discussion, $post) = $this->generator->post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);
        // Rate the other users content.
        $rating = array();
        $rating['moodleoverflowid'] = $forum->id;
        $rating['discussionid'] = $discussion->id;
        $rating['userid'] = $user->id;
        $rating['postid'] = $post->id;
        $rating['rating'] = 1;
        $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow')->create_rating($rating);

        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // The discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data(data_export_helper::get_subcontext($forum, $discussion)));
        $ratingdata = $writer->get_related_data(data_export_helper::get_subcontext($forum, $discussion, $post), 'rating');

        $this->assertNotEmpty($ratingdata->your_rating);
        $this->assertCount(1, (array) $ratingdata);

        // The original post will not be included.
        $this->assert_post_data($post, $writer->get_data(data_export_helper::get_subcontext($forum, $discussion, $post)), $writer);
    }

    /**
     * Test that ratings of a users own content will all be returned.
     */
    public function test_user_has_been_rated() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('moodleoverflow', [
            'course' => $course->id,
            'scale'  => 100,
        ]);
        list($user, $otheruser, $anotheruser) = $this->create_users($course, 3);
        list($discussion, $post) = $this->generator->post_to_forum($forum, $user);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);
        // Other users rate my content.
        // Rate the other users content.
        $rating = array();
        $rating['moodleoverflowid'] = $forum->id;
        $rating['discussionid'] = $discussion->id;
        $rating['userid'] = $otheruser->id;
        $rating['postid'] = $post->id;
        $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow')->create_rating($rating);

        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $ratingdata = $writer->get_related_data(data_export_helper::get_subcontext($forum, $discussion, $post), 'rating');

        $this->assertEmpty((array) $ratingdata->your_rating);
        $this->assertNotNull($ratingdata->downvotes);
    }

    /**
     * Test that the per-user, per-forum user tracking data is exported.
     */
    public function test_user_tracking_data() {
        $course = $this->getDataGenerator()->create_course();
        $forumoff = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $cmoff = get_coursemodule_from_instance('moodleoverflow', $forumoff->id);
        $contextoff = \context_module::instance($cmoff->id);
        $forumon = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $cmon = get_coursemodule_from_instance('moodleoverflow', $forumon->id);
        $contexton = \context_module::instance($cmon->id);
        list($user) = $this->create_users($course, 1);
        // Set user tracking data.
        \mod_moodleoverflow\readtracking::moodleoverflow_stop_tracking($forumoff->id, $user->id);
        \mod_moodleoverflow\readtracking::moodleoverflow_start_tracking($forumon->id, $user->id);
        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - only the forum tracking reads should be included.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($contextoff, $contextlist->current());
        // Check export data for each context.
        $this->export_context_data_for_user($user->id, $contextoff, 'mod_moodleoverflow');
        $this->assertEquals(0,
            \core_privacy\local\request\writer::with_context($contextoff)->get_metadata([], 'trackreadpreference'));
    }

    /**
     * Test that the posts which a user has read are returned correctly.
     */
    public function test_user_read_posts() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $forum1 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $cm1 = get_coursemodule_from_instance('moodleoverflow', $forum1->id);
        $context1 = \context_module::instance($cm1->id);
        $forum2 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('moodleoverflow', $forum2->id);
        $context2 = \context_module::instance($cm2->id);
        $forum3 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $cm3 = get_coursemodule_from_instance('moodleoverflow', $forum3->id);
        $context3 = \context_module::instance($cm3->id);
        $forum4 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $cm4 = get_coursemodule_from_instance('moodleoverflow', $forum4->id);
        $context4 = \context_module::instance($cm4->id);
        list($author, $user) = $this->create_users($course, 2);
        list($f1d1, $f1p1) = $this->generator->post_to_forum($forum1, $author);
        $f1p1reply = $this->generator->post_to_discussion($forum1, $f1d1, $author);
        $f1d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f1d1->id]);
        list($f1d2, $f1p2) = $this->generator->post_to_forum($forum1, $author);
        list($f2d1, $f2p1) = $this->generator->post_to_forum($forum2, $author);
        $f2p1reply = $this->generator->post_to_discussion($forum2, $f2d1, $author);
        $f2d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f2d1->id]);
        list($f2d2, $f2p2) = $this->generator->post_to_forum($forum2, $author);
        list($f3d1, $f3p1) = $this->generator->post_to_forum($forum3, $author);
        $f3p1reply = $this->generator->post_to_discussion($forum3, $f3d1, $author);
        $f3d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f3d1->id]);
        list($f3d2, $f3p2) = $this->generator->post_to_forum($forum3, $author);
        list($f4d1, $f4p1) = $this->generator->post_to_forum($forum4, $author);
        $f4p1reply = $this->generator->post_to_discussion($forum4, $f4d1, $author);
        $f4d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f4d1->id]);
        list($f4d2, $f4p2) = $this->generator->post_to_forum($forum4, $author);
        // Insert read info.
        // User has read post1, but not the reply or second post in forum1.
        \mod_moodleoverflow\readtracking::moodleoverflow_add_read_record($user->id, $f1p1->id);
        // User has read post1 and its reply, but not the second post in forum2.
        \mod_moodleoverflow\readtracking::moodleoverflow_add_read_record($user->id, $f2p1->id);
        \mod_moodleoverflow\readtracking::moodleoverflow_add_read_record($user->id, $f2p1reply->id);
        // User has read post2 in forum3.
        \mod_moodleoverflow\readtracking::moodleoverflow_add_read_record($user->id, $f3p2->id);
        // Nothing has been read in forum4.
        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - should be three - forum4 has no data.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(3, $contextlist);
        $contextids = [
            $context1->id,
            $context2->id,
            $context3->id,
        ];
        sort($contextids);
        $contextlistids = $contextlist->get_contextids();
        sort($contextlistids);
        $this->assertEquals($contextids, $contextlistids);
        // Forum 1.
        $this->export_context_data_for_user($user->id, $context1, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context1);
        // User has read f1p1.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum1, $f1d1, $f1p1),
            'postread'
        );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
        // User has not f1p1reply.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum1, $f1d1, $f1p1reply),
            'postread'
        );
        $this->assertEmpty($readdata);
        // User has not f1p2.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum1, $f1d2, $f1p2),
            'postread'
        );
        $this->assertEmpty($readdata);
        // Forum 2.
        $this->export_context_data_for_user($user->id, $context2, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context2);
        // User has read f2p1.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum2, $f2d1, $f2p1),
            'postread'
        );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
        // User has read f2p1reply.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum2, $f2d1, $f2p1reply),
            'postread'
        );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
        // User has not read f2p2.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum2, $f2d2, $f2p2),
            'postread'
        );
        $this->assertEmpty($readdata);
        // Forum 3.
        $this->export_context_data_for_user($user->id, $context3, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context3);
        // User has not read f3p1.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum3, $f3d1, $f3p1),
            'postread'
        );
        $this->assertEmpty($readdata);
        // User has not read f3p1reply.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum3, $f3d1, $f3p1reply),
            'postread'
        );
        $this->assertEmpty($readdata);
        // User has read f3p2.
        $readdata = $writer->get_metadata(
            data_export_helper::get_subcontext($forum3, $f3d2, $f3p2),
            'postread'
        );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
    }

    /**
     * Test that posts with attachments have their attachments correctly exported.
     */
    public function test_post_attachment_inclusion() {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        list($author, $otheruser) = $this->create_users($course, 2);
        $forum = $this->getDataGenerator()->create_module('moodleoverflow', [
            'course' => $course->id,
            'scale'  => 100,
        ]);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);
        // Create a new discussion + post in the forum.
        list($discussion, $post) = $this->generator->post_to_forum($forum, $author);
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
        // Add a number of replies.
        $reply = $this->generator->reply_to_post($post, $author);
        $reply = $this->generator->reply_to_post($post, $author);
        $reply = $this->generator->reply_to_post($reply, $author);
        $posts[$reply->id] = $reply;
        // Add a fake inline image to the original post.
        $createdfile = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_moodleoverflow',
            'filearea'  => 'attachment',
            'itemid'    => $post->id,
            'filepath'  => '/',
            'filename'  => 'example.jpg',
        ],
            'image contents (not really)');

        // Create a second discussion + post in the forum without tags.
        list($otherdiscussion, $otherpost) = $this->generator->post_to_forum($forum, $author);
        $otherdiscussion = $DB->get_record('moodleoverflow_discussions', ['id' => $otherdiscussion->id]);
        // Add a number of replies.
        $reply = $this->generator->reply_to_post($otherpost, $author);
        $reply = $this->generator->reply_to_post($otherpost, $author);
        // Run as the user under test.
        $this->setUser($author);
        // Retrieve all contexts - should be one.
        $contextlist = $this->get_contexts_for_userid($author->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->export_context_data_for_user($author->id, $context, 'mod_moodleoverflow');
        $writer = \core_privacy\local\request\writer::with_context($context);
        // The inline file should be on the first forum post.
        $subcontext = data_export_helper::get_subcontext($forum, $discussion, $post);
        $foundfiles = $writer->get_files($subcontext);
        $this->assertCount(1, $foundfiles);
        $this->assertEquals($createdfile, reset($foundfiles));
    }

    /**
     * Ensure that all user data is deleted from a context.
     */
    public function test_all_users_deleted_from_context() {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->create_users($course, 5);
        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', [
                'course' => $course->id,
                'scale'  => 100,
            ]);
            $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
            $context = \context_module::instance($cm->id);
            $forums[$forum->id] = $forum;
            $contexts[$forum->id] = $context;
        }
        $discussions = [];
        $posts = [];
        foreach ($users as $user) {
            foreach ($forums as $forum) {
                $context = $contexts[$forum->id];
                // Create a new discussion + post in the forum.
                list($discussion, $post) = $this->generator->post_to_forum($forum, $user);
                $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
                $discussions[$discussion->id] = $discussion;
                // Add a number of replies.
                $posts[$post->id] = $post;
                $reply = $this->generator->reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $reply = $this->generator->reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $reply = $this->generator->reply_to_post($reply, $user);
                $posts[$reply->id] = $reply;
                // Add a fake inline image to the original post.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_moodleoverflow',
                    'filearea'  => 'attachment',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.jpg',
                ], 'image contents (not really)');
            }
        }
        // Mark all posts as read by user.
        $user = reset($users);
        $ratedposts = [];
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion];
            $forum = $forums[$discussion->moodleoverflow];
            $context = $contexts[$forum->id];
            // Mark the post as being read by user.
            \mod_moodleoverflow\readtracking::moodleoverflow_add_read_record($user->id, $post->id);
            // Rate the other users content.
            if ($post->userid != $user->id) {
                $ratedposts[$post->id] = $post;

                $rating = array();
                $rating['moodleoverflowid'] = $forum->id;
                $rating['discussionid'] = $discussion->id;
                $rating['userid'] = $user->id;
                $rating['postid'] = $post->id;
                $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow')->create_rating($rating);
            }
        }
        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - should be two.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(2, $contextlist);
        // These are the contexts we expect.
        $contextids = array_map(function ($context) {
            return $context->id;
        }, $contexts);
        sort($contextids);
        $contextlistids = $contextlist->get_contextids();
        sort($contextlistids);
        $this->assertEquals($contextids, $contextlistids);
        // Delete for the first forum.
        $forum = reset($forums);
        $context = $contexts[$forum->id];
        provider::delete_data_for_all_users_in_context($context);
        // Determine what should have been deleted.
        $discussionsinforum = array_filter($discussions, function ($discussion) use ($forum) {
            return $discussion->moodleoverflow == $forum->id;
        });
        $postsinforum = array_filter($posts, function ($post) use ($discussionsinforum) {
            return isset($discussionsinforum[$post->discussion]);
        });
        // All forum discussions and posts should have been deleted in this forum.
        $this->assertCount(0, $DB->get_records('moodleoverflow_discussions', ['moodleoverflow' => $forum->id]));
        list ($insql, $inparams) = $DB->get_in_or_equal(array_keys($discussionsinforum));
        $this->assertCount(0, $DB->get_records_select('moodleoverflow_posts', "discussion {$insql}", $inparams));
        // All uploaded files relating to this context should have been deleted (post content).
        foreach ($postsinforum as $post) {
            $this->assertEmpty($fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $post->id));
        }
        // All ratings should have been deleted.
        foreach ($postsinforum as $post) {
            $ratings = $DB->get_records('moodleoverflow_ratings', array('postid' => $post->id));
            $this->assertEmpty($ratings);
        }

        // Check the other forum too. It should remain intact.
        $forum = next($forums);
        $context = $contexts[$forum->id];
        // Grab the list of discussions and posts in the forum.
        $discussionsinforum = array_filter($discussions, function ($discussion) use ($forum) {
            return $discussion->moodleoverflow == $forum->id;
        });
        $postsinforum = array_filter($posts, function ($post) use ($discussionsinforum) {
            return isset($discussionsinforum[$post->discussion]);
        });
        // Forum discussions and posts should not have been deleted in this forum.
        $this->assertGreaterThan(0, $DB->count_records('moodleoverflow_discussions', ['moodleoverflow' => $forum->id]));
        list ($insql, $inparams) = $DB->get_in_or_equal(array_keys($discussionsinforum));
        $this->assertGreaterThan(0, $DB->count_records_select('moodleoverflow_posts', "discussion {$insql}", $inparams));
        // Uploaded files relating to this context should remain.
        foreach ($postsinforum as $post) {
            if ($post->parent == 0) {
                $this->assertNotEmpty($fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $post->id));
            }
        }
        // Ratings should not have been deleted.
        foreach ($postsinforum as $post) {
            if (!isset($ratedposts[$post->id])) {
                continue;
            }
            $ratings = $DB->get_records('moodleoverflow_ratings', array('postid' => $post->id));
            $this->assertNotEmpty($ratings);
        }
    }

    /**
     * Ensure that all user data is deleted for a specific context.
     */
    public function test_delete_data_for_user() {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->create_users($course, 5);
        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', [
                'course' => $course->id,
                'scale'  => 100,
            ]);
            $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
            $context = \context_module::instance($cm->id);
            $forums[$forum->id] = $forum;
            $contexts[$forum->id] = $context;
        }
        $discussions = [];
        $posts = [];
        $postsbyforum = [];
        foreach ($users as $user) {
            $postsbyforum[$user->id] = [];
            foreach ($forums as $forum) {
                $context = $contexts[$forum->id];
                // Create a new discussion + post in the forum.
                list($discussion, $post) = $this->generator->post_to_forum($forum, $user);
                $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
                $discussions[$discussion->id] = $discussion;
                $postsbyforum[$user->id][$context->id] = [];
                // Add a number of replies.
                $posts[$post->id] = $post;
                $thisforumposts[$post->id] = $post;
                $postsbyforum[$user->id][$context->id][$post->id] = $post;
                $reply = $this->generator->reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;
                $reply = $this->generator->reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;
                $reply = $this->generator->reply_to_post($reply, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;
                // Add a fake inline image to the original post.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_moodleoverflow',
                    'filearea'  => 'attachment',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.jpg',
                ], 'image contents (not really)');
            }
        }
        // Mark all posts as read by user1.
        $user1 = reset($users);
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion];
            $forum = $forums[$discussion->moodleoverflow];
            $context = $contexts[$forum->id];
            // Mark the post as being read by user1.
            \mod_moodleoverflow\readtracking::moodleoverflow_add_read_record($user1->id, $post->id);
        }
        // Rate and tag all posts.
        $ratedposts = [];
        foreach ($users as $user) {
            foreach ($posts as $post) {
                $discussion = $discussions[$post->discussion];
                $forum = $forums[$discussion->moodleoverflow];
                $context = $contexts[$forum->id];
                // Rate the other users content.
                if ($post->userid != $user->id) {
                    $ratedposts[$post->id] = $post;

                    $rating = array();
                    $rating['moodleoverflowid'] = $forum->id;
                    $rating['discussionid'] = $discussion->id;
                    $rating['userid'] = $user->id;
                    $rating['postid'] = $post->id;
                    $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow')->create_rating($rating);
                }
            }
        }
        // Delete for one of the forums for the first user.
        $firstcontext = reset($contexts);

        $deletedpostids = [];
        $otherpostids = [];
        foreach ($postsbyforum as $user => $contexts) {
            foreach ($contexts as $thiscontextid => $theseposts) {
                $thesepostids = array_map(function($post) {
                    return $post->id;
                }, $theseposts);

                if ($user == $user1->id && $thiscontextid == $firstcontext->id) {
                    // This post is in the deleted context and by the target user.
                    $deletedpostids = array_merge($deletedpostids, $thesepostids);
                } else {
                    // This post is by another user, or in a non-target context.
                    $otherpostids = array_merge($otherpostids, $thesepostids);
                }
            }
        }
        list($postinsql, $postinparams) = $DB->get_in_or_equal($deletedpostids, SQL_PARAMS_NAMED);
        list($otherpostinsql, $otherpostinparams) = $DB->get_in_or_equal($otherpostids, SQL_PARAMS_NAMED);

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user1->id),
            'mod_moodleoverflow',
            [$firstcontext->id]
        );

        provider::delete_data_for_user($approvedcontextlist);
        // All posts should remain.
        $this->assertCount(40, $DB->get_records('moodleoverflow_posts'));
        // There should be 4 posts belonging to user1 that were not deleted.
        $this->assertCount(4, $DB->get_records('moodleoverflow_posts', [
            'userid' => $user1->id,
        ]));
        // Four of those posts should have been marked as deleted.
        // That means that the user ID is null and the message is empty.
        $this->assertCount(4, $DB->get_records_select('moodleoverflow_posts', "userid = :userid"
            . " AND " . $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message')
            , [
                'userid'  => 0,
                'message' => '',
            ]));

        // Only user1's posts should have been marked this way.
        $this->assertCount(4, $DB->get_records('moodleoverflow_posts', [
            'userid' => 0,
        ]));
        $this->assertCount(4, $DB->get_records_select('moodleoverflow_posts',
            $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message'), [
                'message' => '',
            ]));
        // Only the posts in the first discussion should have been marked this way.
        $this->assertCount(4, $DB->get_records_select('moodleoverflow_posts',
            "userid = :userid AND id {$postinsql}",
            array_merge($postinparams, [
                'userid' => 0,
            ])
        ));

        // Ratings should have been anonymized.
        $this->assertCount(16, $DB->get_records('moodleoverflow_ratings', array('userid' => 0)));

        // File count: (5 users * 2 forums * 1 file) = 10.
        // Files for the affected posts should be removed.
        $this->assertCount(0, $DB->get_records_select('files', "itemid {$postinsql}", $postinparams));
        // Files for the other posts should remain.
        $this->assertCount(9, $DB->get_records_select('files', "filename <> '.' AND itemid {$otherpostinsql}", $otherpostinparams));
    }

    protected function create_courses_and_modules($count) {
        $course = null;
        $forum = null;
        for ($i = 0; $i < $count; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        }

        return array($course, $forum);
    }

    /**
     * Helper to create the required number of users in the specified
     * course.
     * Users are enrolled as students.
     *
     * @param stdClass $course The course object
     * @param integer  $count  The number of users to create
     *
     * @return array The users created
     */
    protected function create_users($course, $count) {
        $users = array();
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $users[] = $user;
        }

        return $users;
    }

    protected function create_and_enrol_users($course, $count) {
        $users = array();
        for ($i = 0; $i < $count; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($users[$i]->id, $course->id);
        }

        return $users;
    }
}