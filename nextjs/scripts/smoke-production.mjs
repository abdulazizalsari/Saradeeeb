const base=(process.env.SITE_URL||"https://saradeeeb-preview.vercel.app").replace(/\/$/,"");
const paths=["/","/ar","/ar/categories","/ar/privacy-policy","/ar/search?q=%D9%87%D8%B3%D8%AF%D8%A7%D9%84%D9%86","/sitemap.xml","/robots.txt","/feed.xml","/llms.txt","/llms-full.txt"];
let failed=0;
for(const path of paths){
 try{
  const r=await fetch(base+path,{redirect:"follow",signal:AbortSignal.timeout(20000)});
  const body=await r.text();
  const valid=r.ok&&body.length>50&&(!path.includes("sitemap")||body.includes("<urlset"))&&(!path.includes("feed")||body.includes("<rss"));
  console.log(JSON.stringify({path,status:r.status,bytes:body.length,valid,url:r.url}));
  if(!valid)failed++;
 }catch(e){console.error(JSON.stringify({path,error:e.message}));failed++;}
}
if(failed){console.error("Smoke failures:",failed);process.exitCode=1;}
