/**
 * Filter for the User posts.
 *
 * @module     mod_moodleoverflow/filters
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=(e,r)=>{const o=r.trim().toLowerCase();return[e.discussionsubject,e.message,e.modflow,e.course].some(s=>s.toLowerCase().includes(o))},l=({query:e,courses:r,timespan:o},s)=>t=>n(t,e)&&(r===null||r.includes(t.courseid))&&(o===null||t.created>=s-o);export{l as approvedBy};
