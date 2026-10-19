import type { Metadata } from "next";
import "./original-site.css";
import "./globals.css";
import "./article-migration.css";
import "./account.css";
import "./header-footer-repair.css";
export const metadata: Metadata = { title: "سراديب | ما وراء الظلام", description: "سراديب — حكايات وغموض وتاريخ وأفكار" };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="ar" dir="rtl"><head><link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous"/><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/></head><body style={{fontFamily:'"IBM Plex Sans Arabic",sans-serif'}}>{children}</body></html>; }
