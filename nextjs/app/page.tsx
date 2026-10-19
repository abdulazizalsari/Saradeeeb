import Link from "next/link";
const sections=[
{title:"المقالات",description:"قصص ومعرفة وتحليلات وملفات مختارة.",href:"/ar"},
{title:"بودكاست سراديب",description:"حلقات صوتية مختارة من روح الموقع.",href:"/ar/podcast"},
{title:"خواطر",description:"نصوص قصيرة وتأملات وحِكم.",href:"/ar/khawatir"},
{title:"منتدى سراديب",description:"نقاشات واهتمامات ومساحات للمشاركة.",href:"/ar/community"}
];
export default function HomePage(){return <><a className="skip-link" href="#content">انتقل إلى المحتوى</a><header className="site-header"><div className="container header-inner"><Link href="/" className="brand"><span className="brand-mark">س</span><span><strong>سراديب</strong><small>ما وراء المألوف</small></span></Link><nav className="main-nav" aria-label="القائمة الرئيسية"><Link href="/ar">المقالات</Link><Link href="/ar/podcast">البودكاست</Link><Link href="/ar/khawatir">خواطر</Link><Link href="/ar/community">المنتدى</Link></nav></div></header><main id="content" className="container"><section className="home-intro"><p className="home-kicker">سراديب</p><h1>حكايات وأسرار ومعرفة تتجاوز المألوف</h1><p>اكتشف أقسام سراديب، من المقالات إلى البودكاست والخواطر والمجتمع.</p></section><section className="home-sections" aria-labelledby="sections-heading"><h2 id="sections-heading">أقسام سراديب</h2><div className="home-grid">{sections.map(section=><Link href={section.href} key={section.title} className="home-section"><h3>{section.title}</h3><p>{section.description}</p></Link>)}</div></section></main><footer className="container home-footer">سراديب</footer></>}
