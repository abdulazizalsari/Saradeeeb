import "server-only";
import sanitizeHtml from "sanitize-html";
/** Preserve editorial structure while removing scripts, event handlers and unsafe URLs. */
export function cleanArticleHtml(input:string){
 return sanitizeHtml(input,{allowedTags:["p","br","strong","b","em","i","u","s","blockquote","h2","h3","h4","h5","h6","ul","ol","li","a","img","figure","figcaption","hr","pre","code","table","thead","tbody","tr","th","td","sup","sub","span","div"],allowedAttributes:{a:["href","title","target"],img:["src","alt","title","width","height","loading"],th:["colspan","rowspan"],td:["colspan","rowspan"]},allowedSchemes:["https","http","mailto"],allowedSchemesAppliedToAttributes:["href","src"],allowProtocolRelative:false,transformTags:{a:(tag,attrs)=>({tagName:tag,attribs:{...attrs,rel:"nofollow noopener noreferrer",target:attrs.target==="_blank"?"_blank":"_self"}}),img:(tag,attrs)=>({tagName:tag,attribs:{...attrs,loading:"lazy"}})},disallowedTagsMode:"discard"});
}
