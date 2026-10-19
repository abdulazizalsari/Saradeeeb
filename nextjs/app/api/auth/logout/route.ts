import {NextRequest,NextResponse} from "next/server";
export async function POST(req:NextRequest){const origin=req.headers.get("origin");if(origin&&new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});const response=NextResponse.json({ok:true});response.cookies.set("saradeeeb_web_session","",{path:"/",maxAge:0});return response;}
