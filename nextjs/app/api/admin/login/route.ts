import {NextRequest,NextResponse} from "next/server";
import {database,signSession,verifyPassword} from "../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 if(!process.env.DATABASE_URL||!process.env.AUTH_SECRET)return NextResponse.json({error:"Administration is not configured"},{status:503});
 try{
 const form=await req.formData();const email=String(form.get("email")??"").trim().toLowerCase();const password=String(form.get("password")??"");
 if(email.length>254||password.length>128||!email||!password)return NextResponse.json({error:"Invalid credentials"},{status:401});
 const result=await database().query<{id:string;password_hash:string}>("SELECT id,password_hash FROM web_accounts WHERE email=$1 AND role='admin' LIMIT 1",[email]);
 const account=result.rows[0];if(!account||!verifyPassword(password,account.password_hash))return NextResponse.json({error:"Invalid credentials"},{status:401});
 const response=NextResponse.redirect(new URL("/admin",req.url),303);response.cookies.set("saradeeeb_web_session",signSession(account.id),{httpOnly:true,secure:process.env.NODE_ENV==="production",sameSite:"lax",path:"/",maxAge:604800});response.headers.set("Cache-Control","no-store");return response;
 }catch{return NextResponse.json({error:"Admin login unavailable"},{status:503});}
}