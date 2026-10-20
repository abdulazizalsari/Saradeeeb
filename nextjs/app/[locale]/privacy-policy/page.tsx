import Link from "next/link";
import {notFound} from "next/navigation";
export const metadata={title:"سياسة الخصوصية | سراديب",description:"نحترم خصوصيتك ونلتزم بالتعامل مع بياناتك بوضوح ومسؤولية."};
const sections=[
["البيانات التي نجمعها","قد نجمع الاسم والبريد الإلكتروني ومحتوى الرسائل أو المشاركات التي تقدمها طوعًا."],
["كيف نستخدم البيانات","نستخدم البيانات لتشغيل الموقع وإدارة الحسابات والمشاركات والرد على الرسائل وتحسين الأمان."],
["ملفات الارتباط","نستخدم ملفات الارتباط الضرورية لتشغيل الموقع وحفظ تفضيلاتك، والاختيارية بعد موافقتك."],
["حقوقك","يمكنك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها، وإلغاء الاشتراك في النشرة البريدية في أي وقت."],
["تحديث السياسة","قد نحدّث هذه السياسة عند تغير الخدمات أو المتطلبات القانونية، وسنعرض تاريخ آخر تحديث أسفل الصفحة."]
];
export default async function PrivacyPolicy({params}:{params:Promise<{locale:string}>}){
 const {locale}=await params;if(locale!=="ar")notFound();
 return <main lang="ar" dir="rtl"><section className="page-hero privacy-policy-hero"><div className="container"><span className="eyebrow">Saradeeeb</span><h1>سياسة الخصوصية</h1><p>نحترم خصوصيتك ونلتزم بالتعامل مع بياناتك بوضوح ومسؤولية.</p></div></section>
 <section className="section privacy-policy-page"><div className="container privacy-policy-card">
 {sections.map(([title,body])=><article className="privacy-policy-section" key={title}><h2>{title}</h2><p>{body}</p></article>)}
 <section className="privacy-copyright-card" lang="ar" dir="rtl" aria-labelledby="privacy-copyright-title"><div className="privacy-copyright-icon" aria-hidden="true">♢</div><h2 id="privacy-copyright-title">حماية الإبداع والحقوق الأدبية</h2><p>تؤمن سراديب أن الكلمة أمانة، وأن الإبداع حقٌّ لصاحبه. ولا نتسامح مع انتحال الأعمال الأدبية أو نسخ القصص والمقالات ونسبتها إلى غير أصحابها.</p><p>إذا اكتشفت أن قصتك المنشورة في سراديب قد سُرقت أو نُشرت دون إذنك، أو وجدت محتوى في سراديب تعتقد أنه ينتهك حقوقك الأدبية، فيمكنك إبلاغ الإدارة مع تقديم الروابط والأدلة المتاحة.</p><p>سنراجع البلاغ بعناية ونتخذ الإجراء المناسب ضمن صلاحياتنا، مع احترام حقوق جميع الأطراف.</p><Link className="btn btn-primary" href="/ar/contact">الإبلاغ عن سرقة قصة أو انتهاك حقوق النشر</Link></section>
 <p className="privacy-policy-updated">آخر تحديث: 11 أكتوبر 2026</p>
 </div></section></main>;
}
