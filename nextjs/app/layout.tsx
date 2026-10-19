import type { Metadata } from "next";
import "./original-site.css";
import "./globals.css";
import "./article-migration.css";
export const metadata: Metadata = { title: "سراديب | ما وراء الظلام", description: "سراديب — حكايات وغموض وتاريخ وأفكار" };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="ar" dir="rtl"><body>{children}</body></html>; }
