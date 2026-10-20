"use client";
import Link from "next/link";
import {usePathname} from "next/navigation";

const supported=new Set(["ar","en","es","fr","ru"]);
export default function SiteHeader(){
 const pathname=usePathname()||"/";
 const first=pathname.split("/")[1];
 const locale=supported.has(first)?first:"ar";
 const prefix="/"+locale;
 const nav=[{href:"/",ar:"الرئيسية",en:"Home"},{href:prefix,ar:"المقالات",en:"Articles"},{href:prefix+"/podcast",ar:"البودكاست",en:"Podcast"},{href:prefix+"/khawatir",ar:"خواطر",en:"Reflections"},{href:prefix+"/community",ar:"المنتدى",en:"Community"}];
 const arabic=locale==="ar";
 return <><a className="skip-link" href="#content">{arabic?"انتقل إلى المحتوى":"Skip to content"}</a>
 <header className="site-header"><div className="container header-inner">
 <Link href="/" className="brand" aria-label={arabic?"سراديب - الرئيسية":"Saradeeeb home"}><span className="brand-mark" aria-hidden="true">س</span><span><strong>سراديب</strong><small>ما وراء المألوف</small></span></Link>
 <nav className="main-nav" aria-label={arabic?"القائمة الرئيسية":"Main navigation"}>{nav.map(item=><Link key={item.href} href={item.href} aria-current={pathname===item.href?"page":undefined}>{arabic?item.ar:item.en}</Link>)}</nav>
 <div className="header-actions"><form className="mini-search" action={prefix+"/search"} method="get" role="search"><input type="search" name="q" placeholder={arabic?"ابحث في سراديب":"Search Saradeeeb"} aria-label={arabic?"البحث في سراديب":"Search"}/><button type="submit" aria-label={arabic?"بحث":"Search"}>⌕</button></form>
 <Link className="icon-btn" href="/account" aria-label={arabic?"حسابي":"Account"} title={arabic?"حسابي":"Account"}>♙</Link>
 <details className="mobile-navigation"><summary aria-label={arabic?"فتح القائمة":"Open menu"}>☰</summary><nav aria-label={arabic?"قائمة الهاتف":"Mobile navigation"}>{nav.map(item=><Link key={item.href} href={item.href}>{arabic?item.ar:item.en}</Link>)}<Link href={prefix+"/search"}>{arabic?"البحث":"Search"}</Link><Link href="/account">{arabic?"حسابي":"Account"}</Link></nav></details>
 </div></div></header></>;
}
