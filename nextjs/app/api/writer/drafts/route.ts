import {NextRequest,NextResponse} from "next/server";
import {database,verifySession} from "../../../../lib/auth";
export const runtime="nodejs";
const roles=["writer","trusted_writer","editor","admin"];
async function owner(req:NextRequest){
 const id=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!id)return null;
 const r=await database().query<{id:string;role:string}>("SELECT id,role FROM web_accounts WHERE id=$1",[id]);
 return r.rows[0]&&roles.includes(r.rows[0].role)?r.rows[0]:null;
}
function originOk(req:NextRequest){const origin=req.headers.get("origin");try{return !origin||new URL(origin).host===req.nextUrl.host}catch{return false}}
export async function GET(req:NextRequest){
 try{const user=await owner(req);if(!user)return NextResponse.json({error:"Forbidden"},{status:403});
 const r=await database().query("SELECT id,title,body,status,updated_at FROM writer_drafts WHERE writer_account_id=$1 ORDER BY updated_at DESC LIMIT 100",[user.id]);
 return NextResponse.json({drafts:r.rows},{headers:{"Cache-Control":"no-store"}});
 }catch{return NextResponse.json({error:"Service unavailable"},{status:503})}
}
export async function POST(req:NextRequest){
 if(!originOk(req))return NextResponse.json({error:"Invalid origin"},{status:403});
 try{const user=await owner(req);if(!user)return NextResponse.json({error:"Forbidden"},{status:403});
 const data=await req.json();const title=String(data.title??"").trim(),body=String(data.body??"").trim();
 if(title.length<3||title.length>240||body.length>100000)return NextResponse.json({error:"Invalid draft"},{status:400});
 const r=await database().query("INSERT INTO writer_drafts(writer_account_id,title,body) VALUES($1,$2,$3) RETURNING id,title,body,status,updated_at",[user.id,title,body]);
 return NextResponse.json({draft:r.rows[0]},{status:201});
 }catch{return NextResponse.json({error:"Service unavailable"},{status:503})}
}
