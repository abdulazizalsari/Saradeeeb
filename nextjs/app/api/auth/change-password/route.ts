import {NextRequest,NextResponse} from "next/server";
import {database,hashPassword,verifyPassword,verifySession} from "../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 const id=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!id)return NextResponse.json({error:"Authentication required"},{status:401});
 try{
  const body=await req.json();const current=String(body.currentPassword??"");const next=String(body.newPassword??"");
  if(!current||current.length>128||next.length<12||next.length>128||current===next)return NextResponse.json({error:"Invalid password change"},{status:400});
  const db=database();const result=await db.query<{password_hash:string}>("SELECT password_hash FROM web_accounts WHERE id=$1 LIMIT 1",[id]);
  if(!result.rows[0]||!verifyPassword(current,result.rows[0].password_hash))return NextResponse.json({error:"Current password is incorrect"},{status:403});
  const updated=await db.query("UPDATE web_accounts SET password_hash=$1 WHERE id=$2 AND password_hash=$3",[hashPassword(next),id,result.rows[0].password_hash]);
  if(updated.rowCount!==1)return NextResponse.json({error:"Please retry password change"},{status:409});
  const response=NextResponse.json({ok:true,reauthenticationRequired:true},{headers:{"Cache-Control":"no-store"}});response.cookies.set("saradeeeb_web_session","",{httpOnly:true,secure:process.env.NODE_ENV==="production",sameSite:"lax",path:"/",maxAge:0});return response;
 }catch{return NextResponse.json({error:"Password change unavailable"},{status:503});}
}
