import Link from "next/link";
import {notFound} from "next/navigation";
import type {Metadata} from "next";
import {findPublicPage} from "../../../lib/pages";
import {cleanArticleHtml} from "../../../lib/article-html";
type Props={params:Promise<{locale:string;slug:string}>};
export const dynamic="force-dynamic";
export async function generateMetadata({params}:Props):Promise<Metadata>{
 const {locale,slug}=await params;
 const page=await findPublicPage(locale,slug);
 if(!page)return {title:"صفحة غير موجودة | سراديب"};
 return {title:page.seo_title||page.title,description:page.seo_description||undefined,alternates:{canonical:"/"+locale+"/"+encodeURIComponent(page.slug)}};
}
export default async function PublicPage({params}:Props){
 const {locale,slug}=await params;
 const page=await findPublicPage(locale,slug);
 if(!page)notFound();

 return <main className="container article-migration" lang={locale} dir={locale==="ar"?"rtl":"ltr"}>
 <Link href={"/"+locale}>← سراديب</Link>
 <article>{page.featured_image&&<img src={page.featured_image} alt="" loading="lazy" style={{maxWidth:"100%",height:"auto"}}/>}
 {page.show_title&&<h1>{page.title}</h1>}
 <div className="article-body" dangerouslySetInnerHTML={{__html:cleanArticleHtml(page.content)}}/></article>
 </main>;
}
