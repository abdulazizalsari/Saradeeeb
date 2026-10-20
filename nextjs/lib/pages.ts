import "server-only";
import {Pool} from "pg";
export type PublicPage={title:string;slug:string;content:string;seo_title:string|null;seo_description:string|null;featured_image:string|null;show_title:boolean};
let pool:Pool|undefined;
export async function findPublicPage(locale:string,slug:string):Promise<PublicPage|null>{
 if(!["ar","en","es","fr","ru"].includes(locale)||slug.length>500||!process.env.DATABASE_URL)return null;
 const normalized=(()=>{try{return decodeURIComponent(slug)}catch{return slug}})();
 const candidates=[...new Set([slug,normalized,encodeURIComponent(normalized)])];
 const db=pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:3}));
 const result=await db.query<PublicPage>("SELECT t.title,t.slug,t.content,t.seo_title,t.seo_description,t.featured_image,t.show_title FROM page_translations t JOIN pages p ON p.id=t.page_id WHERE t.locale=$1 AND t.slug=ANY($2::text[]) AND p.status='published' AND p.deleted_at IS NULL LIMIT 1",[locale,candidates]);
 return result.rows[0]??null;
}
