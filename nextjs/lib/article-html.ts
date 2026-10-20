import "server-only";
import sanitizeHtml from "sanitize-html";

/** Retain safe editorial markup and preserve line breaks in plain-text articles. */
export function cleanArticleHtml(input: string): string {
  const hasMarkup = /<\/?[a-z][^>]*>/i.test(input);
  if (!hasMarkup) {
    const escaped = sanitizeHtml(input, {allowedTags: [], allowedAttributes: {}});
    return escaped.split(/\r?\n\s*\r?\n/g)
      .map(part => part.trim())
      .filter(Boolean)
      .map(part => `<p>${part.replace(/\r?\n/g, "<br>")}</p>`)
      .join("");
  }
  return sanitizeHtml(input, {
    allowedTags: ["p","br","strong","b","em","i","u","s","blockquote","h2","h3","h4","h5","h6","ul","ol","li","a","img","figure","figcaption","hr","pre","code","table","thead","tbody","tr","th","td","sup","sub","span","div"],
    allowedAttributes: {a:["href","title","target"],img:["src","alt","title","width","height","loading"],th:["colspan","rowspan"],td:["colspan","rowspan"]},
    allowedSchemes: ["https","http","mailto"],
    allowedSchemesAppliedToAttributes: ["href","src"],
    allowProtocolRelative: false,
    transformTags: {
      a: (tag, attrs) => ({tagName:tag, attribs:{...attrs,rel:"nofollow noopener noreferrer",target:attrs.target==="_blank"?"_blank":"_self"}}),
      img: (tag, attrs) => ({tagName:tag, attribs:{...attrs,loading:"lazy"}})
    },
    disallowedTagsMode: "discard"
  });
}
