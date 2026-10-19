import Link from "next/link";
import { notFound } from "next/navigation";
const locales = ["ar","en","es","fr","ru"] as const;
export function generateStaticParams() { return locales.map(locale => ({locale})); }
export default async function LocalePage({params}: {params: Promise<{locale:string}>}) { const {locale}=await params; if(!locales.includes(locale as typeof locales[number])) notFound(); return <main className="shell"><header className="top"><Link className="brand" href="/">سراديب</Link></header><section className="hero"><h1>سراديب</h1><p>اللغة المحددة: {locale} — صفحات المقالات ستُربط بقاعدة البيانات في المرحلة التالية.</p><Link className="cta" href="/">الرئيسية</Link></section></main>; }
