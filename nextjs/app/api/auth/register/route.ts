import {NextRequest,NextResponse} from "next/server";
import {database,hashPassword,signSession} from "../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest){try{
const origin=req.headers.get("origin");if(origin&&new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});
if(!process.env.DATABASE_URL||!process.env.AUTH_SECRET)return NextResponse.json({error:"Registration is temporarily unavailable. Please contact site administration."},{status:503});
const body=await req.json();const email=String(body.email??"").trim().toLowerCase();const username=String(body.username??"").trim();const password=String(body.password??"");
if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||email.length>254||!/^[\p{L}\p{N}_-]{3,40}$/u.test(username)||password.length<12||password.length>128)return NextResponse.json({error:"Use a valid email, username (3–40 characters), and password (12–128 characters)"},{status:400});
const passwordHash=hashPassword(password);
const result=await database().query<{id:string}>("INSERT INTO web_accounts(email,username,password_hash) VALUES($1,$2,$3) ON CONFLICT DO NOTHING RETURNING id",[email,username,passwordHash]);
if(!result.rows[0])return NextResponse.json({error:"Account already exists"},{status:409});
const response=NextResponse.json({ok:true,username},{status:201});response.cookies.set("saradeeeb_web_session",signSession(result.rows[0].id),{httpOnly:true,secure:process.env.NODE_ENV==="production",sameSite:"lax",path:"/",maxAge:604800});return response;
}catch(error){console.error("Account registration failed",error instanceof Error?{name:error.name,message:error.message,code:(error as {code?:string}).code}:{name:"UnknownError"});return NextResponse.json({error:"Registration temporarily unavailable. Please try again later."},{status:503});}}
