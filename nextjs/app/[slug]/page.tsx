import {notFound,permanentRedirect} from "next/navigation";
import {findPublishedArticle} from "../../lib/articles";
export const dynamic="force-dynamic";
export default async function LegacyArticleRoute({params}:{params:Promise<{slug:string}>}){
 const {slug}=await params;
 const article=await findPublishedArticle("ar",slug);
 if(!article)notFound();
 permanentRedirect("/ar/article/"+encodeURIComponent(article.slug));
}
