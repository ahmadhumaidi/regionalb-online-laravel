"use client";

import { motion } from "framer-motion";
import { Staff, progressPercentage, progressStatus } from "@/lib/types";

export function StaffAvatar({ staff, onClick }: { staff: Staff; onClick: (staff: Staff) => void }) {
  const status = progressStatus(staff);
  const finish = status === "FINISH";
  const start = status === "START";

  return (
    <motion.button
      layoutId={`staff-${staff.id}`}
      transition={{ duration: 0.72, ease: [0.22, 1, 0.36, 1] }}
      onClick={() => onClick(staff)}
      className="group relative -ml-2 first:ml-0 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
      title={staff.name}
    >
      <img
        src={staff.avatar}
        alt={staff.name}
        className={`h-11 w-11 rounded-full border-[3px] object-cover shadow-lg transition duration-200 hover:z-20 hover:scale-110 md:h-12 md:w-12 ${finish ? "border-amber-300 ring-2 ring-amber-400/50" : "border-white"} ${start ? "grayscale opacity-65" : ""}`}
      />
      {finish && <span className="absolute -right-1 -bottom-1 grid h-5 w-5 place-items-center rounded-full bg-amber-400 text-[10px] shadow">🏆</span>}
      <span className="staff-tooltip pointer-events-none absolute bottom-[calc(100%+10px)] left-1/2 z-50 hidden w-48 -translate-x-1/2 rounded-xl bg-slate-950 p-3 text-left text-xs text-white shadow-2xl group-hover:block">
        <strong className="block text-sm">{staff.name}</strong>
        <span className="mt-0.5 block text-slate-300">{staff.unit}</span>
        <span className="mt-2 block">{staff.completedDaily} / {staff.totalDaily} aktivitas</span>
        <span className="block">{progressPercentage(staff).toFixed(1)}%</span>
        <span className="mt-1 block font-semibold text-blue-300">{status}</span>
      </span>
    </motion.button>
  );
}
