import Link from "next/link";
import {notFound} from "next/navigation";

const locales = ["ar", "en", "es", "fr", "ru"];

export default async function CommunityPage({params}:{params:Promise<{locale:string}>}) {
  const {locale} = await params;
  if (!locales.includes(locale)) notFound();
  const arabic = locale === "ar";
  return <main className="container article-migration" lang={locale} dir={arabic ? "rtl" : "ltr"}>
    <Link href={`/${locale}`}>سراديب</Link>
    <h1>{arabic ? "منتدى سراديب" : "Saradeeeb Community"}</h1>
    <p>{arabic ? "مساحة للنقاش والمشاركة. تسجيل الدخول مطلوب قبل إنشاء المشاركات أو التعليقات." : "A space for discussion and participation. Sign in before creating posts or comments."}</p>
    <div className="home-grid"><Link className="home-section" href="/account"><h2>{arabic ? "تسجيل الدخول" : "Sign in"}</h2></Link><Link className="home-section" href={`/${locale}`}><h2>{arabic ? "استكشاف المقالات" : "Explore articles"}</h2></Link></div>
  </main>;
}
