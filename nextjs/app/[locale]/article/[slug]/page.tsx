import {notFound} from "next/navigation";
import Link from "next/link";
import type {Metadata} from "next";
import {findPublishedArticle} from "../../../../lib/articles";
type Props={params:Promise<{locale:string;slug:string}>};
export const dynamic="force-dynamic";
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,slug}=await params;const a=await findPublishedArticle(locale,slug);return {title:a?.title? a.title+" | سراديب":"مقال غير موجود | سراديب",description:a?.excerpt??undefined,openGraph:a?{title:a.title,images:a.featured_image?[a.featured_image]:[]}:undefined};}
function paragraphs(html:string){return html.replace(/<\/(p|div|h[1-6]|li|blockquote)>/gi,"\n\n").replace(/<br\s*\/?\s*>/gi,"\n").replace(/<[^>]*>/g," ").replace(/&nbsp;/g," ").replace(/&amp;/g,"&").replace(/&quot;/g,'"').split(/\n\s*\n/).map(x=>x.replace(/\s+/g," ").trim()).filter(Boolean)}
export default async function ArticlePage({params}:Props){const {locale,slug}=await params;const a=await findPublishedArticle(locale,slug);if(!a)notFound();return <main className="container article-migration article-detail" dir="rtl"><nav className="archive-breadcrumb"><Link href="/">الرئيسية</Link> / <Link href={`/${locale}`}>الأرشيف</Link> / {a.category||"مقال"}</nav><article><h1>{a.title}</h1><p className="article-meta">{a.author_name} {a.published_at?" · "+new Date(a.published_at).toLocaleDateString(locale):""}</p>{a.featured_image&&<img className="article-hero-image" src={a.featured_image} alt={a.title}/>} {a.excerpt&&<p className="article-excerpt">{a.excerpt}</p>}<div className="article-body">{paragraphs(a.content).map((line,i)=><p key={i}>{line}</p>)}</div></article><p><Link href={`/${locale}`}>← العودة إلى أرشيف المقالات</Link></p></main>}
