import Link from "next/link";
import {notFound} from "next/navigation";
import {listPublishedArticles} from "../../lib/articles";
const locales=["ar","en","es","fr","ru"];
export function generateStaticParams(){return locales.map(locale=>({locale}));}
export const dynamic="force-dynamic";
export default async function LocalePage({params}:{params:Promise<{locale:string}>}){const {locale}=await params;if(!locales.includes(locale))notFound();const articles=await listPublishedArticles(locale);return <main className="container article-migration"><Link href="/">سراديب</Link><h1>أحدث المقالات</h1>{!process.env.DATABASE_URL?<p>المحتوى غير متاح حتى يتم إعداد اتصال قاعدة البيانات.</p>:articles.length===0?<p>لا توجد مقالات منشورة بهذه اللغة.</p>:<div className="home-grid">{articles.map(a=><Link className="home-section" href={`/${locale}/article/${encodeURIComponent(a.slug)}`} key={a.slug}><h2>{a.title}</h2><p>{a.excerpt}</p></Link>)}</div>}</main>}
