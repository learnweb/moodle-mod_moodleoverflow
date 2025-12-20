CHANGELOG
=========

v.5.1-r1 (2025-11-27)
---------------------
Moodleoverflow had a lot of bug fixes as well as code improving changes.
Here a list of the most important changes:
- Fix for errors and false rendering when editing posts ([#233](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/233), [#244](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/244))
- Bugfix in "Mark posts as read"-button ([#232](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/232))
- No more missing activity completion ([#238](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/238))
- Improved behavior for moving discussions ([#239](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/239), [#241](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/241))

v5.0-r1 (2025-08-01)
------------------
- Fixes Issues [#216](https://github.com/learnweb/moodle-mod_moodleoverflow/issues/216),
               [#211](https://github.com/learnweb/moodle-mod_moodleoverflow/issues/211),
               [#202](https://github.com/learnweb/moodle-mod_moodleoverflow/issues/202)
- Adaption to Moodle 5.0

4.5.1 (2025-05-19)
------------------
[HOTFIX] #214

4.5.0 (2025-05-06)
------------------
* Moodle 4.5 compatible version


[v4.2-r2](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.2-r2)
------------------
Bug Fixes:

* Fix Page Layout https://github.com/learnweb/moodle-mod_moodleoverflow/pull/149 thanks @lucaboesch
* Improve userstats page - have consistent handling for anonymous moodleoverflows https://github.com/learnweb/moodle-mod_moodleoverflow/pull/147 @TamaroWalter
* Fix attachment not found https://github.com/learnweb/moodle-mod_moodleoverflow/pull/146 @TamaroWalter

[v4.2-r1](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.2-r1)
------------------
New Features:

* discussion can be moved to another moodleoverflow forum of the same course https://github.com/learnweb/moodle-mod_moodleoverflow/pull/119
* unread posts email can be send on a daily basis (inherits forumsetting) https://github.com/learnweb/moodle-mod_moodleoverflow/pull/119
* user behaviour in moodleoverflow forums can be seen in a statistics (how many up- and downvotes a user has, his activity and reputation in the course) for one course https://github.com/learnweb/moodle-mod_moodleoverflow/pull/120
* new setting "multiplemarks", which allows to mark multiple post as solved and/or helpful. Setting can be turned on and off at any time for single moodleoverflows. https://github.com/learnweb/moodle-mod_moodleoverflow/pull/128 (description), https://github.com/learnweb/moodle-mod_moodleoverflow/pull/130 (commits)

bug fixes:

* duplicate forum title and description removed https://github.com/learnweb/moodle-mod_moodleoverflow/issues/121
* never ending query in privacy provider fixed

[v4.1-r1](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.1-r1)
------------------
Bug fixes:
* print header only once
* hide anonymous student names to teacher in edit message
* add missing capability strings
* use allowforcesubscribe capability for subscription
* minor style fixes

[v4.0-r4](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.0-r4)
------------------
* Allow admins to enable the moderation feature
*  moderation feature means:
* Either questions (initial post) or questions and comments are reviewed before they are published
* Reviewers are, by default, teachers (capability can be assigned to further roles)
* Authors of rejected posts/questions are notified with an e-mail and an optional reasoning
* Minor security fix so the post can only be changed by the author
* Changes for anonymous feature
* Setting for disallowing of creation of anonymous moodleoverflows is now working
* Admin can choose between changing existing forums to not anonymous or disabling the setting for future moodleoverflows

[v4.0-r3](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.0-r3)
------------------
* Allow admins to enable the moderation feature
* moderation feature means:
* Either questions (initial post) or questions and comments are reviewed before they are published
* Reviewers are, by default, teachers (capability can be assigned to further roles)
* Authors of rejected posts/questions are notified with an e-mail and an optional reasoning
* Minor security fix so the post can only be changed by the author
* Changes for anonymous feature
* Setting for disallowing of creation of anonymous moodleoverflows is now working
* Admin can choose between changing existing forums to not anonymous or disabling the setting for future moodleoverflows

[v4.0-r2](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.0-r2)
------------------
* Bug fix

[v4.0-r1](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.0-r1)
------------------
* Compatibility for Moode 4.0
* Overhaul of anonymous Feature
* Style update
* Enable teachers to make reputation and voting optional

[v3.11-r2](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v3.11-r2)
------------------
* Anonymous forum feature and some style and bug fixes

[v3.11-r1](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v3.11-r1)
------------------
* Minor Bug fixes

v3.10-r1 (2020111200)
------------------
### Fixes

 * MySQL support for queries
 * Do not escape subject titles
 * Added grades table to privacy provider
 * Only allow using grade as activity completion goal if grading is activated

### Other Changes

* Added support for 3.10
