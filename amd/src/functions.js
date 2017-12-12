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
        recordvote: function (discussionid, postid, ratingid, userid, link, sesskey) {
            var vote = ajax.call([{
                methodname: 'mod_moodleoverflow_record_vote',
                args: {
                    discussionid: discussionid,
                    postid: postid,
                    ratingid: ratingid,
                    userid: userid,
                    sesskey: sesskey
                }
            }
            ]);

            vote[0].done(function (response) {
                var context;

                // Context für Downvote.
                context = {
                    userupvoted: false,
                    userdownvoted: true,
                    canchange: true,
                    votes: response.postrating,
                    removeupvotelink: link + "&r=20",
                    upvotelink: link + "&r=2",
                    removedownvotelink: link + "&r=10",
                    downvotelink: link + "&r=1"
                };

                // Upvote.
                if (ratingid === 2) {
                    context.userupvoted = true;
                    context.userdownvoted = false;
                }
                // Vote has been removed.
                else if (ratingid === 10 || ratingid === 20) {
                    context.userdownvoted = false;
                    context.userupvoted = false;
                }

                // Update templates.
                templates.render('mod_moodleoverflow/postvoting', context)
                    .then(function (html, js) {
                        // Update votes.
                        templates.replaceNodeContents($('.votes a[href$="rp=' + postid + '"]').parent(), html, js);

                    })
                    .fail(notification.example);

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
                            node.remove();
                            node.insertBefore(nextsibling);
                        }
                        else {
                            if (nextsibling) {
                                // Insert as last Element.
                                node.remove();
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
                            node.remove();
                            node.insertAfter(prevsibling);
                        }
                        else {
                            if (prevsibling) {
                                // Insert as first Element.
                                node.remove();
                                node.insertBefore(prevsibling);
                            }
                        }
                    }
                }
                $(window).scrollTop($('#p' + postid).offset().top);

            }).fail(notification.exception);

            return vote;
        }
    };

    return t;
});
