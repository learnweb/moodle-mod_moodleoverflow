/**
 * The user statistics: their data type, and how they are searched and sorted.
 *
 * @module     mod_moodleoverflow/userstats/stats
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const a=["forum","course"],i=["activity","reputation","upvotes","downvotes"],p=(t,e)=>`${t}${e}`,c=t=>e=>e.name.toLowerCase().includes(t.trim().toLowerCase()),m=t=>t.courseactivity===0,u=(t,{key:e,descending:n})=>[...t].sort((o,r)=>{const s=e==="name"?o.name.localeCompare(r.name):o[e]-r[e];return(n?-s:s)||o.name.localeCompare(r.name)});export{p as field,m as isInactive,c as matchesName,i as metrics,a as scopes,u as sortStats};
