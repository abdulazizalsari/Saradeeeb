import {NextRequest, NextResponse} from "next/server";

const arabicSections = ["article", "category", "categories", "search", "podcast", "khawatir", "community", "privacy-policy"];

export function middleware(request: NextRequest) {
  const {pathname} = request.nextUrl;
  if (pathname === "/ar" || pathname.startsWith("/ar/")) {
    const url = request.nextUrl.clone();
    url.pathname = pathname === "/ar" ? "/" : pathname.slice(3) || "/";
    return NextResponse.redirect(url, 308);
  }
  if (pathname === "/" || pathname.startsWith("/api/") || pathname.startsWith("/_next/") || pathname.includes(".")) return NextResponse.next();
  if (arabicSections.includes(pathname.split("/")[1])) {
    const url = request.nextUrl.clone();
    url.pathname = "/ar" + pathname;
    return NextResponse.rewrite(url);
  }
  return NextResponse.next();
}

export const config = {matcher: ["/:path*"]};
