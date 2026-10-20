import "server-only";
import {createHmac,randomBytes,scryptSync,timingSafeEqual} from "node:crypto";
import {Pool} from "pg";
let pool:Pool|undefined;
export function database(){if(!process.env.DATABASE_URL)throw new Error("Database is not configured");return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:process.env.DATABASE_SSL_INSECURE==="true"?{rejectUnauthorized:false}:{rejectUnauthorized:true},max:4}));}
export function hashPassword(password:string){const salt=randomBytes(16).toString("hex");return `scrypt:${salt}:${scryptSync(password,salt,64).toString("hex")}`;}
export function verifyPassword(password:string,stored:string){const [alg,salt,hex]=stored.split(":");if(alg!=="scrypt"||!salt||!hex||hex.length!==128)return false;const expected=Buffer.from(hex,"hex");const actual=scryptSync(password,salt,expected.length);return timingSafeEqual(expected,actual);}
export function signSession(userId:string){const secret=process.env.AUTH_SECRET;if(!secret||secret.length<32)throw new Error("AUTH_SECRET is missing");const expires=Date.now()+7*86400000;const payload=`${userId}.${expires}`;const signature=createHmac("sha256",secret).update(payload).digest("hex");return `${payload}.${signature}`;}
export function verifySession(value:string|undefined){if(!value)return null;const [id,expiry,signature]=value.split(".");if(!id||!expiry||!signature||!/^\d+$/.test(id)||Number(expiry)<Date.now())return null;const secret=process.env.AUTH_SECRET;if(!secret||secret.length<32)return null;const actual=createHmac("sha256",secret).update(`${id}.${expiry}`).digest("hex");if(signature.length!==actual.length||!timingSafeEqual(Buffer.from(signature),Buffer.from(actual)))return null;return id;}
