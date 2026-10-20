import Link from "next/link";
import {listCategories} from "../../../lib/categories";
import {notFound} from "next/navigation";
export const dynamic="force-dynamic";
const locales=new Set(["ar","en","es","fr","ru"]);
export default async function CategoriesPage({params}:{params:Promise<{locale:string}>}){
 const {locale}=await params;if(!locales.has(locale))notFound();
 const categories=await listCategories(locale);
 return <main className="container article-migration" lang={locale} dir={locale==="ar"?"rtl":"ltr"}>
 <Link href={`/${locale}`}>← سراديب</Link>
 <header className="section-head"><div><span className="eyebrow">سراديب</span><h1>التصنيفات</h1></div></header>
 <div className="story-grid">{categories.map(c=><article key={c.id} className="story-card"><Link href={`/${locale}/category/${encodeURIComponent(c.slug)}`}><div className="story-body"><h2>{c.name}</h2>{c.description&&<p>{c.description}</p>}</div></Link></article>)}</div>
 {categories.length===0&&<p>لا توجد تصنيفات منشورة بهذه اللغة.</p>}
 </main>;
}
