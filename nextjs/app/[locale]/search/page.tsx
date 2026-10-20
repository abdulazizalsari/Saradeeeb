import Link from "next/link";
import {notFound} from "next/navigation";
import {searchPublishedArticles} from "../../../lib/articles";
export const dynamic = "force-dynamic";
const locales = new Set(["ar","en","es","fr","ru"]);
export default async function SearchPage({params,searchParams}:{params:Promise<{locale:string}>;searchParams:Promise<{q?:string}>}) {
 const {locale}=await params;
 if(!locales.has(locale))notFound();
 const {q=""}=await searchParams;
 const term=typeof q==="string"?q.trim().slice(0,120):"";
 const results=term?await searchPublishedArticles(locale,term):[];
 return <main className="container" lang={locale} dir={locale==="ar"?"rtl":"ltr"} style={{minHeight:"70vh",paddingBlock:"60px"}}>
  <Link href="/" style={{color:"var(--accent2)"}}>← سراديب</Link>
  <h1>البحث في سراديب</h1>
  <form action={`/${locale}/search`} method="get" role="search" style={{display:"flex",gap:12,maxWidth:700,marginBlock:24}}>
   <input type="search" name="q" defaultValue={term} placeholder="ابحث عن مقال أو موضوع" aria-label="كلمات البحث" style={{flex:1,minWidth:0,padding:14,borderRadius:12,background:"var(--panel)",border:"1px solid var(--line)",color:"var(--text)"}}/>
   <button type="submit" className="btn btn-primary">بحث</button>
  </form>
  {term?<p>نتائج البحث عن «{term}»: {results.length}</p>:<p>أدخل كلمة للبحث في المقالات المنشورة.</p>}
  {term&&results.length===0&&<p>لم نعثر على مقالات تطابق بحثك.</p>}
  <div className="story-grid">{results.map(a=><article className="story-card" key={a.slug}>
   <Link href={`/${locale}/article/${encodeURIComponent(a.slug)}`}>
    {a.featured_image&&<img src={a.featured_image} alt="" loading="lazy" style={{width:"100%",height:190,objectFit:"cover"}}/>}
    <div className="story-body"><h2 style={{fontSize:20}}>{a.title}</h2><p>{a.excerpt??""}</p></div>
   </Link>
  </article>)}</div>
 </main>;
}
