"use client";

import { motion } from "framer-motion";
import { Trophy } from "lucide-react";
import { Staff } from "@/lib/types";
import { StaffCluster } from "./StaffCluster";

export function FinishZone({ staff, onStaffClick }: { staff: Staff[]; onStaffClick: (staff: Staff) => void }) {
  return <div className="finish-zone relative flex min-h-44 flex-col justify-between overflow-hidden rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-emerald-50 p-4 shadow-xl shadow-amber-500/15 md:min-h-52">
    <div className="pointer-events-none absolute inset-x-0 top-0 h-3 finish-checker" />
    <motion.div animate={{ rotate: [0, -6, 6, 0], scale: [1, 1.08, 1] }} transition={{ duration: 2.5, repeat: Infinity, repeatDelay: 2 }} className="absolute top-5 right-4 text-amber-500"><Trophy size={34} fill="currentColor" /></motion.div>
    <div><span className="inline-flex rounded-full bg-emerald-600 px-3 py-1 text-xs font-black tracking-[.16em] text-white">FINISH</span><h3 className="mt-3 text-base font-bold text-slate-900">Daily Selesai</h3><p className="text-sm text-emerald-700">Target Harian Tuntas</p></div>
    <StaffCluster staff={staff} onStaffClick={onStaffClick} align="end" />
    {staff.length > 0 && <span className="mt-2 self-end rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-800">🏆 Daily Completed</span>}
  </div>;
}
