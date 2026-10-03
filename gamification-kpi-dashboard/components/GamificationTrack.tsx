"use client";

import { Staff } from "@/lib/types";
import { StartZone } from "./StartZone";
import { Checkpoint } from "./Checkpoint";
import { FinishZone } from "./FinishZone";

export function GamificationTrack({ staff, totalDaily, onStaffClick }: { staff: Staff[]; totalDaily: number; onStaffClick: (staff: Staff) => void }) {
  const at = (progress: number) => staff.filter((person) => progress === totalDaily ? person.completedDaily >= totalDaily : person.completedDaily === progress);
  const points = Array.from({ length: totalDaily - 1 }, (_, index) => index + 1);

  return <section className="relative overflow-hidden rounded-[2rem] border border-white/80 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-4 shadow-xl shadow-blue-950/5 md:p-6">
    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-28 opacity-30 skyline" />
    <div className="hidden min-w-[1180px] lg:block">
      <svg className="absolute top-32 left-0 h-64 w-full" viewBox="0 0 1200 250" preserveAspectRatio="none" aria-hidden="true">
        <defs><filter id="roadShadow"><feDropShadow dx="0" dy="8" stdDeviation="8" floodColor="#0f172a" floodOpacity=".22" /></filter></defs>
        <path d="M30 125 C110 125 120 45 210 70 S320 205 405 160 S500 35 590 85 S700 205 790 145 S895 30 980 78 S1080 145 1170 125" fill="none" stroke="#0f172a" strokeWidth="48" strokeLinecap="round" filter="url(#roadShadow)" />
        <path d="M30 125 C110 125 120 45 210 70 S320 205 405 160 S500 35 590 85 S700 205 790 145 S895 30 980 78 S1080 145 1170 125" fill="none" stroke="white" strokeWidth="3" strokeDasharray="13 13" strokeLinecap="round" opacity=".9" />
      </svg>
      <div className="relative z-10 grid grid-cols-10 items-start gap-2">
        <div className="col-span-2"><StartZone staff={at(0)} onStaffClick={onStaffClick} /></div>
        {points.map((number) => <div key={number} className={`pt-${number % 2 === 0 ? "28" : "4"}`} style={{ paddingTop: number % 2 === 0 ? 112 : 16 }}><Checkpoint number={number} staff={at(number)} onStaffClick={onStaffClick} /></div>)}
        <div className="col-span-1"><FinishZone staff={at(totalDaily)} onStaffClick={onStaffClick} /></div>
      </div>
    </div>

    <div className="relative grid gap-0 lg:hidden">
      <StartZone staff={at(0)} onStaffClick={onStaffClick} />
      {points.map((number) => <div key={number} className="relative border-l-8 border-slate-900 py-5 pl-7 before:absolute before:top-1/2 before:-left-[6px] before:h-5 before:border-l-2 before:border-dashed before:border-white"><Checkpoint number={number} staff={at(number)} onStaffClick={onStaffClick} /></div>)}
      <FinishZone staff={at(totalDaily)} onStaffClick={onStaffClick} />
    </div>
  </section>;
}
