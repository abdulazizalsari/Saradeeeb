import {notFound} from "next/navigation";
import Link from "next/link";
import type {Metadata} from "next";
import {findPublishedArticle} from "../../../../lib/articles";
type Props={params:Promise<{locale:string;slug:string}>};
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,slug}=await params;const a=await findPublishedArticle(locale,slug);return {title:a?.title??"مقال غير موجود | سراديب",description:a?.excerpt??undefined};}
export default async function ArticlePage({params}:Props){const {locale,slug}=await params;const a=await findPublishedArticle(locale,slug);if(!a)notFound();return <main className="container article-migration"><Link href={`/${locale}`}>← العودة إلى المقالات</Link><article>{a.featured_image&&<img src={a.featured_image} alt={a.title} style={{width:"100%",maxHeight:490,objectFit:"cover",borderRadius:20,marginBlock:"20px"}}/>}<h1>{a.title}</h1><p>{a.author_name} · {a.published_at?new Date(a.published_at).toLocaleDateString(locale):""}</p>{a.excerpt&&<p>{a.excerpt}</p>}<div className="article-body">{a.content.split(/<\/(?:p|h[1-6])>/i).map((block,i)=>{const heading=/<h[1-6][^>]*>/i.test(block);const text=block.replace(/<[^>]*>/g," ").replace(/&nbsp;/g," ").replace(/&amp;/g,"&").trim();return text?(heading?<h2 key={i}>{text}</h2>:<p key={i}>{text}</p>):null;})}</div></article></main>}
