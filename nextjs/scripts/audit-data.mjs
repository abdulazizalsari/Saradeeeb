import pg from "pg";
const {Pool}=pg;
if(!process.env.DATABASE_URL){console.error("DATABASE_URL is required");process.exitCode=2;}else{
const db=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:2});
try{
 const q=await db.query(`SELECT
 (SELECT count(*) FROM articles) AS articles,
 (SELECT count(*) FROM article_translations) AS translations,
 (SELECT count(*) FROM article_translations WHERE content IS NULL OR btrim(content)='') AS empty_translations,
 (SELECT count(*) FROM article_translations WHERE slug LIKE 'archive-%') AS temporary_slugs,
 (SELECT count(*) FROM articles WHERE featured_image IS NULL OR btrim(featured_image)='') AS missing_image_paths`);
 console.log(JSON.stringify(q.rows[0],null,2));
 const gaps=await db.query(`SELECT a.id,t.locale,t.slug,CASE WHEN t.id IS NULL THEN 'missing_translation' WHEN t.content IS NULL OR btrim(t.content)='' THEN 'empty_content' WHEN t.slug LIKE 'archive-%' THEN 'temporary_slug' END AS issue
 FROM articles a LEFT JOIN article_translations t ON t.article_id=a.id
 WHERE t.id IS NULL OR t.content IS NULL OR btrim(t.content)='' OR t.slug LIKE 'archive-%' ORDER BY a.id`);
 console.log(JSON.stringify(gaps.rows,null,2));
 if(gaps.rowCount)process.exitCode=1;
}catch(e){console.error(e.message);process.exitCode=2;}finally{await db.end();}
}