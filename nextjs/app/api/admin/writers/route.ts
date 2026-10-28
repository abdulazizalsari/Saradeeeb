import {NextRequest,NextResponse} from "next/server";
import {database,verifySession} from "../../../../lib/auth";
export const runtime="nodejs";
function sameOrigin(req:NextRequest){
 const origin=req.headers.get("origin");
 try{return !!origin&&new URL(origin).origin===req.nextUrl.origin}catch{return false}
}
export async function POST(req:NextRequest){
 if(!sameOrigin(req))return NextResponse.json({error:"Invalid origin"},{status:403});
 const adminId=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!adminId)return NextResponse.json({error:"Unauthorized"},{status:401});
 try{
  const body=await req.json();
  const accountId=String(body.accountId??"");
  const role=String(body.role??"");
  if(!/^\d+$/.test(accountId)||!["writer","member"].includes(role))
   return NextResponse.json({error:"Invalid request"},{status:400});
  if(accountId===adminId)return NextResponse.json({error:"Cannot change own role"},{status:403});
  const client=await database().connect();
  try{
   await client.query("BEGIN");
   const actor=await client.query<{role:string}>("SELECT role FROM web_accounts WHERE id=$1 FOR UPDATE",[adminId]);
   if(actor.rows[0]?.role!=="admin"){await client.query("ROLLBACK");return NextResponse.json({error:"Forbidden"},{status:403})}
   const changed=await client.query<{id:string;username:string;role:string}>(
    "UPDATE web_accounts SET role=$1 WHERE id=$2 AND role IN ('member','writer') RETURNING id,username,role",[role,accountId]);
   if(!changed.rows[0]){await client.query("ROLLBACK");return NextResponse.json({error:"Account not found or role protected"},{status:409})}
   await client.query("COMMIT");
   return NextResponse.json({account:changed.rows[0]});
  }catch(e){await client.query("ROLLBACK");throw e}finally{client.release()}
 }catch{return NextResponse.json({error:"Service unavailable"},{status:503})}
}
