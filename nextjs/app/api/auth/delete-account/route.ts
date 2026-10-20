import {NextRequest,NextResponse} from "next/server";
import {database,verifyPassword,verifySession} from "../../../../lib/auth";
export const runtime="nodejs";
export async function DELETE(req:NextRequest){
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 const id=verifySession(req.cookies.get("saradeeeb_web_session")?.value);
 if(!id)return NextResponse.json({error:"Authentication required"},{status:401});
 try{
  const body=await req.json();const password=String(body.currentPassword??"");if(!password||password.length>128)return NextResponse.json({error:"Password required"},{status:400});
  const db=database();const result=await db.query<{password_hash:string}>("SELECT password_hash FROM web_accounts WHERE id=$1",[id]);
  if(!result.rows[0]||!verifyPassword(password,result.rows[0].password_hash))return NextResponse.json({error:"Current password is incorrect"},{status:403});
  const removed=await db.query("DELETE FROM web_accounts WHERE id=$1 AND password_hash=$2",[id,result.rows[0].password_hash]);
  if(removed.rowCount!==1)return NextResponse.json({error:"Please retry account deletion"},{status:409});
  const response=NextResponse.json({ok:true},{headers:{"Cache-Control":"no-store"}});response.cookies.set("saradeeeb_web_session","",{path:"/",maxAge:0,httpOnly:true,sameSite:"lax",secure:process.env.NODE_ENV==="production"});return response;
 }catch(e){if(e&&typeof e==="object"&&"code" in e&&e.code==="23503")return NextResponse.json({error:"Account has associated content and cannot be deleted automatically"},{status:409});return NextResponse.json({error:"Account deletion unavailable"},{status:503});}
}
