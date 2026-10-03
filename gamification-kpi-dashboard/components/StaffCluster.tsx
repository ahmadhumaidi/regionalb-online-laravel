"use client";

import { useState } from "react";
import { Staff } from "@/lib/types";
import { StaffAvatar } from "./StaffAvatar";

export function StaffCluster({ staff, onStaffClick, align = "center" }: { staff: Staff[]; onStaffClick: (staff: Staff) => void; align?: "start" | "center" | "end" }) {
  const [open, setOpen] = useState(false);
  const visible = staff.slice(0, 6);
  const hidden = staff.slice(6);

  return (
    <div className={`relative flex flex-wrap items-center ${align === "start" ? "justify-start" : align === "end" ? "justify-end" : "justify-center"}`}>
      {visible.map((person) => <StaffAvatar key={person.id} staff={person} onClick={onStaffClick} />)}
      {hidden.length > 0 && (
        <button onClick={() => setOpen((value) => !value)} className="relative -ml-2 grid h-11 w-11 place-items-center rounded-full border-2 border-white bg-slate-800 text-xs font-bold text-white shadow md:h-12 md:w-12">+{hidden.length}</button>
      )}
      {open && hidden.length > 0 && (
        <div className="absolute top-14 z-50 min-w-56 rounded-xl border border-slate-200 bg-white p-2 text-left shadow-xl">
          {hidden.map((person) => <button key={person.id} onClick={() => onStaffClick(person)} className="flex w-full items-center gap-2 rounded-lg p-2 text-sm hover:bg-slate-50"><img src={person.avatar} alt="" className="h-8 w-8 rounded-full object-cover" /><span><b className="block">{person.name}</b><small className="text-slate-500">{person.unit}</small></span></button>)}
        </div>
      )}
    </div>
  );
}
