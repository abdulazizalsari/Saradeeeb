import {NextRequest,NextResponse} from "next/server";
import {database,verifySession} from "../../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest,{params}:{params:Promise<{id:string}>}){
 const origin=req.headers.get("origin");
 if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 const userId=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!userId)return NextResponse.json({error:"Login required"},{status:401});
 const {id}=await params;
 if(!/^[1-9]\d{0,17}$/.test(id))return NextResponse.json({error:"Invalid topic"},{status:400});
 try{
  const body=await req.json();const content=String(body.content??"").trim();
  if(content.length<2||content.length>10000)return NextResponse.json({error:"Invalid reply"},{status:400});
  const r=await database().query("INSERT INTO web_community_replies(topic_id,author_id,content) SELECT id,$2,$3 FROM web_community_topics WHERE id=$1 AND status='open' RETURNING id",[id,userId,content]);
  if(!r.rowCount)return NextResponse.json({error:"Topic unavailable"},{status:404});
  return NextResponse.json({ok:true},{status:201,headers:{"Cache-Control":"no-store"}});
 }catch{return NextResponse.json({error:"Unable to publish reply"},{status:503});}
}
