"use client";

import { Staff } from "@/lib/types";
import { StaffCluster } from "./StaffCluster";

const colors = ["bg-orange-500", "bg-cyan-500", "bg-violet-500", "bg-pink-500", "bg-emerald-500", "bg-amber-500", "bg-blue-600"];

export function Checkpoint({ number, staff, onStaffClick }: { number: number; staff: Staff[]; onStaffClick: (staff: Staff) => void }) {
  return <div className="flex min-h-36 flex-col items-center justify-between gap-3 text-center">
    <div><div className={`mx-auto grid h-12 w-12 place-items-center rounded-2xl border-4 border-white text-xl font-black text-white shadow-lg ${colors[(number - 1) % colors.length]}`}>{number}</div><p className="mt-2 text-[10px] font-black tracking-[.16em] text-slate-500">CHECKPOINT {number}</p></div>
    <StaffCluster staff={staff} onStaffClick={onStaffClick} />
  </div>;
}
