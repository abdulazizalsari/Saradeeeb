import {listPublishedArticles} from "../../lib/articles";
export const dynamic="force-dynamic";
export async function GET(){
 const articles=await listPublishedArticles("ar",50);
 const origin=(process.env.NEXT_PUBLIC_SITE_URL??"https://saradeeeb-preview.vercel.app").replace(/\/$/,"");
 const lines=["# سراديب","موقع سراديب للمقالات والقصص والمعرفة.","",...articles.map(a=>"## "+a.title+"\n"+origin+"/ar/article/"+encodeURIComponent(a.slug)+"\n"+(a.excerpt??""))];
 return new Response(lines.join("\n\n"),{headers:{"Content-Type":"text/plain; charset=utf-8","Cache-Control":"public, max-age=0, s-maxage=300"}});
}
