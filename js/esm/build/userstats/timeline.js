/**
 * Groups the activity per day into the periods the chart shows: days for a short timeline, weeks for a longer one.
 * Every period from the first activity until today is listed, also the empty ones.
 *
 * The days are calendar dates without a time, so they are handled as local dates, in which no timezone can move them.
 *
 * @module     mod_moodleoverflow/userstats/timeline
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=30,y=t=>{const[e,o,s]=t.split("-").map(Number);return new Date(e,o-1,s)},m=(t,e)=>new Date(t.getFullYear(),t.getMonth(),t.getDate()+e),g=(t,e)=>Math.round((e.getTime()-t.getTime())/864e5),l=(t,e)=>g(t,e)+1<=u?"day":"week",D=(t,e,o)=>e==="week"?m(t,-((t.getDay()-o+7)%7)):t,v=t=>t==="week"?7:1,b=t=>{const e=new Map;for(const o of t.flat()){const s=e.get(o.day)??{day:o.day,posts:0,votes:0};e.set(o.day,{...s,posts:s.posts+o.posts,votes:s.votes+o.votes})}return[...e.values()].sort((o,s)=>o.day.localeCompare(s.day))},f=(t,e,o=new Date)=>{if(t.length===0)return{unit:"day",periods:[]};const s=y(t[0].day),p=new Date(o.getFullYear(),o.getMonth(),o.getDate()),c=y(t[t.length-1].day),d=c>p?c:p,a=l(s,d),r=new Map;for(let n=D(s,a,e);n<=d;n=m(n,v(a)))r.set(n.getTime(),{start:n,posts:0,votes:0});for(const n of t){const i=r.get(D(y(n.day),a,e).getTime());i&&(i.posts+=n.posts,i.votes+=n.votes)}return{unit:a,periods:[...r.values()]}};export{l as chooseUnit,f as groupTimeline,b as mergeDays};
