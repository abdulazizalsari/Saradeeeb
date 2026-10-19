import type {MetadataRoute} from "next";
export default function robots():MetadataRoute.Robots{
 return {rules:[{userAgent:"*",allow:"/",disallow:["/account","/admin","/api/"]}],sitemap:`${process.env.NEXT_PUBLIC_SITE_URL??"https://saradeeeb-preview.vercel.app"}/sitemap.xml`};
}
