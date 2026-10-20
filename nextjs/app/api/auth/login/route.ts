import {NextRequest,NextResponse} from "next/server";
import {database,verifyPassword,signSession} from "../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest){try{
const origin=req.headers.get("origin");if(origin&&new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});
if(!process.env.DATABASE_URL||!process.env.AUTH_SECRET)return NextResponse.json({error:"Login is not configured yet"},{status:503});
const body=await req.json();const email=String(body.email??"").trim().toLowerCase();const password=String(body.password??"");
if(email.length>254||password.length>128)return NextResponse.json({error:"Invalid credentials"},{status:401});
const result=await database().query<{id:string;username:string;password_hash:string}>("SELECT id,username,password_hash FROM web_accounts WHERE email=$1 LIMIT 1",[email]);
const account=result.rows[0];if(!account||!verifyPassword(password,account.password_hash))return NextResponse.json({error:"Invalid credentials"},{status:401});
const response=NextResponse.json({ok:true,username:account.username});response.cookies.set("saradeeeb_web_session",signSession(account.id),{httpOnly:true,secure:process.env.NODE_ENV==="production",sameSite:"lax",path:"/",maxAge:604800});return response;
}catch(error){console.error("Account login failed",error instanceof Error?{name:error.name,message:error.message,code:(error as {code?:string}).code}:{name:"UnknownError"});return NextResponse.json({error:"Login temporarily unavailable. Please try again later."},{status:503});}}
