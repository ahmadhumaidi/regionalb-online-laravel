"use client";

import { Flag } from "lucide-react";
import { Staff } from "@/lib/types";
import { StaffCluster } from "./StaffCluster";

export function StartZone({ staff, onStaffClick }: { staff: Staff[]; onStaffClick: (staff: Staff) => void }) {
  return <div className="flex min-h-44 flex-col justify-between rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-xl shadow-blue-950/10 backdrop-blur md:min-h-52">
    <div><span className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1 text-xs font-black tracking-[.18em] text-white"><Flag size={14} /> START</span><h3 className="mt-3 text-base font-bold text-slate-900">Belum Mengerjakan</h3><p className="text-sm text-slate-500">0 Aktivitas</p></div>
    <StaffCluster staff={staff} onStaffClick={onStaffClick} align="start" />
  </div>;
}
