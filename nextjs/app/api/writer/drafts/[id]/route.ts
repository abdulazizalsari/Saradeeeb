import {NextRequest,NextResponse} from "next/server";
import {database,verifySession} from "../../../../../lib/auth";
export const runtime="nodejs";
const roles=["writer","trusted_writer","editor","admin"];
function originOk(req:NextRequest){const origin=req.headers.get("origin");try{return !origin||new URL(origin).host===req.nextUrl.host}catch{return false}}
export async function PATCH(req:NextRequest,{params}:{params:Promise<{id:string}>}){
 if(!originOk(req))return NextResponse.json({error:"Invalid origin"},{status:403});
 try{
 const accountId=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!accountId)return NextResponse.json({error:"Unauthorized"},{status:401});
 const user=await database().query<{role:string}>("SELECT role FROM web_accounts WHERE id=$1",[accountId]);
 if(!user.rows[0]||!roles.includes(user.rows[0].role))return NextResponse.json({error:"Forbidden"},{status:403});
 const {id}=await params;if(!/^\d+$/.test(id))return NextResponse.json({error:"Invalid id"},{status:400});
 const data=await req.json();const title=String(data.title??"").trim(),body=String(data.body??"").trim(),submit=data.submit===true;
 if(title.length<3||title.length>240||body.length>100000)return NextResponse.json({error:"Invalid draft"},{status:400});
 const r=await database().query(
  "UPDATE writer_drafts SET title=$1,body=$2,status=CASE WHEN $3 THEN 'submitted' ELSE 'draft' END,updated_at=now() WHERE id=$4 AND writer_account_id=$5 AND status IN ('draft','changes_requested') RETURNING id,title,body,status,updated_at",
  [title,body,submit,id,accountId]);
 if(!r.rows[0])return NextResponse.json({error:"Draft not found or locked for review"},{status:409});
 return NextResponse.json({draft:r.rows[0]});
 }catch{return NextResponse.json({error:"Service unavailable"},{status:503})}
}
