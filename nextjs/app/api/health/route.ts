import {NextResponse} from "next/server";
export const dynamic="force-dynamic";
export function GET(){
const databaseConfigured=Boolean(process.env.DATABASE_URL);
return NextResponse.json({application:"saradeeeb-nextjs",status:"migration-in-progress",databaseConfigured,featureParity:false,productionReady:false},{headers:{"Cache-Control":"no-store"}});
}
