"use client";
import Link from "next/link";
import {usePathname} from "next/navigation";
import {useEffect,useState} from "react";
const supported=new Set(["ar","en","es","fr","ru"]);
export default function SiteHeader(){
 const pathname=usePathname()||"/";
 const first=pathname.split("/")[1];
 const locale=supported.has(first)?first:"ar";
 const prefix="/"+locale;
 const arabic=locale==="ar";
 const [dark,setDark]=useState(false);
 const [categories,setCategories]=useState<{name:string;slug:string;count:number}[]>([]);
 useEffect(()=>{let active=true;fetch("/api/header-categories?locale="+encodeURIComponent(locale)).then(r=>r.ok?r.json():[]).then(data=>{if(active&&Array.isArray(data))setCategories(data)}).catch(()=>{});return ()=>{active=false}},[locale]);
 const nav=[
 {href:"/",ar:"الرئيسية",en:"Home"},
 
 {href:prefix+"/podcast",ar:"بودكاست سراديب",en:"Podcast"},
 {href:prefix+"/khawatir",ar:"خواطر",en:"Reflections"},
 {href:prefix+"/community",ar:"منتدى سراديب",en:"Community"}
 ];
 return <><a className="skip-link" href="#content">{arabic?"انتقل إلى المحتوى":"Skip to content"}</a>
 <header className="site-header classic-header" data-dark={dark}>
 <div className="container header-inner">
 <Link href="/" className="brand" aria-label={arabic?"سراديب - الرئيسية":"Saradeeeb home"}><span className="brand-mark" aria-hidden="true">س</span><span><strong>سراديب</strong></span></Link>
 <nav className="main-nav" aria-label={arabic?"القائمة الرئيسية":"Main navigation"}>{nav.slice(0,1).map(item=><Link key={item.href} href={item.href} aria-current={pathname===item.href?"page":undefined}>{arabic?item.ar:item.en}</Link>)}<details className="categories-dropdown"><summary>{arabic?"أقسام سراديب":"Categories"} <span aria-hidden="true">▦</span></summary><div className="categories-menu"><Link href={prefix+"/categories"}><span>{arabic?"كل المقالات":"All articles"}</span></Link>{categories.map(category=><Link key={category.slug} href={prefix+"/category/"+encodeURIComponent(category.slug)}><span>{category.name}</span><small>{category.count}</small></Link>)}</div></details>{nav.slice(1).map(item=><Link key={item.href} href={item.href} aria-current={pathname===item.href?"page":undefined}>{arabic?item.ar:item.en}</Link>)}</nav>
 <div className="header-actions">
 <Link className="header-link story-link" href={prefix+"/community"}>{arabic?"أرسل قصتك":"Share your story"}</Link>
 <Link className="header-link account-link" href="/account">{arabic?"حسابي":"My account"}</Link>
 <form className="header-search" action={prefix+"/search"} method="get" role="search"><input type="search" name="q" placeholder={arabic?"ابحث...":"Search..."} aria-label={arabic?"البحث":"Search"}/><button type="submit" aria-label={arabic?"بحث":"Search"}>⌕</button></form>
 <button className="header-icon" type="button" aria-label={arabic?"تبديل لون الهيدر":"Toggle header theme"} aria-pressed={dark} onClick={()=>setDark(v=>!v)}>{dark?"☀":"☾"}</button>
 <details className="mobile-navigation"><summary aria-label={arabic?"فتح القائمة":"Open menu"}>☰</summary><nav aria-label={arabic?"قائمة الهاتف":"Mobile navigation"}>{nav.map(item=><Link key={item.href} href={item.href}>{arabic?item.ar:item.en}</Link>)}<Link href={prefix+"/categories"}>{arabic?"أقسام سراديب":"Categories"}</Link><Link href={prefix+"/community"}>{arabic?"أرسل قصتك":"Share your story"}</Link><Link href="/account">{arabic?"حسابي":"Account"}</Link><Link href={prefix+"/search"}>{arabic?"البحث":"Search"}</Link></nav></details>
 </div></div></header></>;
}
