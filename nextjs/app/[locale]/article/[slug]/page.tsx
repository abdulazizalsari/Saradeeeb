import {notFound} from "next/navigation";
import Link from "next/link";
import type {Metadata} from "next";
import {findPublishedArticle} from "../../../../lib/articles";
type Props={params:Promise<{locale:string;slug:string}>};
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,slug}=await params;const a=await findPublishedArticle(locale,slug);return {title:a?.title??"مقال غير موجود | سراديب",description:a?.excerpt??undefined};}
export default async function ArticlePage({params}:Props){const {locale,slug}=await params;const a=await findPublishedArticle(locale,slug);if(!a)notFound();return <main className="container article-migration"><Link href={`/${locale}`}>← العودة إلى المقالات</Link><article><h1>{a.title}</h1><p>{a.author_name} · {a.published_at?new Date(a.published_at).toLocaleDateString(locale):""}</p>{a.excerpt&&<p>{a.excerpt}</p>}<div className="article-body">{a.content.replace(/<[^>]*>/g," ").split(/\n+/).map((line,i)=><p key={i}>{line}</p>)}</div></article></main>}
