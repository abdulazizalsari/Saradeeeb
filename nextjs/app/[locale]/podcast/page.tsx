import Link from "next/link";
import {notFound} from "next/navigation";
import {listPodcastEpisodes} from "../../../lib/public-sections";
const locales=new Set(["ar","en","es","fr","ru"]);
export const dynamic="force-dynamic";
export default async function SectionPage({params}:{params:Promise<{locale:string}>}){
 const {locale}=await params;if(!locales.has(locale))notFound();
 const arabic=locale==="ar";const items=await listPodcastEpisodes(locale);
 return <main className="container article-migration" lang={locale} dir={arabic?"rtl":"ltr"}>
 <Link href={`/${locale}`}>{arabic?"الرئيسية":"Home"}</Link><h1>{arabic?"بودكاست سراديب":"Saradeeeb Podcast"}</h1>
 {items.length===0?<p>{arabic?"لا يوجد محتوى منشور في هذا القسم حاليًا.":"No published content in this section yet."}</p>:<div className="home-grid">{items.map(e=><article className="home-section" key={e.id}>{e.cover_image&&<img src={e.cover_image} alt="" loading="lazy" style={{width:"100%",height:"auto"}}/>}<h2><Link href={`/${locale}/podcast/${encodeURIComponent(e.slug)}`}>{e.title}</Link></h2>{e.excerpt&&<p>{e.excerpt}</p>}{e.audio_url&&/^https?:\/\//i.test(e.audio_url)&&<audio controls preload="none" src={e.audio_url} style={{width:"100%"}}/>}{e.duration&&<p>{e.duration}</p>}</article>)}</div>}
 </main>;
}
