# ![Moodleoverflow](pix/icon.png) Moodleoverflow

![Moodle 4.5–5.2](https://img.shields.io/badge/Moodle-4.5%E2%80%935.2-f98012)
[![Latest release](https://img.shields.io/github/v/release/learnweb/moodle-mod_moodleoverflow)](https://github.com/learnweb/moodle-mod_moodleoverflow/releases/latest)

**Question-and-answer forums for your Moodle courses, inspired by Stack Overflow.** Students ask, the course answers,
and the best answers rise to the top.

<!-- Image: a lively discussion. A question, several answers with votes, one marked as solution (green) and one as
helpful (orange), a comment below an answer, reputation next to the names. -->

---

## Why Moodleoverflow?

In a classic forum, the answer to a question is often hidden somewhere in a long thread. Moodleoverflow is built for
questions and answers:

- **The best answer is on top.** Students vote answers up or down, the questioner marks the answer that helped, and
  teachers mark the solution. Moodleoverflow sorts the answers accordingly, so nobody needs to read through 40 posts.
- **Students help each other.** Every good answer earns reputation. It shows who knows their way around and makes
  helping others visible and worthwhile.
- **Every question gets asked.** In anonymous forums, students ask without their name being shown, which often brings
  out exactly the questions that many have but nobody dares to ask.
- **Teachers stay in control.** Approve posts before they are published, mark the correct solution, decide when
  answers are allowed, and move discussions to the right forum.
- **Participation can count.** Turn reputation into a grade in the gradebook.
- **Fits every course.** Each forum can be set up for its purpose: with or without votes, reputation and grades,
  anonymous or with names, moderated or open, with answers allowed at any time or only within a time window.
  Administrators decide which of these options teachers may use.

---

## How courses use it

### Questions about the lecture

Students ask about the lecture, fellow students answer, and the teacher marks the correct solution. Next semester's
students find the answer to the same question right away.

<!-- Image: a discussion with a solution mark from the teacher and an upvoted answer of a student. -->

### Anonymous questions before the exam

Exam preparation brings questions that nobody wants to ask with their name. In an anonymous forum, authors appear as
"Questioner" and "Answerer #1", "Answerer #2"... Readers can still tell which answers come from the same person, but
not who it is.

<!-- Image: an anonymous discussion with "Questioner" and numbered answerers. -->

### Collect questions first, answer later

With the limited answer mode, the forum collects questions first, for example during a lecture, and opens for answers
at a time the teacher sets. It can also close answering at a deadline.

<!-- Image: the list of discussions with several questions and the note that answering opens later. -->

### Check posts before they are published

In a moderated forum, a teacher approves new questions, or all posts, before the other participants see them. Wrong
answers do not mislead anybody, solutions to graded exercises are not shared, and nothing inappropriate appears.
Rejected posts can come with a short reason for the author.

<!-- Image: a post waiting for review, with the approve and reject buttons. -->

### Recognise helpful students

Teachers can see in an overview who contributes the most: how many posts, received votes and how much reputation each
participant has, per forum and for the whole course. It shows only what is visible in the forums anyway, and it is
switched off unless the administrator enables it.

<!-- Image: the user statistics table. -->

---

## Features

- **Votes, helpful and solution marks** that sort the answers ([details][wiki-ratings])
- **Reputation** per forum or for the whole course, with points the administrator can adjust ([details][wiki-reputation])
- **Grading** based on reputation ([details][wiki-grading])
- **User statistics** for teachers ([details][wiki-userstats])
- **Anonymous mode** for questioners or for everyone ([details][wiki-anonymous])
- **Review** of questions or of all posts before they are published ([details][wiki-review])
- **Limited answer mode** with a start and an end for answers ([details][wiki-limited])
- **Subscriptions** to forums or single discussions, with email notifications or a daily summary ([details][wiki-subscriptions])
- **Read tracking** that highlights new posts ([details][wiki-readtracking])
- **Moving discussions** to another forum of the course ([details][wiki-move])
- **Attachments**, privacy API, backup and restore, global search, activity completion

Every feature can be adjusted: administrators set what teachers may use, teachers decide for each forum. All settings
are explained in the [documentation for administrators][wiki].

---

## Installation

Install the plugin from the [Moodle plugins directory](https://moodle.org/plugins/mod_moodleoverflow), or clone this
repository into `{your/moodle/dirroot}/mod/moodleoverflow`. Then complete the installation in
_Site administration > Notifications_, or run `php admin/cli/upgrade.php` on the command line.

## Documentation and support

- [Documentation for administrators][wiki]: every feature and setting explained.
- Found a bug or have an idea? Open an [issue](https://github.com/learnweb/moodle-mod_moodleoverflow/issues).

---

Moodleoverflow was initially implemented by Kennet Winter. It is developed and maintained by the
[Learnweb development team](https://github.com/learnweb) of the University of Münster, together with
[all contributors](https://github.com/learnweb/moodle-mod_moodleoverflow/graphs/contributors).

[wiki]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators
[wiki-ratings]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#3-votes-marks-and-the-order-of-answers
[wiki-reputation]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#4-reputation
[wiki-grading]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#5-grading
[wiki-userstats]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#6-user-statistics
[wiki-anonymous]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#7-anonymous-mode
[wiki-review]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#8-review-moderated-forums
[wiki-limited]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#9-limited-answer-mode
[wiki-subscriptions]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#10-subscriptions-and-email-notifications
[wiki-readtracking]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#11-read-tracking
[wiki-move]: https://github.com/learnweb/moodle-mod_moodleoverflow/wiki/Documentation-for-administrators#12-moving-discussions
