import Link from "next/link";
import {notFound} from "next/navigation";

const locales = ["ar", "en", "es", "fr", "ru"];

export default async function KhawatirPage({params}:{params:Promise<{locale:string}>}) {
  const {locale} = await params;
  if (!locales.includes(locale)) notFound();
  const arabic = locale === "ar";
  return <main className="container article-migration" lang={locale} dir={arabic ? "rtl" : "ltr"}>
    <Link href={`/${locale}`}>سراديب</Link>
    <h1>{arabic ? "خواطر" : "Reflections"}</h1>
    <p>{arabic ? "نصوص قصيرة وتأملات وحِكم من أرشيف سراديب." : "Short reflections and meditations from the Saradeeeb archive."}</p>
    <Link href={`/${locale}`}>{arabic ? "العودة إلى المقالات" : "Back to articles"}</Link>
  </main>;
}
