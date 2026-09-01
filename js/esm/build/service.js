import r from"@moodle/lms/core/fetch";/**
 * Service class that communicates with moodleoverflows REST API
 *
 * @module     mod_moodleoverflow/service
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const t=async s=>await(await r.performGet("mod_moodleoverflow",`user/posts/${s}`)).json();export{t as fetchUserPosts};
