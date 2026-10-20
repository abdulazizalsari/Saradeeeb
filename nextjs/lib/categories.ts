import "server-only";
import {Pool} from "pg";
let pool:Pool|null=null;
function db(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:process.env.DATABASE_SSL_INSECURE==="true"?{rejectUnauthorized:false}:{rejectUnauthorized:true},max:3,connectionTimeoutMillis:5000}));}
export type Category={id:string;name:string;slug:string;description:string|null};
export type CategoryArticle={title:string;slug:string;excerpt:string|null;featured_image:string|null};
export async function listCategories(locale:string):Promise<Category[]>{
 const c=db();if(!c)return [];
 const r=await c.query<Category>("SELECT c.id::text,t.name,t.slug,t.description FROM categories c JOIN category_translations t ON t.category_id=c.id WHERE c.active=1 AND t.locale=$1 AND t.status='published' ORDER BY c.sort_order,c.id",[locale]);
 return r.rows;
}
export async function categoryWithArticles(locale:string,slug:string,page=1):Promise<{category:Category;articles:CategoryArticle[];total:number;page:number;pages:number}|null>{
 const c=db();if(!c)return null;
 let decoded=slug;try{decoded=decodeURIComponent(slug);}catch{}
 const candidates=[...new Set([slug,decoded,encodeURIComponent(decoded),encodeURIComponent(decoded).toLowerCase()])];
 const r=await c.query<Category>("SELECT c.id::text,t.name,t.slug,t.description FROM categories c JOIN category_translations t ON t.category_id=c.id WHERE c.active=1 AND t.locale=$1 AND t.slug=ANY($2::text[]) AND t.status='published' LIMIT 1",[locale,candidates]);
 if(!r.rows[0])return null;
 const safePage=Number.isSafeInteger(page)?Math.min(10000,Math.max(1,page)):1;
 const count=await c.query<{total:string}>(`SELECT count(*)::text AS total FROM article_categories ac JOIN articles a ON a.id=ac.article_id JOIN article_translations t ON t.article_id=a.id WHERE ac.category_id=$1 AND t.locale=$2 AND a.status='published' AND t.status='published'`,[r.rows[0].id,locale]);
 const total=Number(count.rows[0]?.total??0);
 const articles=await c.query<CategoryArticle>(`SELECT t.title,t.slug,t.excerpt,a.featured_image FROM article_categories ac JOIN articles a ON a.id=ac.article_id JOIN article_translations t ON t.article_id=a.id WHERE ac.category_id=$1 AND t.locale=$2 AND a.status='published' AND t.status='published' ORDER BY a.published_at DESC,a.id DESC LIMIT 18 OFFSET $3`,[r.rows[0].id,locale,(safePage-1)*18]);
 return {category:r.rows[0],articles:articles.rows,total,page:safePage,pages:Math.ceil(total/18)};
}
