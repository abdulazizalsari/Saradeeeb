import SiteFooter from "./site-footer";
import SiteHeader from "./site-header";
import type { Metadata } from "next";
import "./original-site.css";
import "./globals.css";
import "./article-migration.css";
import "./account.css";
import "./mobile-navigation.css";
export const metadata: Metadata = { metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "https://saradeeeb-preview.vercel.app"), title: "سراديب | ما وراء الظلام", description: "سراديب — حكايات وغموض وتاريخ وأفكار" };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="ar" dir="rtl"><body><SiteHeader/>{children}<SiteFooter/></body></html>; }
