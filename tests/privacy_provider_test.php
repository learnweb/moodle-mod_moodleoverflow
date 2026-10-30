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
namespace mod_moodleoverflow;

use core\exception\coding_exception;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\local\service\readtracking;
use mod_moodleoverflow\privacy\data_export_helper;
use mod_moodleoverflow\privacy\provider;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the moodleoverflow implementation of the Privacy Provider API.
 *
 * @copyright  2018 Tamara Gunkel
 * @package   mod_moodleoverflow
 * @group mod_moodleoverflow
 * @group mod_moodleoverflow_privacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\privacy\provider
 */
#[Group('mod_moodleoverflow')]
#[Group('mod_moodleoverflow_privacy')]
#[CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    use moodleoverflow_test_trait;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
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
     * @param object $expected  The expected data in the discussion.
     * @param object $actual    The actual data in the discussion.
     * @param int $userid       The user whose data is exported.
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
     * @param   writer $writer   The writer used
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
     *
     * @return void
     * @throws coding_exception
     */
    public function test_user_has_never_posted(): void {
        // Create a course with moodleoverflow forums.
        [$course, $forum] = $this->create_courses_and_modules(3);
        // Create users.
        [$user, $otheruser] = $this->create_students(2, $course);

        // Post to forum.
        $this->generator->post_to_forum($forum, $otheruser);
        $context = \context_module::instance($forum->cmid);

        // Test that no contexts were retrieved.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $contexts = $contextlist->get_contextids();
        $this->assertCount(0, $contexts);

        // Attempting to export data for this context should return nothing either.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
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
    public function test_user_has_never_posted_subscribed_to_forum(): void {
        // Create a course, with a forum, our user under test, another user, and a discussion + post from the other user.
        [$course, $forum] = $this->create_courses_and_modules(3);
        [$user, $otheruser] = $this->create_students(2, $course);
        $this->generator->post_to_forum($forum, $otheruser);
        $context = \context_module::instance($forum->cmid);

        // Subscribe the user to the forum.
        service\subscription::subscribe(moodleoverflow::from_id($forum->id), $user->id);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $subcontext = data_export_helper::get_subcontext();
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
    public function test_user_has_never_posted_subscribed_to_discussion(): void {
        // Create a course, with a forum, our user under test, another user, and a discussion + post from the other user.
        [$course, $forum] = $this->create_courses_and_modules(3);
        // Create users.
        [$user, $otheruser] = $this->create_students(2, $course);

        // Post twice - only the second discussion should be included.
        $this->generator->post_to_forum($forum, $otheruser);
        [$discussion, $post] = $this->generator->post_to_forum($forum, $otheruser);
        $context = \context_module::instance($forum->cmid);
        // Subscribe the user to the discussion.
        service\subscription::subscribe_discussion(discussion::from_id($discussion->id), $user->id);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // There should be nothing in the forum. The user is not subscribed there.
        $forumsubcontext = data_export_helper::get_subcontext();
        $this->assertCount(0, $writer->get_all_metadata($forumsubcontext));
        $this->assert_forum_data($forum, $writer->get_data($forumsubcontext));
        // There should be metadata in the discussion.
        $discsubcontext = data_export_helper::get_subcontext($discussion);
        $this->assertCount(1, $writer->get_all_metadata($discsubcontext));
        // It should be the subscriptionpreference whose value is an Integer.
        $metadata = $writer->get_metadata($discsubcontext, 'subscriptionpreference');
        $this->assertEquals(service\subscription::OPTIN, $metadata);
        // For context we output the discussion content.
        $data = $writer->get_data($discsubcontext);
        $this->assertInstanceOf('stdClass', $data);
        $this->assert_discussion_data($discussion, $data, $user->id);
        // Post content is not exported unless the user participated.
        $postsubcontext = data_export_helper::get_subcontext($discussion, $post);
        $this->assertCount(0, $writer->get_data($postsubcontext));
    }

    /**
     * Test that a user who has posted their own discussion will have all
     * content returned.
     */
    public function test_user_has_posted_own_discussion(): void {
        [$course, $forum] = $this->create_courses_and_modules(3);
        [$user, $otheruser] = $this->create_students(2, $course);
        // Post twice - only the second discussion should be included.
        [$discussion, $post] = $this->generator->post_to_forum($forum, $user);
        [$otherdiscussion] = $this->generator->post_to_forum($forum, $otheruser);
        $context = \context_module::instance($forum->cmid);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->setUser($user);
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // The other discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data(data_export_helper::get_subcontext($otherdiscussion)));
        $this->assert_discussion_data(
            $discussion,
            $writer->get_data(data_export_helper::get_subcontext($discussion)),
            $user->id
        );
        $this->assert_post_data($post, $writer->get_data(data_export_helper::get_subcontext($discussion, $post)), $writer);
    }

    /**
     * Test that a user who has posted a reply to another users discussion
     * will have all content returned.
     */
    public function test_user_has_posted_reply(): void {
        global $DB;
        // Create several courses and forums. We only insert data into the final one.
        [$course, $forum] = $this->create_courses_and_modules(3);
        [$user, $otheruser] = $this->create_students(2, $course);
        // Post twice - only the second discussion should be included.
        [$discussion, $post] = $this->generator->post_to_forum($forum, $otheruser);
        [$otherdiscussion] = $this->generator->post_to_forum($forum, $otheruser);
        $context = \context_module::instance($forum->cmid);
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
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // Refresh the discussions.
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
        $otherdiscussion = $DB->get_record('moodleoverflow_discussions', ['id' => $otherdiscussion->id]);
        // The other discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data(data_export_helper::get_subcontext($otherdiscussion)));
        // Our discussion should have been returned as we did post in it.
        $data = $writer->get_data(data_export_helper::get_subcontext($discussion));
        $this->assertNotEmpty($data);
        $this->assert_discussion_data($discussion, $data, $user->id);
        // The reply will be included.
        $this->assert_post_data($reply, $writer->get_data(data_export_helper::get_subcontext($discussion, $reply)), $writer);
    }

    /**
     * Test that the rating of another users content will have only the
     * rater's information returned.
     */
    public function test_user_has_rated_others(): void {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id, 'scale' => 100]);
        [$user, $otheruser] = $this->create_students(2, $course);
        [$discussion, $post] = $this->generator->post_to_forum($forum, $otheruser);
        $context = \context_module::instance($forum->cmid);
        // Rate the other users content.
        $this->generator->create_rating(post::from_id($post->id), $user, rating_type::DOWNVOTE);

        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        // The discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data(data_export_helper::get_subcontext($discussion)));
        $ratingdata = $writer->get_related_data(data_export_helper::get_subcontext($discussion, $post), 'rating');

        $this->assertNotEmpty($ratingdata->your_rating);
        $this->assertCount(1, (array) $ratingdata);

        // The original post will not be included.
        $this->assert_post_data($post, $writer->get_data(data_export_helper::get_subcontext($discussion, $post)), $writer);
    }

    /**
     * Test that ratings of a users own content will all be returned.
     */
    public function test_user_has_been_rated(): void {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('moodleoverflow', [ 'course' => $course->id, 'scale' => 100]);
        [$user, $otheruser] = $this->create_students(3, $course);
        [$discussion, $post] = $this->generator->post_to_forum($forum, $user);
        $context = \context_module::instance($forum->cmid);
        // Other users rate my content.
        // Rate the other users content.
        $this->generator->create_rating(post::from_id($post->id), $otheruser, rating_type::DOWNVOTE);

        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $ratingdata = $writer->get_related_data(data_export_helper::get_subcontext($discussion, $post), 'rating');

        $this->assertEmpty((array) $ratingdata->your_rating);
        $this->assertNotNull($ratingdata->downvotes);
    }

    /**
     * Test that the per-user, per-forum user tracking data is exported.
     */
    public function test_user_tracking_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $forumoff = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $contextoff = \context_module::instance($forumoff->cmid);
        $forumon = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        [$user] = $this->create_students(1, $course);
        // Set user tracking data.
        readtracking::stop_tracking(moodleoverflow::from_record($forumoff), $user->id);
        readtracking::start_tracking(moodleoverflow::from_record($forumon), $user->id);
        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - only the forum tracking reads should be included.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($contextoff, $contextlist->current());
        // Check export data for each context.
        $this->export_context_data_for_user($user->id, $contextoff, 'mod_moodleoverflow');
        $this->assertEquals(0, writer::with_context($contextoff)->get_metadata([], 'trackreadpreference'));
    }

    /**
     * Test that the posts which a user has read are returned correctly.
     */
    public function test_user_read_posts(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $forum1 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context1 = \context_module::instance($forum1->cmid);
        $forum2 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context2 = \context_module::instance($forum2->cmid);
        $forum3 = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context3 = \context_module::instance($forum3->cmid);
        [$author, $user] = $this->create_students(2, $course);
        [$f1d1, $f1p1] = $this->generator->post_to_forum($forum1, $author);
        $f1p1reply = $this->generator->reply_to_post($f1p1, $author);
        $f1d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f1d1->id]);
        [$f1d2, $f1p2] = $this->generator->post_to_forum($forum1, $author);
        [$f2d1, $f2p1] = $this->generator->post_to_forum($forum2, $author);
        $f2p1reply = $this->generator->reply_to_post($f2p1, $author);
        $f2d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f2d1->id]);
        [$f2d2, $f2p2] = $this->generator->post_to_forum($forum2, $author);
        [$f3d1, $f3p1] = $this->generator->post_to_forum($forum3, $author);
        $f3p1reply = $this->generator->reply_to_post($f3p1, $author);
        $f3d1 = $DB->get_record('moodleoverflow_discussions', ['id' => $f3d1->id]);
        [$f3d2, $f3p2] = $this->generator->post_to_forum($forum3, $author);

        // Insert read info.
        // User has read post1, but not the reply or second post in forum1.
        readtracking::mark_post_read(post::from_record($f1p1), $user->id);

        // User has read post1 and its reply, but not the second post in forum2.
        readtracking::mark_post_read(post::from_record($f2p1), $user->id);
        readtracking::mark_post_read(post::from_record($f2p1reply), $user->id);

        // User has read post2 in forum3.
        readtracking::mark_post_read(post::from_record($f3p2), $user->id);
        // Nothing has been read in forum4.
        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - should be three - forum4 has no data.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_moodleoverflow');
        $this->assertCount(3, $contextlist);
        $contextids = [$context1->id, $context2->id, $context3->id];
        sort($contextids);
        $contextlistids = $contextlist->get_contextids();
        sort($contextlistids);
        $this->assertEquals($contextids, $contextlistids);
        // Forum 1.
        $this->export_context_data_for_user($user->id, $context1, 'mod_moodleoverflow');
        $writer = writer::with_context($context1);
        // User has read f1p1.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f1d1, $f1p1), 'postread');
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
        // User has not f1p1reply.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f1d1, $f1p1reply), 'postread');
        $this->assertEmpty($readdata);
        // User has not f1p2.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f1d2, $f1p2), 'postread');
        $this->assertEmpty($readdata);
        // Forum 2.
        $this->export_context_data_for_user($user->id, $context2, 'mod_moodleoverflow');
        $writer = writer::with_context($context2);
        // User has read f2p1.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f2d1, $f2p1), 'postread');
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
        // User has read f2p1reply.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f2d1, $f2p1reply), 'postread');
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
        // User has not read f2p2.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f2d2, $f2p2), 'postread');
        $this->assertEmpty($readdata);
        // Forum 3.
        $this->export_context_data_for_user($user->id, $context3, 'mod_moodleoverflow');
        $writer = writer::with_context($context3);
        // User has not read f3p1.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f3d1, $f3p1), 'postread');
        $this->assertEmpty($readdata);
        // User has not read f3p1reply.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f3d1, $f3p1reply), 'postread');
        $this->assertEmpty($readdata);
        // User has read f3p2.
        $readdata = $writer->get_metadata(data_export_helper::get_subcontext($f3d2, $f3p2), 'postread');
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));
    }

    /**
     * Test that posts with attachments have their attachments correctly exported.
     */
    public function test_post_attachment_inclusion(): void {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        [$author] = $this->create_students(2, $course);
        $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id, 'scale' => 100]);
        $context = \context_module::instance($forum->cmid);
        // Create a new discussion + post in the forum.
        [$discussion, $post] = $this->generator->post_to_forum($forum, $author);
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
        // Add a number of replies.
        $this->generator->reply_to_post($post, $author);
        $reply = $this->generator->reply_to_post($post, $author);
        $this->generator->reply_to_post($reply, $author);
        // Add a fake inline image to the original post.
        $createdfile = $fs->create_file_from_string(
            ['contextid' => $context->id, 'component' => 'mod_moodleoverflow',
                               'filearea' => 'attachment', 'itemid' => $post->id, 'filepath' => '/', 'filename' => 'example.jpg', ],
            'image contents (not really)'
        );

        // Create a second discussion + post in the forum without tags.
        [, $otherpost] = $this->generator->post_to_forum($forum, $author);
        // Add a number of replies.
        $this->generator->reply_to_post($otherpost, $author);
        $this->generator->reply_to_post($otherpost, $author);
        // Run as the user under test.
        $this->setUser($author);
        // Retrieve all contexts - should be one.
        $contextlist = $this->get_contexts_for_userid($author->id, 'mod_moodleoverflow');
        $this->assertCount(1, $contextlist);
        $this->export_context_data_for_user($author->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        // The inline file should be on the first forum post.
        $subcontext = data_export_helper::get_subcontext($discussion, $post);
        $foundfiles = $writer->get_files($subcontext);
        $this->assertCount(1, $foundfiles);
        $this->assertEquals($createdfile, reset($foundfiles));
    }

    /**
     * Ensure that all user data is deleted from a context.
     *
     * @return void
     */
    public function test_all_users_deleted_from_context(): void {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->create_students(5, $course);
        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id, 'scale' => 100]);
            $context = \context_module::instance($forum->cmid);
            $forums[$forum->id] = $forum;
            $contexts[$forum->id] = $context;
        }
        $discussions = [];
        $posts = [];
        foreach ($users as $user) {
            foreach ($forums as $forum) {
                $context = $contexts[$forum->id];
                // Create a new discussion + post in the forum.
                [$discussion, $post] = $this->generator->post_to_forum($forum, $user);
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
                $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_moodleoverflow',
                                              'filearea' => 'attachment', 'itemid' => $post->id, 'filepath' => '/',
                                              'filename' => 'example.jpg', ], 'image contents (not really)');
            }
        }
        // Mark all posts as read by user.
        $user = reset($users);
        $ratedposts = [];
        foreach ($posts as $post) {
            // Mark the post as being read by user.
            readtracking::mark_post_read(post::from_record($post), $user->id);
            // Rate the other users content.
            if ($post->userid != $user->id) {
                $ratedposts[$post->id] = $post;
                $this->generator->create_rating(post::from_id($post->id), $user, rating_type::DOWNVOTE);
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
         [$insql, $inparams] = $DB->get_in_or_equal(array_keys($discussionsinforum));
        $this->assertCount(0, $DB->get_records_select('moodleoverflow_posts', "discussion {$insql}", $inparams));
        // All uploaded files relating to this context should have been deleted (post content).
        foreach ($postsinforum as $post) {
            $this->assertEmpty($fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $post->id));
        }
        // All ratings should have been deleted.
        foreach ($postsinforum as $post) {
            $ratings = $DB->get_records('moodleoverflow_ratings', ['postid' => $post->id]);
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
         [$insql, $inparams] = $DB->get_in_or_equal(array_keys($discussionsinforum));
        $this->assertGreaterThan(0, $DB->count_records_select('moodleoverflow_posts', "discussion {$insql}", $inparams));
        // Uploaded files relating to this context should remain.
        foreach ($postsinforum as $post) {
            if ($post->parent == 0) {
                $this->assertNotEmpty($fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $post->id));
            }
        }
        // Ratings should not have been deleted.
        foreach ($postsinforum as $post) {
            if (isset($ratedposts[$post->id])) {
                $ratings = $DB->get_records('moodleoverflow_ratings', ['postid' => $post->id]);
                $this->assertNotEmpty($ratings);
            }
        }
    }

    /**
     * Ensure that all user data is deleted for a specific context.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->create_students(5, $course);
        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id, 'scale' => 100]);
            $context = \context_module::instance($forum->cmid);
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
                [$discussion, $post] = $this->generator->post_to_forum($forum, $user);
                $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);
                $discussions[$discussion->id] = $discussion;
                $postsbyforum[$user->id][$context->id] = [];
                // Add a number of replies.
                $posts[$post->id] = $post;
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
                    'filearea' => 'attachment',
                    'itemid' => $post->id,
                    'filepath' => '/',
                    'filename' => 'example.jpg',
                ], 'image contents (not really)');
            }
        }
        // Mark all posts as read by user1.
        $user1 = reset($users);
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion];
            $forum = $forums[$discussion->moodleoverflow];
            // Mark the post as being read by user1.
            readtracking::mark_post_read(post::from_record($post), $user1->id);
        }
        // Rate and tag all posts.
        foreach ($users as $user) {
            foreach ($posts as $post) {
                // Rate the other users content.
                if ($post->userid != $user->id) {
                    $this->generator->create_rating(post::from_id($post->id), $user, rating_type::DOWNVOTE);
                }
            }
        }
        // Delete for one of the forums for the first user.
        $firstcontext = reset($contexts);

        $deletedpostids = [];
        $otherpostids = [];
        foreach ($postsbyforum as $user => $contexts) {
            foreach ($contexts as $thiscontextid => $theseposts) {
                $thesepostids = array_map(function ($post) {
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
        [$postinsql, $postinparams] = $DB->get_in_or_equal($deletedpostids, SQL_PARAMS_NAMED);
        [$otherpostinsql, $otherpostinparams] = $DB->get_in_or_equal($otherpostids, SQL_PARAMS_NAMED);

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user1->id),
            'mod_moodleoverflow',
            [$firstcontext->id]
        );

        provider::delete_data_for_user($approvedcontextlist);
        // All posts should remain.
        $this->assertCount(40, $DB->get_records('moodleoverflow_posts'));
        // There should be 4 posts belonging to user1 that were not deleted.
        $this->assertCount(4, $DB->get_records('moodleoverflow_posts', ['userid' => $user1->id]));
        // Four of those posts should have been marked as deleted.
        // That means that the user ID is null and the message is empty.
        $this->assertCount(4, $DB->get_records_select('moodleoverflow_posts', "userid = :userid"
            . " AND " . $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message'), [
                'userid' => 0,
                'message' => '',
            ]));

        // Only user1's posts should have been marked this way.
        $this->assertCount(4, $DB->get_records('moodleoverflow_posts', ['userid' => 0]));
        $this->assertCount(4, $DB->get_records_select(
            'moodleoverflow_posts',
            $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message'),
            ['message' => '']
        ));

        // Only the posts in the first discussion should have been marked this way.
        $this->assertCount(4, $DB->get_records_select(
            'moodleoverflow_posts',
            "userid = :userid AND id {$postinsql}",
            array_merge($postinparams, ['userid' => 0])
        ));

        // Ratings should have been anonymized.
        $this->assertCount(16, $DB->get_records('moodleoverflow_ratings', ['userid' => 0]));

        // File count: (5 users * 2 forums * 1 file) = 10.
        // Files for the affected posts should be removed.
        $this->assertCount(0, $DB->get_records_select('files', "itemid {$postinsql}", $postinparams));
        // Files for the other posts should remain.
        $this->assertCount(9, $DB->get_records_select('files', "filename <> '.' AND itemid {$otherpostinsql}", $otherpostinparams));
    }

    /**
     * Create courses and a moodleoverflow module for each course.
     *
     * @param int $count The number how many courses/modules should be created.
     *
     * @return array The last course and module which were created.
     */
    protected function create_courses_and_modules($count) {
        $course = null;
        $forum = null;
        for ($i = 0; $i < $count; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        }

        return [$course, $forum];
    }



    /**
     * Ensure that user data for specific users is deleted from a specified context.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        // Creates 5 users.
        $users = $this->create_students(5, $course);

        $moodleoverflows = [];
        $contexts = [];
        // Creates two Moodleoverflows.
        for ($i = 0; $i < 2; $i++) {
            $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', [
                'course' => $course->id,
                'scale' => 100,
            ]);
            $context = \context_module::instance($moodleoverflow->cmid);
            $moodleoverflows[$moodleoverflow->id] = $moodleoverflow;
            $contexts[$moodleoverflow->id] = $context;
        }

        $discussions = [];
        $posts = [];
        $postsbyforum = [];
        // Foreach of the 5 users and foreach of the two Moodleoverflows a post and discussion is created.
        // Additionally, 3 replies to each discussion are created.
        // Last but not least the original post gets a fake image and attachment.
        foreach ($users as $user) {
            $postsbyforum[$user->id] = [];
            foreach ($moodleoverflows as $moodleoverflow) {
                $context = $contexts[$moodleoverflow->id];

                // Create a new discussion + post in the moodleoverflow.
                [$discussion, $post] = $this->generator->post_to_forum($moodleoverflow, $user);
                $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->id]);

                $discussions[$discussion->id] = $discussion;
                $postsbyforum[$user->id][$context->id] = [];

                // Add a number of replies.
                $posts[$post->id] = $post;
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
                    'filearea' => 'post',
                    'itemid' => $post->id,
                    'filepath' => '/',
                    'filename' => 'example.jpg',
                ], 'image contents (not really)');
                // And a fake attachment.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_moodleoverflow',
                    'filearea' => 'attachment',
                    'itemid' => $post->id,
                    'filepath' => '/',
                    'filename' => 'example.jpg',
                ], 'image contents (not really)');
            }
        }

        // Mark all posts as read by user1.
        $user1 = reset($users);
        foreach ($posts as $post) {
            // Mark the post as being read by user1.
            readtracking::mark_post_read(post::from_record($post), $user1->id);
        }

        // Rate all posts (Every user every post).
        foreach ($users as $user) {
            foreach ($posts as $post) {
                $discussion = $discussions[$post->discussion];
                $moodleoverflow = $moodleoverflows[$discussion->moodleoverflow];

                if ($post->userid != $user->id) {
                    $time = time();
                    $rating = (object) ['userid' => $user->id, 'postid' => $post->id, 'discussionid' => $discussion->id,
                                        'moodleoverflowid' => $moodleoverflow->id, 'rating' => 1, 'firstrated' => $time,
                                        'lastchanged' => $time, ];
                    // Inserts a rating into the table.
                    $DB->insert_record('moodleoverflow_ratings', $rating);
                }
            }
        }

        // Delete for one of the moodleoverflows all post for the first user.
        $firstcontext = reset($contexts);

        $approveduserlist = new approved_userlist($firstcontext, 'mod_moodleoverflow', [$user1->id]);
        provider::delete_data_for_users($approveduserlist);

        // All posts should remain (5 user * 2 Moodleoverflows * 4 Post per Moodleoverflow).
        $this->assertCount(40, $DB->get_records('moodleoverflow_posts'));

        // User 1 posted in every Moodleoverflow 4 post (8).
        // Post from the first Moodleoverflow are made anonymous.
        $this->assertCount(4, $DB->get_records('moodleoverflow_posts', ['userid' => $user1->id]));

        // All of the post from moodleoverflow 1 should have been filed with empty values.
        // That means the userid equals 0, message equals empty string, and message format equals FORMAT_PLAIN.
        $this->assertCount(4, $DB->get_records_select('moodleoverflow_posts', "userid = :userid"
            . " AND " . $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message')
            . " AND " . $DB->sql_compare_text('messageformat') . " = " . $DB->sql_compare_text(':messageformat'), [
                'userid' => 0,
                'messageformat' => FORMAT_PLAIN,
                'message' => '',
            ]));

        // Ratings were made anonymous from the affected posts.
        // All ratings from user1 in Moodleoverflow1 should have been deleted so 16 entries should be anonymous.
        // (20 post in Moodleoverflow -4 post belonging to user 1).
        $this->assertCount(16, $DB->get_records_select('moodleoverflow_ratings', "userid = :userid", ['userid' => 0 ]));
        // All ratings should still exist (2 Moodleoverflows * 5 users * 16 post from other people).
        $this->assertCount(160, $DB->get_records('moodleoverflow_ratings'));

        // All discussions from user1 should be made anonymous (onyl one discussion created).
        // We stil have an entry for all 10 discussions (2 moodleoverflows * 1 discussion * 5 users).
        $this->assertCount(10, $DB->get_records('moodleoverflow_discussions'));

        // User 1 posted in every Moodleoverflow 1 discussion.
        // Discussion from the first Moodleoverflow are made anonymous.
        $this->assertCount(1, $DB->get_records('moodleoverflow_discussions', ['userid' => $user1->id]));

        // All of the discussions from moodleoverflow 1 should have been filed with empty values.
        // That means the userid equals 0, and name equals empty string.
        $this->assertCount(1, $DB->get_records_select('moodleoverflow_discussions', "userid = :userid"
            . " AND " . $DB->sql_compare_text('name') . " = " . $DB->sql_compare_text(':name'), [
                'userid' => 0,
                'name' => '',
            ]));

        // Get all the post ids where files should be deleted.
        $deletedpostids = [];
        $otherpostids = [];
        foreach ($postsbyforum as $user => $contexts) {
            foreach ($contexts as $thiscontextid => $theseposts) {
                $thesepostids = array_map(function ($post) {
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
        [$postinsql, $postinparams] = $DB->get_in_or_equal($deletedpostids, SQL_PARAMS_NAMED);
        [$otherpostinsql, $otherpostinparams] = $DB->get_in_or_equal($otherpostids, SQL_PARAMS_NAMED);

        // For each context 20 files are created.
        // 2 are from the critical user in the critical context, which are deleted.
        $this->assertCount(0, $DB->get_records_select('files', "itemid {$postinsql}", $postinparams));
        // All other files still exist.
        $this->assertCount(18, $DB->get_records_select(
            'files',
            "filename <> '.' AND itemid {$otherpostinsql}",
            $otherpostinparams
        ));
    }

    /**
     * Ensure that the discussion author is listed as a user in the context.
     */
    public function test_get_users_in_context_post_author(): void {
        $course = $this->getDataGenerator()->create_course();

        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context = \context_module::instance($moodleoverflow->cmid);

        [$author] = $this->create_students(2, $course);

        $this->generator->post_to_forum($moodleoverflow, $author);

        $userlist = new userlist($context, 'moodleoverflow');
        provider::get_users_in_context($userlist);

        // There should only be one user in the list.
        $this->assertCount(1, $userlist);
        $this->assertEquals([$author->id], $userlist->get_userids());
    }

    /**
     * Ensure that all post authors are included as a user in the context.
     */
    public function test_get_users_in_context_post_authors(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();

        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context = \context_module::instance($moodleoverflow->cmid);

        [$author, $user] = $this->create_students(3, $course);

        [$fd1, $fp1] = $this->generator->post_to_forum($moodleoverflow, $author);
        $this->generator->reply_to_post($fp1, $user);
        $DB->get_record('moodleoverflow_discussions', ['id' => $fd1->id]);

        $userlist = new userlist($context, 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // Two users - author and replier.
        $this->assertCount(2, $userlist);
        $expected = [$author->id, $user->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));
    }

    /**
     * Ensure that all post raters are included as a user in the context --> this is different from the forum ratings,
     * since ratings in moodle overflow are saved in a separate table
     */
    public function test_get_users_in_context_post_ratings(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();

        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context = \context_module::instance($moodleoverflow->cmid);
        [$author, $user] = $this->create_students(3, $course);
        [$fd1, $fp1] = $this->generator->post_to_forum($moodleoverflow, $author);
        $time = time();
        $rating = (object) ['userid' => $user->id, 'postid' => $fp1->id, 'discussionid' => $fd1->id,
                            'moodleoverflowid' => $moodleoverflow->id, 'rating' => 1, 'firstrated' => $time,
                            'lastchanged' => $time, ];

        // Inserts a rating into the table.
        $DB->insert_record('moodleoverflow_ratings', $rating);
        $userlist = new userlist($context, 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // Two users - author and rater.
        $this->assertCount(2, $userlist);
        $expected = [$author->id, $user->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));
    }

    /**
     * Ensure that all users with a moodleoverflow subscription preference included as a user in the context.
     */
    public function test_get_users_in_context_with_subscription(): void {
        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context = \context_module::instance($moodleoverflow->cmid);
        [$user] = $this->create_students(2, $course);

        // Subscribe the user to the moodleoverflow.
        service\subscription::subscribe(moodleoverflow::from_id($moodleoverflow->id), $user->id);
        $userlist = new userlist($context, 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // One user - the one with a digest preference.
        $this->assertCount(1, $userlist);
        $expected = [$user->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));
    }

    /**
     * Ensure that all users with a per-discussion subscription preference included as a user in the context.
     */
    public function test_get_users_in_context_with_discussion_subscription(): void {
        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $context = \context_module::instance($moodleoverflow->cmid);
        $othermoodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        [$author, $user, $otheruser] = $this->create_students(3, $course);

        // Post in both of the moodleoverflows.
        [$fd1] = $this->generator->post_to_forum($moodleoverflow, $author);
        [$ofd1] = $this->generator->post_to_forum($othermoodleoverflow, $author);

        // Subscribe the user to the discussions.
        service\subscription::subscribe_discussion(discussion::from_id($fd1->id), $user->id);
        service\subscription::subscribe_discussion(discussion::from_id($ofd1->id), $otheruser->id);

        $userlist = new userlist($context, 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // Two users - the author, and the one who subscribed.
        $this->assertCount(2, $userlist);
        $expected = [$author->id, $user->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));
    }

    /**
     * Ensure that all users with read tracking are included as a user in the context.
     */
    public function test_get_users_in_context_with_read_post_tracking(): void {
        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);

        $othermoodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        [$author, $user, $otheruser] = $this->create_students(3, $course);

        // Post in both of the moodleoverflows.
        [, $fp1] = $this->generator->post_to_forum($moodleoverflow, $author);
        [, $ofp1] = $this->generator->post_to_forum($othermoodleoverflow, $author);

        // Add read information for those users.
        readtracking::mark_post_read(post::from_record($fp1), $user->id);
        readtracking::mark_post_read(post::from_record($ofp1), $otheruser->id);

        $userlist = new userlist(\context_module::instance($moodleoverflow->cmid), 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // Two user - the author, and the one who has read the post.
        $this->assertCount(2, $userlist);
        $expected = [$author->id, $user->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));

        // Testing again for the other context.
        $userlist = new userlist(\context_module::instance($othermoodleoverflow->cmid), 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // Two user - the author, and the one who has read the post.
        $this->assertCount(2, $userlist);
        $expected = [$author->id, $otheruser->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));
    }

    /**
     * Ensure that all users with tracking preferences are included as a user in the context.
     */
    public function test_get_users_in_context_with_tracking_preferences(): void {
        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);

        $othermoodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
        $othercontext = \context_module::instance($othermoodleoverflow->cmid);
        [, $user, $otheruser] = $this->create_students(3, $course);

        // Stop tracking the read posts.
        readtracking::stop_tracking(moodleoverflow::from_record($moodleoverflow), $user->id);
        readtracking::stop_tracking(moodleoverflow::from_record($othermoodleoverflow), $otheruser->id);

        $userlist = new userlist(\context_module::instance($moodleoverflow->cmid), 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // One user - the one who is tracking that moodleoverflow.
        $this->assertCount(1, $userlist);

        $expected = [$user->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));

        // Testing the other context.
        $userlist = new userlist($othercontext, 'mod_moodleoverflow');
        provider::get_users_in_context($userlist);

        // One user - the one who is tracking that moodleoverflow.
        $this->assertCount(1, $userlist);
        $expected = [$otheruser->id];
        $actual = $userlist->get_userids();
        $this->assertEquals(sort($expected), sort($actual));
    }

    /**
     * Tests deletion of Grades
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_grades(): void {
        global $DB;

        $course = self::getDataGenerator()->create_course();
        $forum = self::getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id, 'scale' => 100,
                            'grademaxgrade' => 50, 'gradescalefactor' => 2, ]);
        $forum = moodleoverflow::from_id($forum->id);
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);
        $context = \context_module::instance($cm->id);

        [$user, $user2] = $this->create_students(2, $course);
        [ , $post] = $this->generator->post_to_forum($forum, $user);
        service\rating::vote(post::from_id($post->id), $user2->id, rating_type::UPVOTE);
        service\rating::update_all_grades($forum);
        $grades = grade_get_grades($course->id, 'mod', 'moodleoverflow', $forum->id, [$user->id, $user2->id]);
        self::assertEquals("2.50", $grades->items[0]->grades[$user->id]->str_grade);
        self::assertEquals("0.50", $grades->items[0]->grades[$user2->id]->str_grade);

        // Test export.
        $this->export_context_data_for_user($user->id, $context, 'mod_moodleoverflow');
        $writer = writer::with_context($context);
        $metadata = $writer->get_all_metadata();
        self::assertArrayHasKey('grade', $metadata);
        self::assertEquals(2.5, $metadata['grade']->value);

        // Test delete for user.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $contextlist = new approved_contextlist($user2, 'mod_moodleoverflow', $contextlist->get_contextids());
        self::assertContains("$context->id", $contextlist->get_contextids());
        provider::delete_data_for_user($contextlist);
        service\rating::update_all_grades($forum);
        $grades = $DB->get_records('moodleoverflow_grades', ['moodleoverflowid' => $forum->id], null, 'userid, grade');
        self::assertEquals(2.5, $grades[$user->id]->grade);
        self::assertArrayNotHasKey($user2->id, $grades);

        // Test delete context.
        provider::delete_data_for_all_users_in_context($context);
        service\rating::update_all_grades($forum);
        self::assertEmpty($DB->get_records('moodleoverflow_grades', ['moodleoverflowid' => $forum->id], null, 'userid, grade'));
    }

    /**
     * Tests that the posts queued for the daily digest are found and deleted with the user's data.
     */
    public function test_digest_queue_deleted(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        [$user1, $user2, $user3] = $this->create_students(3, $course);
        $posts = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);
            $contexts[$i] = \context_module::instance($forum->cmid);
            [, $posts[$i]] = $this->generator->post_to_forum($forum, $user1);
            foreach ([$user1, $user2, $user3] as $user) {
                $DB->insert_record('moodleoverflow_digest_queue', ['userid' => $user->id, 'postid' => $posts[$i]->id]);
            }
        }

        // The queue alone makes a context contain data of the user.
        $this->assertContains("{$contexts[0]->id}", provider::get_contexts_for_userid($user3->id)->get_contextids());

        // Delete the data of a single user in the first forum.
        provider::delete_data_for_user(new approved_contextlist($user1, 'mod_moodleoverflow', [$contexts[0]->id]));
        $this->assertFalse($DB->record_exists('moodleoverflow_digest_queue', ['userid' => $user1->id, 'postid' => $posts[0]->id]));
        $this->assertTrue($DB->record_exists('moodleoverflow_digest_queue', ['userid' => $user1->id, 'postid' => $posts[1]->id]));
        $this->assertEquals(2, $DB->count_records('moodleoverflow_digest_queue', ['postid' => $posts[0]->id]));

        // Delete the data of a list of users in the first forum.
        provider::delete_data_for_users(new approved_userlist($contexts[0], 'mod_moodleoverflow', [$user2->id]));
        $this->assertFalse($DB->record_exists('moodleoverflow_digest_queue', ['userid' => $user2->id, 'postid' => $posts[0]->id]));
        $this->assertTrue($DB->record_exists('moodleoverflow_digest_queue', ['userid' => $user3->id, 'postid' => $posts[0]->id]));

        // Delete the data of all users in the first forum.
        provider::delete_data_for_all_users_in_context($contexts[0]);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_digest_queue', ['postid' => $posts[0]->id]));
        $this->assertEquals(3, $DB->count_records('moodleoverflow_digest_queue', ['postid' => $posts[1]->id]));
    }
}
