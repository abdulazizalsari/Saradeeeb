import Link from "next/link";
import {notFound} from "next/navigation";
import type {Metadata} from "next";
import {categoryWithArticles} from "../../../../lib/categories";
export const dynamic="force-dynamic";
const locales=new Set(["ar","en","es","fr","ru"]);
export async function generateMetadata({params}:{params:Promise<{locale:string;slug:string}>}):Promise<Metadata>{const {locale,slug}=await params;const data=await categoryWithArticles(locale,slug);return {title:data?data.category.name+" | سراديب":"تصنيف غير موجود | سراديب",description:data?.category.description??undefined,alternates:data?{canonical:"/"+locale+"/category/"+encodeURIComponent(data.category.slug)}:{}};}
export default async function CategoryPage({params,searchParams}:{params:Promise<{locale:string;slug:string}>;searchParams:Promise<{page?:string}>}){
 const {locale,slug}=await params;if(!locales.has(locale))notFound();
 const {page:pageParam}=await searchParams;const page=typeof pageParam==="string"&&/^[1-9]\d{0,3}$/.test(pageParam)?Number(pageParam):1;
 const data=await categoryWithArticles(locale,slug,page);if(!data)notFound();if(data.pages>0&&page>data.pages)notFound();
 return <main className="container article-migration" dir={locale==="ar"?"rtl":"ltr"} lang={locale}>
 <Link href={`/${locale}/categories`}>← جميع التصنيفات</Link>
 <header className="section-head"><div><span className="eyebrow">تصنيفات سراديب</span><h1>{data.category.name}</h1>{data.category.description&&<p>{data.category.description}</p>}</div></header>
 <div className="story-grid">{data.articles.map(a=><article key={a.slug} className="story-card"><Link href={`/${locale}/article/${encodeURIComponent(a.slug)}`}>
 {a.featured_image&&<img src={a.featured_image} alt="" loading="lazy" style={{height:200,width:"100%",objectFit:"cover"}}/>}
 <div className="story-body"><h2>{a.title}</h2><p>{a.excerpt??""}</p></div></Link></article>)}</div>
 {data.articles.length===0&&<p>لا توجد مقالات منشورة في هذا التصنيف بعد.</p>}
 {data.pages>1&&<nav aria-label="صفحات التصنيف" style={{display:"flex",gap:18,alignItems:"center",justifyContent:"center",marginBlock:32}}>{data.page>1&&<Link href={`/${locale}/category/${encodeURIComponent(data.category.slug)}?page=${data.page-1}`}>← السابق</Link>}<span>صفحة {data.page} من {data.pages} · {data.total} مقال</span>{data.page<data.pages&&<Link href={`/${locale}/category/${encodeURIComponent(data.category.slug)}?page=${data.page+1}`}>التالي ←</Link>}</nav>}
 </main>;
}
