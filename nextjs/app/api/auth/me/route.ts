import {NextRequest,NextResponse} from "next/server";
import {verifySession,database} from "../../../../lib/auth";
export const dynamic="force-dynamic";
export async function GET(req:NextRequest){const id=verifySession(req.cookies.get("saradeeeb_web_session")?.value);if(!id)return NextResponse.json({authenticated:false},{status:401});try{const r=await database().query<{username:string;email:string}>("SELECT username,email FROM web_accounts WHERE id=$1",[id]);if(!r.rows[0])return NextResponse.json({authenticated:false},{status:401});return NextResponse.json({authenticated:true,user:r.rows[0]},{headers:{"Cache-Control":"no-store"}});}catch{return NextResponse.json({error:"Service unavailable"},{status:503});}}
