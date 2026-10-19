import {redirect} from "next/navigation";
export default function AdminEntry(){
 const origin=process.env.PHP_ADMIN_ORIGIN;
 if(origin&&/^https:\/\/[a-z0-9.-]+(?::\d+)?\/?$/i.test(origin)){redirect(origin.replace(/\/$/,"")+"/admin");}
 return <main style={{maxWidth:680,margin:"80px auto",padding:24}} dir="rtl"><h1>لوحة الإدارة غير متاحة حاليًا</h1><p>لم يُضبط خادم الإدارة المتوافق مع PHP بعد. لا يتم توجيه المستخدمين إلى لوحة وهمية أو غير آمنة.</p></main>;
}
