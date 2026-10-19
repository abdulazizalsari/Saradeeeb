import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = { title: "سراديب | ما وراء الظلام", description: "سراديب — حكايات وغموض وتاريخ وأفكار" };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="ar" dir="rtl"><body>{children}</body></html>; }
