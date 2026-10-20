import Link from "next/link";
import {notFound} from "next/navigation";

const locales = ["ar", "en", "es", "fr", "ru"];

export default async function PodcastPage({params}:{params:Promise<{locale:string}>}) {
  const {locale} = await params;
  if (!locales.includes(locale)) notFound();
  const arabic = locale === "ar";
  return <main className="container article-migration" lang={locale} dir={arabic ? "rtl" : "ltr"}>
    <Link href={`/${locale}`}>سراديب</Link>
    <h1>{arabic ? "بودكاست سراديب" : "Saradeeeb Podcast"}</h1>
    <p>{arabic ? "حلقات صوتية مختارة من روح الموقع. ستظهر الحلقات المنشورة هنا عند اكتمال ربط مكتبة الصوت." : "Selected audio episodes from Saradeeeb."}</p>
    <Link href={`/${locale}`}>{arabic ? "العودة إلى المقالات" : "Back to articles"}</Link>
  </main>;
}
