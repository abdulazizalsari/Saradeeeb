import type {MetadataRoute} from "next";
import {listArticleSlugs} from "../lib/articles";
import {listCategories} from "../lib/categories";
import {listPublicPageSlugs} from "../lib/pages";
export const dynamic="force-dynamic";
export default async function sitemap():Promise<MetadataRoute.Sitemap>{
 const origin=(process.env.NEXT_PUBLIC_SITE_URL??"https://saradeeeb-preview.vercel.app").replace(/\/$/,"");
 const items:MetadataRoute.Sitemap=[{url:origin+"/",changeFrequency:"daily",priority:1},{url:origin+"/ar/privacy-policy",changeFrequency:"yearly",priority:.3}];
 for(const locale of ["ar","en","es","fr","ru"]){
  items.push({url:origin+"/"+locale,changeFrequency:"daily",priority:.8});
  try{const categories=await listCategories(locale);if(categories.length){items.push({url:origin+"/"+locale+"/categories",changeFrequency:"weekly",priority:.6});for(const c of categories){items.push({url:origin+"/"+locale+"/category/"+encodeURIComponent(c.slug),changeFrequency:"weekly",priority:.6});}}}catch(e){console.error("sitemap categories error",locale,e);}
  try{for(const slug of await listPublicPageSlugs(locale)){
   items.push({url:origin+"/"+locale+"/"+encodeURIComponent(slug),changeFrequency:"monthly",priority:.4});
  }}catch(e){console.error("sitemap pages error",locale,e);}
  try{for(const article of await listArticleSlugs(locale)){
   items.push({url:origin+"/"+locale+"/article/"+encodeURIComponent(article.slug),lastModified:article.published_at??undefined,changeFrequency:"monthly",priority:.7});
  }}catch(e){console.error("sitemap locale error",locale,e);}
 }
 return items;
}
