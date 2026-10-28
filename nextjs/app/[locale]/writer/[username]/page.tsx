import Link from "next/link";
import {notFound} from "next/navigation";
import type {Metadata} from "next";
import {publicWriter} from "../../../../lib/writers";
import "./writer-profile.css";
type Props={params:Promise<{locale:string;username:string}>};
export const dynamic="force-dynamic";
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,username}=await params;const w=await publicWriter(username,locale);return {title:w?w.name+" | كتّاب سراديب":"كاتب غير موجود | سراديب",description:w?.bio??"الصفحة الأدبية للكاتب في سراديب"};}
export default async function WriterProfile({params}:Props){
 const {locale,username}=await params;
 if(!["ar","en","es","fr","ru"].includes(locale))notFound();
 const writer=await publicWriter(username,locale);if(!writer)notFound();
 return <main className="writer-public" dir={locale==="ar"?"rtl":"ltr"}>
 <div className="writer-public-wrap"><Link href={"/"+locale} className="writer-back">← سراديب</Link>
 <section className="writer-hero"><div className="writer-avatar">{writer.avatar?<img src={writer.avatar} alt={writer.name}/>:<span>{writer.name.charAt(0)}</span>}</div><div><span className="writer-eyebrow">المساحة الأدبية • سراديب</span><h1>{writer.name}</h1><p>{writer.bio||"كاتب في منصة سراديب"}</p></div></section>
 <section className="writer-works"><div className="writer-works-head"><div><span className="writer-eyebrow">من أعمال الكاتب</span><h2>المقالات المنشورة</h2></div><span className="writer-count">{writer.articles.length} مقالًا</span></div>
 {writer.articles.length?<div className="writer-article-grid">{writer.articles.map(a=><article className="writer-article" key={a.slug}><Link href={`/${locale}/article/${encodeURIComponent(a.slug)}`}>{a.featured_image?<img src={a.featured_image} alt="" loading="lazy"/>:<div className="writer-article-placeholder">سراديب</div>}<div className="writer-article-copy"><h3>{a.title}</h3>{a.excerpt&&<p>{a.excerpt}</p>}<span>اقرأ المقال ←</span></div></Link></article>)}</div>:<p className="writer-empty">لا توجد مقالات منشورة بهذه اللغة حتى الآن.</p>}
 </section></div></main>;
}
