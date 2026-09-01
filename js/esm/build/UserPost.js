import{str as t}from"./lang";import{jsx as a,jsxs as s}from"react/jsx-runtime";/**
 * A single post of a user, as it is listed on the user.php page.
 *
 * @module     mod_moodleoverflow/UserPost
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const r=e=>new Date(e*1e3).toLocaleDateString("de-DE",{timeZone:"Europe/Berlin",day:"2-digit",month:"2-digit",year:"numeric"});function i({post:e}){return a("article",{className:"card mb-3",children:s("div",{className:"card-body",children:[s("div",{className:"d-flex justify-content-between align-items-baseline gap-3 mb-2",children:[a("h4",{className:"h6 mb-0",children:a("a",{href:e.discussionurl,className:"text-decoration-none",children:e.discussionsubject})}),a("small",{className:"text-muted text-nowrap",children:r(e.created)})]}),a("div",{dangerouslySetInnerHTML:{__html:e.message}}),a("a",{href:e.posturl,className:"small",children:t("showpost")})]})})}export{i as default};
