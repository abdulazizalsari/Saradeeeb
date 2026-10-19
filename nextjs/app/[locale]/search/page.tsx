import {redirect} from "next/navigation";
export default async function SearchPage({params,searchParams}:{params:Promise<{locale:string}>;searchParams:Promise<{q?:string}>}){const [{locale},{q}]=await Promise.all([params,searchParams]);const query=new URLSearchParams();if(q?.trim())query.set("q",q.trim().slice(0,100));redirect("/"+(locale==="ar"?"ar":locale)+(query.size?"?"+query.toString():""))}
