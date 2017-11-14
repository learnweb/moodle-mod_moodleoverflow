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
         * @returns {string}
         */
        recordvote: function (discussionid, postid, ratingid, userid, link) {

            var vote = ajax.call([{
                methodname: 'mod_moodleoverflow_record_vote',
                args: {
                    discussionid: discussionid,
                    postid: postid,
                    ratingid: ratingid,
                    userid: userid
                }
            }
            ]);

            vote[0].done(function (response) {
                // eslint-disable-next-line no-console
                console.log(response);

                var context;

                // Context für Downvote
                context = {userupvoted: false,
                    userdownvoted: true,
                    canchange: true,
                    votes: response.postrating,
                    removeupvotelink: link + "&amp;r=20",
                    upvotelink: link + "&amp;r=2",
                    removedownvotelink: link + "&amp;r=10",
                    downvotelink: link + "&amp;r=1"};

                // Upvote
                if (ratingid === 2) {
                    context.userupvoted = true;
                    context.userdownvoted = false;
                }
                // Vote has been removed
                else {
                    context.userdownvoted = false;
                    context.userupvoted = false;
                }

                // Update templates
                templates.render('mod_moodleoverflow/postvoting', context)
                    .then(function(html,js) {
                        // Update votes
                        templates.replaceNodeContents($('.votes a[href$="rp='+ postid +'"]').parent(), html, "");

                    })
                    .fail(notification.example);

                // Update user reputation
                templates.replaceNode($('.user-details,.author').find('a[href*="id='+ userid +'"]').siblings('span'), '<span>'+response.raterreputation+'</span>', "");
                if(userid !== response.ownerid) {
                    templates.replaceNode($('.user-details,.author').find('a[href*="id='+ response.ownerid +'"]').siblings('span'), '<span>'+response.ownerreputation+'</span>', "");
                }

                // Update order of posts
                if(ratingid == 1 ||ratingid == 20) {
                    // Postrating has been reduced
                    // TODO does not work for comments
                    // Save node
                    var node = $('.tmargin a #p'+postid+'').parent();
                    var nextsibling;
                    console.log(node.text());

                    node.siblings().each(function () {
                       if(parseInt($('.votes p').text()) < respone.postrating) {
                           nextsibling = $(this);
                           return false;
                       }
                    });

                    console.log(nextsibling);

                    // TODO kann auch der letzte Post sein
                    node.remove();
                    node.insertBefore(nextsibling);

                }


            }).fail(notification.exception);

            return vote;
        }
    };

    return t;
});
