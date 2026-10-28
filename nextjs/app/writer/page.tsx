import {cookies} from "next/headers";
import {redirect} from "next/navigation";
import Link from "next/link";
import WriterEditor from "./editor";
import {database,verifySession} from "../../lib/auth";

export const dynamic="force-dynamic";
export const metadata={title:"لوحة الكاتب | سراديب",robots:{index:false,follow:false}};
type Writer={id:string;username:string;role:string};
type Draft={id:string;title:string;body:string;status:string;updated_at:string};

export default async function WriterDashboard(){
 const store=await cookies();
 const id=verifySession(store.get("saradeeeb_web_session")?.value);
 if(!id)redirect("/account");
 let writer:Writer|undefined;
 let drafts:Draft[]=[];
 try{
  const result=await database().query<Writer>("SELECT id,username,role FROM web_accounts WHERE id=$1",[id]);
  writer=result.rows[0];
  if(!writer)throw new Error("Account missing");
  if(!["writer","trusted_writer","editor","admin"].includes(writer.role))
   return <main dir="rtl" className="container"><h1>قسم الكاتب</h1><p>حسابك مسجل، لكن صلاحية الكاتب لم تُفعّل بعد. تواصل مع الإدارة لطلب التفعيل.</p><Link href="/account">حسابي</Link></main>;
  const resultDrafts=await database().query<Draft>(
   "SELECT id,title,body,status,updated_at FROM writer_drafts WHERE writer_account_id=$1 ORDER BY updated_at DESC LIMIT 100",[id]);
  drafts=resultDrafts.rows;
 }catch{
  return <main dir="rtl" className="container"><h1>قسم الكاتب</h1><p>القسم غير متاح مؤقتًا. تحقق من تطبيق ترحيل قاعدة البيانات واتصالها.</p></main>;
 }
 return <main dir="rtl" className="container" style={{paddingBlock:40}}>
  <h1>لوحة الكاتب</h1><p>مرحبًا {writer?.username}</p>
  <p>المسودات: {drafts.filter(d=>d.status==="draft").length} · قيد المراجعة: {drafts.filter(d=>d.status==="submitted").length}</p>
  <WriterEditor initial={drafts.map(d=>({...d,updated_at:String(d.updated_at)}))}/>
  <Link href="/account">إدارة حسابي</Link>
 </main>;
}
