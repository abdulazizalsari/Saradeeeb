import "server-only";
import {Pool} from "pg";
let pool:Pool|undefined;
function db(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:process.env.DATABASE_SSL_INSECURE==="true"?{rejectUnauthorized:false}:{rejectUnauthorized:true},max:3,connectionTimeoutMillis:5000}));}
export type WriterPublic={username:string;name:string;bio:string|null;avatar:string|null;articles:{title:string;slug:string;excerpt:string|null;featured_image:string|null}[]};
export async function publicWriter(username:string,locale:string):Promise<WriterPublic|null>{
 if(!/^[\p{L}\p{N}_-]{3,40}$/u.test(username)||!["ar","en","es","fr","ru"].includes(locale))return null;
 const c=db();if(!c)return null;
 try{
 const user=await c.query<{id:string;username:string;name:string;bio:string|null;avatar:string|null}>(`SELECT id::text,username,name,bio,avatar FROM users WHERE LOWER(username)=LOWER($1) AND role IN ('writer','trusted_writer') AND status='active' LIMIT 1`,[username]);
 const u=user.rows[0];if(!u)return null;
 const articles=await c.query<{title:string;slug:string;excerpt:string|null;featured_image:string|null}>(`SELECT t.title,t.slug,t.excerpt,a.featured_image FROM articles a JOIN article_translations t ON t.article_id=a.id WHERE a.author_id=$1 AND a.status='published' AND t.status='published' AND t.locale=$2 ORDER BY a.published_at DESC,a.id DESC LIMIT 48`,[u.id,locale]);
 return {username:u.username,name:u.name,bio:u.bio,avatar:u.avatar,articles:articles.rows};
 }catch{return null;}
}
