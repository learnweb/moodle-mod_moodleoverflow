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
define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/config', 'core/url', 'core/str'],
    function($, ajax, templates, notification, Cfg, Url, str) {

    var RATING_SOLVED = 3;
    var RATING_REMOVE_SOLVED = 30;
    var RATING_HELPFUL = 4;
    var RATING_REMOVE_HELPFUL = 40;

    var t = {

        /**
         * Reoords a upvote / downvote.
         * @param {int} discussionid
         * @param {int} ratingid
         * @param {int} userid
         * @param {event} event
         * @returns {string}
         */
        recordvote: function(discussionid, ratingid, userid, event) {
            var target = $(event.target).closest('.moodleoverflowpost').prev();
            var postid = target.attr('id');
            postid = postid.substring(1);

            var vote = ajax.call([{
                methodname: 'mod_moodleoverflow_record_vote',
                args: {
                    discussionid: discussionid,
                    postid: postid,
                    ratingid: ratingid,
                    sesskey: Cfg.sesskey
                }
            }
            ]);

            vote[0].done(function(response) {

                var parentdiv = $(event.target).parent().parent();
                // Update Votes.
                if (ratingid === 2) {
                    parentdiv.children('a:first-of-type').children().attr(
                        'src', Url.imageUrl('vote/upvoted', 'moodleoverflow'));
                    parentdiv.children('a:nth-of-type(2)').children().attr(
                        'src', Url.imageUrl('vote/downvote', 'moodleoverflow'));
                } else if (ratingid === 1) {
                    parentdiv.children('a:first-of-type').children().attr(
                        'src', Url.imageUrl('vote/upvote', 'moodleoverflow'));
                    parentdiv.children('a:nth-of-type(2)').children().attr(
                        'src', Url.imageUrl('vote/downvoted', 'moodleoverflow'));
                } else {
                    parentdiv.children('a:first-of-type').children().attr(
                        'src', Url.imageUrl('vote/upvote', 'moodleoverflow'));
                    parentdiv.children('a:nth-of-type(2)').children().attr(
                        'src', Url.imageUrl('vote/downvote', 'moodleoverflow'));
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
                    var success = false;
                    var votes;

                    if (ratingid === 1 || ratingid === 20) {
                        // Post rating has been reduced.
                        var nextsibling;

                        node.nextAll().each(function() {
                            nextsibling = $(this);
                            votes = parseInt($('.votes p', this).text());
                            if (votes < response.postrating ||
                                (votes === response.postrating &&
                                d < new Date(nextsibling.find('.user-action-time').text()))) {
                                success = true;
                                return false;
                            }
                            return true;
                        });

                        // Insert before Sibling.
                        if (success) {
                            node.detach();
                            node.insertBefore(nextsibling);
                        } else if (nextsibling) {
                            // Insert as last Element.
                            node.detach();
                            node.insertAfter(nextsibling);
                        }
                    } else {
                        // Post reating has been increased.
                        var prevsibling;

                        node.prevUntil(':not(.tmargin)').each(function() {
                            prevsibling = $(this);
                            votes = parseInt($('.votes p', this).text());
                            if (votes > response.postrating ||
                                (votes === response.postrating &&
                                d > new Date(prevsibling.find('.user-action-time').text()))) {
                                success = true;
                                return false;
                            }
                            return true;
                        });

                        // Insert after Sibling.
                        if (success) {
                            node.detach();
                            node.insertAfter(prevsibling);
                        } else {
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

        /**
         * Initializes the clickevent on upvotes / downvotes.
         * @param {int} discussionid
         * @param {int} userid
         */
        clickevent: function(discussionid, userid) {
            $(".upvote").on("click", function(event) {
                if ($(event.target).is('a')) {
                    event.target = $(event.target).children();
                }

                if ($(event.target).parent().attr('class').indexOf('active') >= 0) {
                    t.recordvote(discussionid, 20, userid, event);
                } else {
                    t.recordvote(discussionid, 2, userid, event);
                }
                $(event.target).parent().toggleClass('active');
                $(event.target).parent().nextAll('a').removeClass('active');
            });

            $(".downvote").on("click", function(event) {
                if ($(event.target).is('a')) {
                    event.target = $(event.target).children();
                }

                if ($(event.target).parent().attr('class').indexOf('active') >= 0) {
                    t.recordvote(discussionid, 10, userid, event);
                } else {
                    t.recordvote(discussionid, 1, userid, event);
                }
                $(event.target).parent().toggleClass('active');
                $(event.target).parent().prevAll('a').removeClass('active');
            });

            $(".marksolved").on("click", function(event) {
                var post = $(event.target).parents('.moodleoverflowpost');

                if (post.hasClass('statusteacher') || post.hasClass('statusboth')) {
                    // Remove solution mark.
                    t.recordvote(discussionid, RATING_REMOVE_SOLVED, userid, event)[0].then(function() {
                        if (post.hasClass('statusteacher')) {
                            post.removeClass('statusteacher');
                        } else {
                            post.removeClass('statusboth');
                            post.addClass('statusstarter');
                        }

                        var promiseSolved = str.get_string('marksolved', 'mod_moodleoverflow');
                        $.when(promiseSolved).done(function(string) {
                            $(event.target).text(string);
                        });

                        t.redoStatus(post);
                    });
                } else {
                    // Add solution mark.
                    t.recordvote(discussionid, RATING_SOLVED, userid, event)[0].then(function() {
                        // Remove other solution mark in dom.
                        t.removeSolved(post.parent().parent());
                        if (post.hasClass('statusstarter')) {
                            post.removeClass('statusstarter');
                            post.addClass('statusboth');
                        } else {
                            post.addClass('statusteacher');
                        }

                        var promiseNotSolved = str.get_string('marknotsolved', 'mod_moodleoverflow');
                        $.when(promiseNotSolved).done(function(string) {
                            $(event.target).text(string);
                        });

                        t.redoStatus(post);
                    });
                }


            });

            $(".markhelpful").on("click", function(event) {
                var post = $(event.target).parents('.moodleoverflowpost');

                if (post.hasClass('statusstarter') || post.hasClass('statusboth')) {
                    // Remove helpful mark.
                    t.recordvote(discussionid, RATING_REMOVE_HELPFUL, userid, event)[0].then(function() {
                        if (post.hasClass('statusstarter')) {
                            post.removeClass('statusstarter');
                        } else {
                            post.removeClass('statusboth');
                            post.addClass('statusteacher');
                        }

                        var promiseHelpful = str.get_string('markhelpful', 'mod_moodleoverflow');
                        $.when(promiseHelpful).done(function(string) {
                            $(event.target).text(string);
                        });
                        t.redoStatus(post);
                    });
                } else {
                    // Add helpful mark.
                    t.recordvote(discussionid, RATING_HELPFUL, userid, event)[0].then(function() {
                        // Remove other helpful mark in dom.
                        t.removeHelpful(post.parent().parent());
                        if (post.hasClass('statusteacher')) {
                            post.removeClass('statusteacher');
                            post.addClass('statusboth');
                        } else {
                            post.addClass('statusstarter');
                        }

                        var promiseNotHelpful = str.get_string('marknothelpful', 'mod_moodleoverflow');
                        $.when(promiseNotHelpful).done(function(string) {
                            $(event.target).text(string);
                        });
                        t.redoStatus(post);
                    });
                }

            });
        },

        removeHelpful: function(root) {
            var formerhelpful = root.find('.statusstarter, .statusboth');
            if (formerhelpful.length > 0) {
                if (formerhelpful.hasClass('statusstarter')) {
                    formerhelpful.removeClass('statusstarter');
                } else {
                    formerhelpful.removeClass('statusboth');
                    formerhelpful.addClass('statusteacher');
                }

                t.redoStatus(formerhelpful);

                var promiseHelpful = str.get_string('markhelpful', 'mod_moodleoverflow');
                $.when(promiseHelpful).done(function(string) {
                    formerhelpful.find('.markhelpful').text(string);
                });
            }

        },

        removeSolved: function(root) {
            var formersolution = root.find('.statusteacher, .statusboth');
            if (formersolution.length > 0) {
                if (formersolution.hasClass('statusteacher')) {
                    formersolution.removeClass('statusteacher');
                } else {
                    formersolution.removeClass('statusboth');
                    formersolution.addClass('statusstarter');
                }

                t.redoStatus(formersolution);

                var promiseHelpful = str.get_string('marksolved', 'mod_moodleoverflow');
                $.when(promiseHelpful).done(function(string) {
                    formersolution.find('.marksolved').text(string);
                });
            }
        },

        /**
         * Redoes the post status
         * @param {object} post dom with .moodleoverflowpost which status should be redone
         */
        redoStatus: function(post) {
            if ($(post).hasClass('statusboth')) {
                var statusBothRequest = [
                    {key: 'teacherrating', component: 'mod_moodleoverflow'},
                    {key: 'starterrating', component: 'mod_moodleoverflow'},
                    {key: 'bestanswer', component: 'mod_moodleoverflow'}
                ];
                str.get_strings(statusBothRequest).then(function(results) {
                    var circle = templates.renderPix('status/c_circle', 'mod_moodleoverflow', results[0]);
                    var box = templates.renderPix('status/b_box', 'mod_moodleoverflow', results[1]);
                    $.when(box, circle).done(function(boxImg, circleImg) {
                        post.find('.status').html(boxImg + circleImg + results[2]);
                    });
                    return results;
                });
            } else if ($(post).hasClass('statusteacher')) {
                var statusTeacherRequest = [
                    {key: 'teacherrating', component: 'mod_moodleoverflow'},
                    {key: 'solvedanswer', component: 'mod_moodleoverflow'}
                ];
                str.get_strings(statusTeacherRequest).then(function(results) {
                    var circle = templates.renderPix('status/c_outline', 'mod_moodleoverflow', results[0]);
                    $.when(circle).done(function(circleImg) {
                        post.find('.status').html(circleImg + results[1]);
                    });
                    return results;
                });
            } else if ($(post).hasClass('statusstarter')) {
                var statusStarterRequest = [
                    {key: 'starterrating', component: 'mod_moodleoverflow'},
                    {key: 'helpfulanswer', component: 'mod_moodleoverflow'}
                ];
                str.get_strings(statusStarterRequest).then(function(results) {
                    var box = templates.renderPix('status/b_outline', 'mod_moodleoverflow', results[0]);
                    $.when(box).done(function(boxImg) {
                        post.find('.status').html(boxImg + results[1]);
                    });
                    return results;
                });
            } else {
                post.find('.status').html('');
            }

        }
    };

    return t;
});
