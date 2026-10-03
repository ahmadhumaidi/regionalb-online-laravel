import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Gamification KPI Staff Unit",
  description: "Daily KPI progression dashboard for staff units",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="id"><body>{children}</body></html>;
}
