import {createHash} from "node:crypto";
import {NextRequest,NextResponse} from "next/server";
import {database,hashPassword} from "../../../../lib/auth";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.nextUrl.host)return NextResponse.json({error:"Invalid origin"},{status:403});}catch{return NextResponse.json({error:"Invalid origin"},{status:403});}}
 try{
  const body=await req.json();const token=String(body.token??"");const password=String(body.password??"");
  if(!/^[a-f0-9]{64}$/.test(token)||password.length<12||password.length>128)return NextResponse.json({error:"Invalid or expired reset link or password"},{status:400});
  const tokenHash=createHash("sha256").update(token).digest("hex");const db=database();const client=await db.connect();
  try{await client.query("BEGIN");const result=await client.query<{id:string;account_id:string}>("SELECT id,account_id FROM web_password_resets WHERE token_hash=$1 AND used_at IS NULL AND expires_at>now() FOR UPDATE",[tokenHash]);
   if(!result.rows[0]){await client.query("ROLLBACK");return NextResponse.json({error:"Reset link has expired or been used"},{status:400});}
   await client.query("UPDATE web_accounts SET password_hash=$1 WHERE id=$2",[hashPassword(password),result.rows[0].account_id]);
   await client.query("UPDATE web_password_resets SET used_at=now() WHERE id=$1",[result.rows[0].id]);
   await client.query("DELETE FROM web_password_resets WHERE account_id=$1 AND id<>$2",[result.rows[0].account_id,result.rows[0].id]);
   await client.query("COMMIT");return NextResponse.json({ok:true},{headers:{"Cache-Control":"no-store"}});
  }catch(e){await client.query("ROLLBACK");throw e}finally{client.release()}
 }catch{return NextResponse.json({error:"Password reset unavailable"},{status:503});}
}
