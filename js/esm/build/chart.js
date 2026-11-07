import{useEffect as n,useState as c}from"react";import{requireAsync as a}from"@moodle/lms/core/amd";/**
 * Helpers to draw charts with Chart.js, which Moodle core ships as the AMD module core/chartjs.
 *
 * A canvas does not know the CSS of the page, so the colours of the theme are read from its custom properties and
 * read again when the colour mode (light or dark) changes.
 *
 * @module     mod_moodleoverflow/chart
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=()=>a("core/chartjs"),o=e=>{const t=document.createElement("span");t.style.color=`var(${e})`,document.body.append(t);const r=getComputedStyle(t).color;return t.remove(),r},i=e=>(o(e).match(/[\d.]+/g)??[]).slice(0,3).join(", "),l=e=>{const t=r=>i(e?`--bs-${r}-text-emphasis`:`--bs-${r}`);return{primary:t("primary"),success:t("success"),text:o("--bs-secondary-color"),grid:o("--bs-border-color-translucent"),font:getComputedStyle(document.body).fontFamily}},p=()=>{const e=()=>document.documentElement.dataset.bsTheme==="dark",[t,r]=c(e);return n(()=>{const s=new MutationObserver(()=>r(e()));return s.observe(document.documentElement,{attributes:!0,attributeFilter:["data-bs-theme"]}),()=>s.disconnect()},[]),t};export{d as loadChart,l as themeColours,p as useDarkMode};
