import {createHash,randomBytes} from "node:crypto";
import {NextRequest,NextResponse} from "next/server";
import {database} from "../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 if(!process.env.DATABASE_URL||!process.env.PASSWORD_RESET_WEBHOOK_URL||!process.env.PASSWORD_RESET_WEBHOOK_SECRET)return NextResponse.json({error:"Password recovery email is not configured"},{status:503});
 try{
  const body=await req.json();const email=String(body.email??"").trim().toLowerCase();if(email.length>254||!email.includes("@"))return NextResponse.json({error:"Invalid email"},{status:400});
  const generic=()=>NextResponse.json({ok:true,message:"If the account exists, a recovery email will be sent."});
  const db=database();const account=await db.query<{id:string}>("SELECT id FROM web_accounts WHERE lower(email)=lower($1) LIMIT 1",[email]);if(!account.rows[0])return generic();
  const token=randomBytes(32).toString("hex");const hash=createHash("sha256").update(token).digest("hex");
  await db.query("DELETE FROM web_password_resets WHERE account_id=$1 OR expires_at<now()",[account.rows[0].id]);
  await db.query("INSERT INTO web_password_resets(account_id,token_hash,expires_at) VALUES($1,$2,now()+interval '30 minutes')",[account.rows[0].id,hash]);
  const resetUrl=new URL("/account/reset-password",req.nextUrl.origin);resetUrl.searchParams.set("token",token);
  const response=await fetch(process.env.PASSWORD_RESET_WEBHOOK_URL,{method:"POST",headers:{"Content-Type":"application/json","Authorization":`Bearer ${process.env.PASSWORD_RESET_WEBHOOK_SECRET}`},body:JSON.stringify({to:email,resetUrl:resetUrl.toString(),subject:"استعادة كلمة مرور سراديب"}),signal:AbortSignal.timeout(10000)});
  if(!response.ok){await db.query("DELETE FROM web_password_resets WHERE token_hash=$1",[hash]);return NextResponse.json({error:"Email delivery unavailable"},{status:503});}
  return generic();
 }catch{return NextResponse.json({error:"Password recovery unavailable"},{status:503});}
}
