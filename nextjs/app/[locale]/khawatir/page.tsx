import Link from "next/link";
import {notFound} from "next/navigation";
import {listKhawatir} from "../../../lib/public-sections";
const locales=new Set(["ar","en","es","fr","ru"]);
export const dynamic="force-dynamic";
export default async function SectionPage({params}:{params:Promise<{locale:string}>}){
 const {locale}=await params;if(!locales.has(locale))notFound();
 const arabic=locale==="ar";const items=await listKhawatir(locale);
 return <main className="container article-migration" lang={locale} dir={arabic?"rtl":"ltr"}>
 <Link href={`/${locale}`}>{arabic?"الرئيسية":"Home"}</Link><h1>{arabic?"خواطر":"Reflections"}</h1>
 {items.length===0?<p>{arabic?"لا يوجد محتوى منشور في هذا القسم حاليًا.":"No published content in this section yet."}</p>:<div className="home-grid">{items.map(k=><article className="home-section" key={k.id}>{k.image_path&&<img src={k.image_path} alt="" loading="lazy" style={{width:"100%",height:"auto"}}/>}{k.slug?<Link href={`/${locale}/khawatir/${encodeURIComponent(k.slug)}`}><p>{k.body}</p></Link>:<p>{k.body}</p>}</article>)}</div>}
 </main>;
}
