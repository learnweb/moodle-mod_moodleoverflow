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
 * Ajax functions for moodleoverflow
 *
 * @module     mod/moodleoverflow
 * @package    mod_moodleoverflow
 * @copyright  2017 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification'], function ($, ajax, templates, notification) {

    var t = {
        /**
         * Records a upvote / downvote.
         * @param int discussionid
         * @param int postid
         * @param int ratingid
         * @param int userid
         * @param string link
         * @param string sesskey
         * @returns {string}
         */
        recordvote: function (discussionid, ratingid, userid, event) {
            var target = $(event.target).closest('.moodleoverflowpost').prev();
            var postid = target.attr('id');
            var postid = postid.substring(1);

            var vote = ajax.call([{
                methodname: 'mod_moodleoverflow_record_vote',
                args: {
                    discussionid: discussionid,
                    postid: postid,
                    ratingid: ratingid,
                    userid: userid,
                    sesskey: M.cfg.sesskey
                }
            }
            ]);

            vote[0].done(function (response) {

                var parentdiv = $(event.target).parent().parent();
                // Update Votes.
                if (ratingid == 2) {
                    parentdiv.children('a:first-of-type').children().attr(
                        'src', M.util.image_url('vote/upvoted', 'moodleoverflow'));
                    parentdiv.children('a:nth-of-type(2)').children().attr(
                        'src', M.util.image_url('vote/downvote', 'moodleoverflow'));
                }
                else if (ratingid == 1) {
                    parentdiv.children('a:first-of-type').children().attr(
                        'src', M.util.image_url('vote/upvote', 'moodleoverflow'));
                    parentdiv.children('a:nth-of-type(2)').children().attr(
                        'src', M.util.image_url('vote/downvoted', 'moodleoverflow'));
                }
                else {
                    parentdiv.children('a:first-of-type').children().attr(
                        'src', M.util.image_url('vote/upvote', 'moodleoverflow'));
                    parentdiv.children('a:nth-of-type(2)').children().attr(
                        'src', M.util.image_url('vote/downvote', 'moodleoverflow'));
                }

                parentdiv.children('p').text(response.postrating);

                // Update user reputation.
                templates.replaceNode($('.user-details,.author').find('a[href*="id=' + userid + '"]')
                    .siblings('span'), '<span>' + response.raterreputation + '</span>', "");
                if (userid !== response.ownerid) {
                    templates.replaceNode($('.user-details,.author').find('a[href*="id=' + response.ownerid + '"]')
                        .siblings('span'), '<span>' + response.ownerreputation + '</span>', "");
                }

                // Check if post is an answer or a comment.
                var node = $('#p' + postid).parent();
                var classattr = node.attr('class');

                var d = new Date(node.find('.user-action-time').text());

                if (node.attr('role') !== 'main' && // Question.
                    node.children('div').first().attr('class').indexOf('status') < 0 && // Mark.
                    classattr !== 'intend') { // Comment.

                    // Post is not the question, not a comment and not marked as helpful/solved.
                    // Update order of posts.

                    // Get creation date of post.
                    var d = new Date(node.find('.user-action-time').text());

                    if (ratingid === 1 || ratingid === 20) {
                        // Post rating has been reduced.
                        var nextsibling;
                        var success = false;
                        var votes;

                        node.nextAll().each(function () {
                            nextsibling = $(this);
                            votes = parseInt($('.votes p', this).text());
                            if (votes < response.postrating ||
                                (votes === response.postrating &&
                                d < new Date(nextsibling.find('.user-action-time').text()))) {
                                success = true;
                                return false;
                            }
                        });

                        // Insert before Sibling.
                        if (success) {
                            node.detach();
                            node.insertBefore(nextsibling);
                        }
                        else {
                            if (nextsibling) {
                                // Insert as last Element.
                                node.detach();
                                node.insertAfter(nextsibling);
                            }
                        }
                    }
                    else {
                        // Post reating has been increased.
                        var prevsibling;
                        var success = false;
                        var votes;

                        node.prevUntil(':not(.tmargin)').each(function () {
                            prevsibling = $(this);
                            votes = parseInt($('.votes p', this).text());
                            if (votes > response.postrating ||
                                (votes === response.postrating &&
                                d > new Date(prevsibling.find('.user-action-time').text()))) {
                                success = true;
                                return false;
                            }
                        });

                        // Insert after Sibling.
                        if (success) {
                            node.detach();
                            node.insertAfter(prevsibling);
                        }
                        else {
                            if (prevsibling) {
                                // Insert as first Element.
                                node.detach();
                                node.insertBefore(prevsibling);
                            }
                        }
                    }
                }

                $(window).scrollTop($('#p' + postid).offset().top);

            }).fail(notification.exception);

            return vote;
        },

        clickevent: function (discussionid, userid) {
            $(".upvote").on("click", function (event) {
                if ($(event.target).is('a')) {
                    event.target = $(event.target).children();
                }

                if ($(event.target).parent().attr('class').indexOf('active') >= 0) {
                    t.recordvote(discussionid, 20, userid, event);
                }
                else {
                    t.recordvote(discussionid, 2, userid, event);
                }
                $(event.target).parent().toggleClass('active');
                $(event.target).parent().nextAll('a').removeClass('active');
            });

            $(".downvote").on("click", function (event) {
                if ($(event.target).is('a')) {
                    event.target = $(event.target).children();
                }

                if ($(event.target).parent().attr('class').indexOf('active') >= 0) {
                    t.recordvote(discussionid, 10, userid, event);
                }
                else {
                    t.recordvote(discussionid, 1, userid, event);
                }
                $(event.target).parent().toggleClass('active');
                $(event.target).parent().prevAll('a').removeClass('active');
            });
        }
    };

    return t;
});
