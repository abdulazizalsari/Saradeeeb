import Link from "next/link";
import {notFound} from "next/navigation";
import type {Metadata} from "next";
import {findKhatera} from "../../../../lib/public-sections";
type Props={params:Promise<{locale:string;slug:string}>};
export const dynamic="force-dynamic";
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,slug}=await params;const item=await findKhatera(locale,slug);return {title:item?item.body.slice(0,65)+" | سراديب":"خاطرة غير موجودة | سراديب",alternates:item?{canonical:`/${locale}/khawatir/${encodeURIComponent(item.slug||slug)}`}:undefined,robots:item?undefined:{index:false}};}
export default async function KhateraPage({params}:Props){const {locale,slug}=await params;const item=await findKhatera(locale,slug);if(!item)notFound();return <main id="content" className="container article-migration" lang={locale} dir={locale==="ar"?"rtl":"ltr"}><Link href={`/${locale}/khawatir`}>{locale==="ar"?"← العودة إلى الخواطر":"← Back to reflections"}</Link><article>{item.image_path&&<img src={item.image_path} alt="" loading="lazy" style={{maxWidth:"100%",height:"auto"}}/>}<blockquote style={{whiteSpace:"pre-wrap",fontSize:"1.3rem"}}>{item.body}</blockquote></article></main>;}
