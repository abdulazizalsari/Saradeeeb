"use client";
import Link from "next/link";
import {usePathname} from "next/navigation";
const locales=new Set(["ar","en","es","fr","ru"]);
export default function SiteFooter(){
 const pathname=usePathname()||"/";const segment=pathname.split("/")[1];
 const locale=locales.has(segment)?segment:"ar";const prefix="/"+locale;const arabic=locale==="ar";
 return <footer className="site-footer" lang={locale} dir={arabic?"rtl":"ltr"}><div className="container">
 <div className="footer-grid"><div><Link href="/" className="brand" aria-label="Saradeeeb"><span className="brand-mark" aria-hidden="true">س</span><span><strong>سراديب</strong><small>ما وراء المألوف</small></span></Link><p>{arabic?"قصص وغموض وتاريخ ومعرفة، ومساحة لاكتشاف ما وراء المألوف.":"Stories, history, mysteries and ideas beyond the ordinary."}</p></div>
 <div><h3>{arabic?"استكشف سراديب":"Explore"}</h3><Link href="/">{arabic?"الرئيسية":"Home"}</Link><Link href={prefix}>{arabic?"المقالات":"Articles"}</Link><Link href={prefix+"/podcast"}>{arabic?"البودكاست":"Podcast"}</Link><Link href={prefix+"/khawatir"}>{arabic?"خواطر":"Reflections"}</Link></div>
 <div><h3>{arabic?"المجتمع والحساب":"Community & account"}</h3><Link href={prefix+"/community"}>{arabic?"منتدى سراديب":"Community"}</Link><Link href="/account">{arabic?"حسابي":"My account"}</Link><Link href={prefix+"/search"}>{arabic?"البحث":"Search"}</Link><Link href={prefix+"/privacy-policy"}>{arabic?"سياسة الخصوصية":"Privacy policy"}</Link></div></div>
 <div className="copyright">© {new Date().getFullYear()} {arabic?"سراديب — جميع الحقوق محفوظة.":"Saradeeeb — All rights reserved."}</div>
 </div></footer>;
}
