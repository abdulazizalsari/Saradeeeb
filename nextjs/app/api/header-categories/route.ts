import {NextRequest,NextResponse} from "next/server";
import {Pool} from "pg";
export const dynamic="force-dynamic";
export async function GET(req:NextRequest){
 const locale=req.nextUrl.searchParams.get("locale")??"ar";
 if(!["ar","en","es","fr","ru"].includes(locale))return NextResponse.json([],{status:400});
 if(!process.env.DATABASE_URL)return NextResponse.json([]);
 const pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:process.env.DATABASE_SSL_INSECURE==="true"?{rejectUnauthorized:false}:{rejectUnauthorized:true},max:1,connectionTimeoutMillis:5000});
 try{
  const result=await pool.query<{name:string;slug:string;count:string}>(`SELECT t.name,t.slug,COUNT(DISTINCT a.id)::text AS count
 FROM categories c JOIN category_translations t ON t.category_id=c.id
 LEFT JOIN article_categories ac ON ac.category_id=c.id
 LEFT JOIN articles a ON a.id=ac.article_id AND a.status='published'
 LEFT JOIN article_translations at ON at.article_id=a.id AND at.locale=$1 AND at.status='published'
 WHERE c.active=1 AND t.locale=$1 AND t.status='published'
 GROUP BY c.id,t.name,t.slug,c.sort_order ORDER BY c.sort_order,c.id`,[locale]);
  return NextResponse.json(result.rows.map(r=>({name:r.name,slug:r.slug,count:Number(r.count)})),{headers:{"Cache-Control":"public, max-age=60"}});
 }catch{return NextResponse.json([])}finally{await pool.end()}
}
