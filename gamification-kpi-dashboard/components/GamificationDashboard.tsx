"use client";

import { useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import { ArrowLeft, Clock3, Info, Sparkles } from "lucide-react";
import { initialStaff } from "@/lib/data";
import { Staff } from "@/lib/types";
import { ActivityPool } from "./ActivityPool";
import { DailyStats } from "./DailyStats";
import { DashboardFilters, Filters } from "./DashboardFilters";
import { GamificationTrack } from "./GamificationTrack";
import { Leaderboard } from "./Leaderboard";
import { StaffDetailDrawer } from "./StaffDetailDrawer";

const timeToMidnight = () => {
  const now = new Date();
  const midnight = new Date(now);
  midnight.setHours(24, 0, 0, 0);
  const seconds = Math.max(0, Math.floor((midnight.getTime() - now.getTime()) / 1000));
  return [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60].map((value) => String(value).padStart(2, "0")).join(":");
};

export function GamificationDashboard() {
  const [filters, setFilters] = useState<Filters>({ regional: "", campus: "", unit: "", query: "" });
  const [selectedStaff, setSelectedStaff] = useState<Staff | null>(null);
  const [countdown, setCountdown] = useState(timeToMidnight);
  useEffect(() => { const timer = window.setInterval(() => setCountdown(timeToMidnight()), 1000); return () => window.clearInterval(timer); }, []);

  const unique = (key: "regional" | "campus" | "unit") => [...new Set(initialStaff.map((person) => person[key]))].sort();
  const filteredStaff = useMemo(() => initialStaff.filter((person) =>
    (!filters.regional || person.regional === filters.regional) &&
    (!filters.campus || person.campus === filters.campus) &&
    (!filters.unit || person.unit === filters.unit) &&
    (!filters.query || `${person.name} ${person.unit} ${person.campus}`.toLowerCase().includes(filters.query.toLowerCase()))
  ), [filters]);

  return <main className="min-h-screen bg-[#f8fafc] text-slate-900">
    <div className="mx-auto max-w-[1600px] space-y-5 px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
      <header className="relative overflow-hidden rounded-[2rem] bg-slate-950 px-5 py-6 text-white shadow-2xl shadow-blue-950/15 sm:px-7 lg:flex lg:items-center lg:justify-between">
        <div className="pointer-events-none absolute -top-20 right-20 h-64 w-64 rounded-full bg-blue-600/30 blur-3xl" />
        <div className="relative"><a href="/" className="mb-4 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-300 transition hover:text-white"><ArrowLeft size={14} /> Dashboard Utama</a><br /><span className="mb-3 inline-flex items-center gap-2 rounded-full border border-blue-400/25 bg-blue-400/10 px-3 py-1 text-[11px] font-black tracking-[.18em] text-blue-300"><Sparkles size={13} /> DAILY TEAM RACE</span><h1 className="text-2xl font-black tracking-tight sm:text-3xl">Gamification KPI Staff Unit</h1><p className="mt-2 text-sm text-slate-300 sm:text-base">Setiap 1 Aktivitas Selesai = Naik 1 Checkpoint</p><p className="mt-3 inline-flex items-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold text-white"><Info size={15} className="text-cyan-300" /> Aktivitas bebas dikerjakan, tidak harus urut.</p></div>
        <div className="relative mt-5 grid grid-cols-2 gap-3 lg:mt-0 lg:min-w-[360px]"><div className="rounded-2xl border border-white/10 bg-white/5 p-4"><p className="text-xs text-slate-400">Hari ini</p><strong className="mt-1 block text-base">{new Intl.DateTimeFormat("id-ID", { weekday: "long", day: "numeric", month: "long", year: "numeric" }).format(new Date())}</strong></div><div className="rounded-2xl border border-blue-400/20 bg-blue-500/15 p-4"><p className="flex items-center gap-1 text-xs text-blue-200"><Clock3 size={13} /> Reset Daily</p><strong className="mt-1 block font-mono text-xl tracking-wider text-white">{countdown}</strong></div></div>
      </header>

      <DashboardFilters filters={filters} setFilters={setFilters} regionals={unique("regional")} campuses={unique("campus")} units={unique("unit")} />
      <DailyStats staff={filteredStaff} />

      <motion.div layout><GamificationTrack staff={filteredStaff} totalDaily={8} onStaffClick={setSelectedStaff} /></motion.div>

      <div className="grid gap-5 xl:grid-cols-[1fr_340px]"><ActivityPool /><Leaderboard staff={filteredStaff} /></div>
    </div>
    <StaffDetailDrawer staff={selectedStaff} onClose={() => setSelectedStaff(null)} />
  </main>;
}
