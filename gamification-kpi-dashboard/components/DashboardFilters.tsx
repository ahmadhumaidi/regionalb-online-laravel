"use client";

import { CalendarDays, Search, SlidersHorizontal } from "lucide-react";

export interface Filters { regional: string; campus: string; unit: string; query: string; }

export function DashboardFilters({ filters, setFilters, regionals, campuses, units }: { filters: Filters; setFilters: (filters: Filters) => void; regionals: string[]; campuses: string[]; units: string[] }) {
  const selectClass = "min-h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100";
  return <section className="rounded-2xl border border-white bg-white/80 p-3 shadow-sm backdrop-blur">
    <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1.3fr]">
      <label className="relative"><SlidersHorizontal className="pointer-events-none absolute top-3.5 left-3 text-slate-400" size={16} /><select value={filters.regional} onChange={(event) => setFilters({ ...filters, regional: event.target.value })} className={`${selectClass} w-full pl-9`}><option value="">Semua Regional</option>{regionals.map((item) => <option key={item}>{item}</option>)}</select></label>
      <select value={filters.campus} onChange={(event) => setFilters({ ...filters, campus: event.target.value })} className={selectClass}><option value="">Semua Kampus</option>{campuses.map((item) => <option key={item}>{item}</option>)}</select>
      <select value={filters.unit} onChange={(event) => setFilters({ ...filters, unit: event.target.value })} className={selectClass}><option value="">Semua Unit</option>{units.map((item) => <option key={item}>{item}</option>)}</select>
      <button className={`${selectClass} flex items-center justify-center gap-2`}><CalendarDays size={16} /> Hari Ini</button>
      <label className="relative md:col-span-2 xl:col-span-1"><Search className="pointer-events-none absolute top-3.5 left-3 text-slate-400" size={16} /><input value={filters.query} onChange={(event) => setFilters({ ...filters, query: event.target.value })} placeholder="Cari staff..." className={`${selectClass} w-full pl-9`} /></label>
    </div>
  </section>;
}
