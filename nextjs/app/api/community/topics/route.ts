import {randomBytes} from "node:crypto";
import {NextRequest,NextResponse} from "next/server";
import {database,verifySession} from "../../../../lib/auth";
export const runtime="nodejs";
const locales=new Set(["ar","en","es","fr","ru"]);
export async function POST(req:NextRequest){
 const origin=req.headers.get("origin");
 if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 const userId=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!userId)return NextResponse.json({error:"Login required"},{status:401});
 try{
  const body=await req.json();
  const locale=String(body.locale??"ar");
  const title=String(body.title??"").trim();
  const content=String(body.content??"").trim();
  if(!locales.has(locale)||title.length<5||title.length>190||content.length<10||content.length>20000)return NextResponse.json({error:"Invalid topic"},{status:400});
  const slug=randomBytes(12).toString("hex");
  const r=await database().query<{slug:string}>("INSERT INTO web_community_topics(author_id,locale,title,slug,content) VALUES($1,$2,$3,$4,$5) RETURNING slug",[userId,locale,title,slug,content]);
  return NextResponse.json({ok:true,url:`/${locale}/community/${r.rows[0].slug}`},{status:201,headers:{"Cache-Control":"no-store"}});
 }catch{return NextResponse.json({error:"Unable to publish topic"},{status:503});}
}
