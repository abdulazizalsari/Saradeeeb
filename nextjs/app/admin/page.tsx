import {cookies} from "next/headers";
import {redirect} from "next/navigation";
import Link from "next/link";
import {database,verifySession} from "../../lib/auth";
export const dynamic="force-dynamic";
export const metadata={title:"لوحة إدارة سراديب",robots:{index:false,follow:false}};
export default async function AdminPage(){
 const cookieStore=await cookies();const id=verifySession(cookieStore.get("saradeeeb_web_session")?.value);
 if(!id)redirect("/admin/login");
 let admin:null|{username:string}=null;
 try{const result=await database().query<{username:string}>("SELECT username FROM web_accounts WHERE id=$1 AND role='admin'",[id]);admin=result.rows[0]??null;}catch{return <main dir="rtl" style={{maxWidth:700,margin:"80px auto",padding:24}}><h1>تعذّر الاتصال بقاعدة البيانات</h1><p>تحقق من إعدادات الاتصال قبل محاولة الدخول.</p></main>;}
 if(!admin)redirect("/admin/login");
 const result=await database().query<{articles:string;topics:string;replies:string}>(`SELECT (SELECT count(*)::text FROM articles) AS articles,(SELECT count(*)::text FROM web_community_topics) AS topics,(SELECT count(*)::text FROM web_community_replies) AS replies`);
 const stats=result.rows[0];
 return <main dir="rtl" style={{maxWidth:1000,margin:"40px auto",padding:24}}><h1>لوحة إدارة سراديب</h1><p>مرحبًا، {admin.username}</p><section style={{display:"flex",gap:16,flexWrap:"wrap"}}><article style={{padding:24,border:"1px solid #aaa",borderRadius:12}}><h2>المقالات</h2><strong>{stats.articles}</strong></article><article style={{padding:24,border:"1px solid #aaa",borderRadius:12}}><h2>مواضيع المنتدى</h2><strong>{stats.topics}</strong></article><article style={{padding:24,border:"1px solid #aaa",borderRadius:12}}><h2>ردود المنتدى</h2><strong>{stats.replies}</strong></article></section><p><Link href="/ar/community">المنتدى</Link> · <Link href="/ar">الموقع</Link></p><p>هذه بداية لوحة الإدارة المحمية. تحرير المقالات وإدارة المستخدمين والصلاحيات التفصيلية قيد الاستكمال.</p></main>;
}