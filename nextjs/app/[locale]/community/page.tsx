import CommunityComposer from "./CommunityComposer";
import Link from "next/link";
import {notFound} from "next/navigation";
import {listCommunityTopics} from "../../../lib/public-sections";
const locales=new Set(["ar","en","es","fr","ru"]);
export const dynamic="force-dynamic";
export default async function SectionPage({params}:{params:Promise<{locale:string}>}){
 const {locale}=await params;if(!locales.has(locale))notFound();
 const arabic=locale==="ar";const items=await listCommunityTopics(locale);
 return <main className="container article-migration" lang={locale} dir={arabic?"rtl":"ltr"}>
 <CommunityComposer locale={locale}/>
 <Link href={`/${locale}`}>{arabic?"الرئيسية":"Home"}</Link><h1>{arabic?"منتدى سراديب":"Saradeeeb Community"}</h1>
 {items.length===0?<p>{arabic?"لا يوجد محتوى منشور في هذا القسم حاليًا.":"No published content in this section yet."}</p>:<div className="home-grid">{items.map(t=><article className="home-section" key={t.id}><h2><Link href={`/${locale}/community/${encodeURIComponent(t.slug)}`}>{t.title}</Link></h2>{t.excerpt&&<p>{t.excerpt}</p>}<small>{t.replies_count} {arabic?"ردود":"replies"}</small></article>)}</div>}
 </main>;
}
