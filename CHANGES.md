CHANGELOG
=========
[v5.3-r1 (2026-11-14)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v5.3-r1)
---------------------
This release is a big milestone in the history of the plugin. The goal for this year was to reduce the code debt in the
backend and to improve it. This goal is now reached: the backend has been rebuilt with a new service-oriented architecture.
The rebuild made it possible to implement many new features and sped up the development thanks to centralized read and write
data flows.

**New features:**

> **Important:** Some new features were implemented with React, which Moodle fully supports from version 5.3 on. On older
> Moodle versions these features are either not available or only available in their old implementation (without the
> improvements). We recommend keeping Moodle updated to the newest version. Moodle 5.3 is an LTS release.

The most important things for users are the new and improved features. A short summary:

- Search: Moodle's global search is now supported, as well as a search within the activity
  ([#285](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/285), [#317](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/317))
- User posts overview: A new navigation item in the user profile shows all posts of a user that the viewer can see, built with
  React ([#288](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/288))
- Mails: All mails have improved HTML versions ([#311](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/311))
- Navigation to neighbouring discussions: It is easier to navigate between discussions
  ([#312](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/312))
- Visible names in anonymous forums: With a new capability, certain roles (e.g. teachers) can be set to be excluded from being anonymous
  in anonymous forums. This preserves the anonymity of e.g. students while allowing the teachers to be visible
  ([#313](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/313))
- New UI for the user statistics: The statistics page was rebuilt in React with several improvements, such as a time series
  chart of posts and votes ([#314](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/314))
- Inline answering: Answers and comments can now be written directly below a post without being redirected to another page.
  This makes it easier to write short answers that don't need additional media. Advanced posts with attachments are still
  possible ([#315](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/315))
- Word count limits: Teachers can now set a minimum and a maximum number of words per post. This gives more control over the
  posts ([#316](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/316))
- Post export: Users with a new capability (by default teachers and higher) can export the posts of a forum to different
  document types ([#318](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/318))

**Refactoring of the backend:**

The old backend had several flaws, as each domain (posting, subscriptions, read tracking, mails, …) did everything on its own
without any central architecture. This included checking capabilities and permissions as well as reading from and writing to
the database. As a consequence, the same question was answered inconsistently (e.g. "can a user read this post" was answered
differently per domain), and data objects (a moodleoverflow, discussion or post) were read from the database again and again in
different functions instead of being passed on. A side effect was that the pages and the external functions behaved
inconsistently.

The solution was to centralize all reads and writes. A new `permissions` class bundles every check for viewing and writing,
centralizing the business rules like a "rulebook" for the plugin. Every domain received a `service` class that handles its
use cases. Service functions check the permissions of a use case, orchestrate the reading and writing of data objects and pass
them on to other domains, so that data that is already known is not read from the database again. The model classes
(including a new `moodleoverflow` class) keep the data in the database consistent. The entry layer (pages, external
functions, REST routes) only checks the site security (`require_login`, sesskey), calls the services and renders the output.
It holds no SQL and no permission rules.

A short summary:

- New `moodleoverflow` class to represent an instance ([#291](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/291))
- New central class to check permissions and capabilities ([#294](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/294))
- Domains refactored into services: read tracking ([#295](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/295)),
  review ([#299](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/299)),
  rating ([#300](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/300)),
  posting ([#303](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/303)),
  subscriptions ([#306](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/306)),
  output classes ([#307](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/307)),
  mails ([#309](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/309))

**Bug fixes:**

Together with the refactoring, many bugs were fixed:

- Improve the check for unenrolled users ([#282](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/282))
- Remove the doubled title in the breadcrumb navigation ([#283](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/283))
- Move discussions together with their grades, ratings and post attachments ([#296](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/296))
- Add the missing SVG fallbacks for the Font Awesome icon map ([#304](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/304))

> **Before you upgrade** (due to the refactoring):
>
> - Make a database backup: the update changes tables (subscriptions, mails), and a downgrade is not possible.
> - Mails that are waiting to be sent during the update are not sent. Links in older mails (e.g. to unsubscribe) no longer
>   work. New mails are sent correctly.
> - Changing the subscription mode of a forum now resets the subscription choices of its users.
> - Restoring old backups of forums with automatic subscription subscribes users again who had unsubscribed.
> - Existing sites: managers can only review posts if their role allows `mod/moodleoverflow:reviewpost` (new sites have it).

Thanks to all contributors who found bugs, requested new features or improvements, and opened their own pull requests!

---

[v5.2-r2 (2026-07-07)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v5.2-r2)
---------------------
This release contains fixes for bugs that were found as well as some code debt refactorings:

**Bug fixes**:

- Fix missing reference to first post after creating a new discussion ([#276](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/276))
- Fix typo and wrong DB data read in review functionality ([#277](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/277))

**Refactoring**:

- Fix moodle.org checks and delete some more lines in the locallib ([#273](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/273))

[v5.2-r1 (2026-05-07)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v5.2-r1)
---------------------
Moodleoverflow had a lot of changes in the last months. Most of them were directly for developers, but they were
improvements for users too like the course overview integration. This year's vision is to reduce the code debt
that build up over the last few years. This is the first release towards that goal.

A short summary:

**New features**:

- Course overview integration ([#257](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/257), [#261](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/261), [#264](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/264))
  - Moodleoverflow now supports the course overview page, showing unread posts and give the user the ability to change readtracking/subscription setting.
- Activity choose improvements ([#260](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/260), [#263](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/263))

**Bug fixes**:

- Fix video embedding in posts ([#255](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/255))
- Fix missing SVG icon ([#270](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/270))

**Refactoring**:

- Major locallib reduction ([#271](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/271))
  - Removed ~1,400 lines from locallib.php by replacing old procedural printing functions with output classes that represent single components
- External functions moved to dedicated classes ([#267](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/267))

Special thanks to [lucaboesch](https://github.com/lucaboesch) and [humanspaces](https://github.com/humanspaces) for contributing to this release!

[v5.1-r2 (2025-12-22)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v5.1-r2)
---------------------
A new set of updates are waiting for a new release! And there is no better christmas gift than a new
moodleoverflow version. Here's a list of the recent changes:
- Completions with passing grades are now possible ([#253](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/253))
- Discussion title is no more editable in the reply form ([#250](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/250))
- Reply form displays the post it is referring to ([#249](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/249))

[v5.1-r1 (2025-11-27)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v5.1-r1)
---------------------
Moodleoverflow had a lot of bug fixes as well as code improving changes.
Here a list of the most important changes:
- Fix for errors and false rendering when editing posts ([#233](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/233), [#244](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/244))
- Bugfix in "Mark posts as read"-button ([#232](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/232))
- No more missing activity completion ([#238](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/238))
- Improved behavior for moving discussions ([#239](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/239), [#241](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/241))

[v5.0-r1 (2025-08-01)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v5.0-r1)
------------------
- Fixes Issues [#216](https://github.com/learnweb/moodle-mod_moodleoverflow/issues/216),
               [#211](https://github.com/learnweb/moodle-mod_moodleoverflow/issues/211),
               [#202](https://github.com/learnweb/moodle-mod_moodleoverflow/issues/202)
- Adaption to Moodle 5.0

[v4.5-r2 (2025-05-19](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.5-r2)
------------------
- fix for [#214](https://github.com/learnweb/moodle-mod_moodleoverflow/pull/214)

[v4.5-r1 (2025-05-06)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/tag/v4.5-r1)
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
