import {NextResponse} from "next/server";
import {Pool} from "pg";
export const dynamic="force-dynamic";
export async function GET(){
let database:"not-configured"|"connected"|"unreachable"|"schema-missing"="not-configured";
if(process.env.DATABASE_URL){const pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},connectionTimeoutMillis:2500,max:1});try{await pool.query("SELECT 1");const schema=await pool.query("SELECT to_regclass('public.articles') AS table_name");database=schema.rows[0]?.table_name?"connected":"schema-missing";}catch{database="unreachable";}finally{await pool.end().catch(()=>{});}}
return NextResponse.json({application:"saradeeeb-nextjs",status:"migration-in-progress",database,featureParity:false,productionReady:false},{headers:{"Cache-Control":"no-store"}});
}
