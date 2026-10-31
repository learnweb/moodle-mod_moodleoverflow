/**
 * The user statistics: their data type, and how they are searched and sorted.
 *
 * @module     mod_moodleoverflow/userstats
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=["forum","course"],p=["activity","reputation","upvotes","downvotes"],a=(e,t)=>`${e}${t}`,i=e=>t=>t.name.toLowerCase().includes(e.trim().toLowerCase()),m=e=>e.courseactivity===0,S=(e,{key:t,descending:c})=>[...e].sort((o,r)=>{const s=t==="name"?o.name.localeCompare(r.name):o[t]-r[t];return(c?-s:s)||o.name.localeCompare(r.name)});export{a as field,m as isInactive,i as matchesName,p as metrics,n as scopes,S as sortStats};
