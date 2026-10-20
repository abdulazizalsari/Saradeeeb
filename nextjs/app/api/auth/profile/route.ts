import {NextRequest,NextResponse} from "next/server";
import {database,verifyPassword,verifySession} from "../../../../lib/auth";
export const runtime="nodejs";
export async function PATCH(req:NextRequest){
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 const id=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!id)return NextResponse.json({error:"Authentication required"},{status:401});
 try{
  const body=await req.json();const email=String(body.email??"").trim().toLowerCase();const username=String(body.username??"").trim();const password=String(body.currentPassword??"");
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||email.length>254||!/^[\p{L}\p{N}_-]{3,40}$/u.test(username)||!password||password.length>128)return NextResponse.json({error:"Invalid profile details"},{status:400});
  const db=database();const account=await db.query<{password_hash:string}>("SELECT password_hash FROM web_accounts WHERE id=$1",[id]);
  if(!account.rows[0]||!verifyPassword(password,account.rows[0].password_hash))return NextResponse.json({error:"Current password is incorrect"},{status:403});
  const result=await db.query<{email:string;username:string}>("UPDATE web_accounts SET email=$1,username=$2 WHERE id=$3 AND password_hash=$4 AND NOT EXISTS(SELECT 1 FROM web_accounts WHERE id<>$3 AND (lower(email)=lower($1) OR lower(username)=lower($2))) RETURNING email,username",[email,username,id,account.rows[0].password_hash]);
  if(!result.rows[0])return NextResponse.json({error:"Email or username already in use, or account changed"},{status:409});
  return NextResponse.json({ok:true,user:result.rows[0]},{headers:{"Cache-Control":"no-store"}});
 }catch(e){if(e&&typeof e==="object"&&"code" in e&&e.code==="23505")return NextResponse.json({error:"Email or username already in use"},{status:409});return NextResponse.json({error:"Profile update unavailable"},{status:503});}
}
