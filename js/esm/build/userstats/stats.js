/**
 * The user statistics: their data type, and how they are searched and sorted.
 *
 * @module     mod_moodleoverflow/userstats/stats
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const a=["forum","course"],i=["activity","reputation","upvotes","downvotes"],p=(e,t)=>`${e}${t}`,c=e=>t=>t.name.toLowerCase().includes(e.trim().toLowerCase()),m=e=>e.courseactivity===0,u=(e,{key:t,descending:n})=>[...e].sort((o,r)=>{const s=t==="name"?o.name.localeCompare(r.name):o[t]-r[t];return(n?-s:s)||o.name.localeCompare(r.name)});export{p as field,m as isInactive,c as matchesName,i as metrics,a as scopes,u as sortStats};
