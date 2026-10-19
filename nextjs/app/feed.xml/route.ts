import {listPublishedArticles} from "../../lib/articles";
export const dynamic="force-dynamic";
const esc=(s:string)=>s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
export async function GET(){
 const origin=(process.env.NEXT_PUBLIC_SITE_URL??"https://saradeeeb-preview.vercel.app").replace(/\/$/,"");
 const articles=await listPublishedArticles("ar",50);
 const items=articles.map(a=>{const url=origin+"/ar/article/"+encodeURIComponent(a.slug);return "<item><title>"+esc(a.title)+"</title><link>"+esc(url)+"</link><guid>"+esc(url)+"</guid><description>"+esc(a.excerpt??"")+"</description>"+(a.published_at?"<pubDate>"+new Date(a.published_at).toUTCString()+"</pubDate>":"")+"</item>";}).join("");
 return new Response('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>سراديب</title><link>'+esc(origin)+'</link><description>مقالات سراديب</description>'+items+'</channel></rss>',{headers:{"Content-Type":"application/rss+xml; charset=utf-8","Cache-Control":"public, max-age=0, s-maxage=300"}});
}
