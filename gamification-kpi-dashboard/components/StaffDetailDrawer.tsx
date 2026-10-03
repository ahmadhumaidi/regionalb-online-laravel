"use client";

import { AnimatePresence, motion } from "framer-motion";
import { Check, Circle, Clock3, MapPin, X } from "lucide-react";
import { activities } from "@/lib/data";
import { Staff, progressPercentage, progressStatus } from "@/lib/types";

export function StaffDetailDrawer({ staff, onClose }: { staff: Staff | null; onClose: () => void }) {
  return <AnimatePresence>{staff && <>
    <motion.button aria-label="Tutup detail" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={onClose} className="fixed inset-0 z-40 bg-slate-950/35 backdrop-blur-[2px]" />
    <motion.aside initial={{ x: "100%" }} animate={{ x: 0 }} exit={{ x: "100%" }} transition={{ type: "spring", stiffness: 280, damping: 30 }} className="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
      <button onClick={onClose} className="absolute top-5 right-5 rounded-full bg-slate-100 p-2 text-slate-500 hover:bg-slate-200"><X size={18} /></button>
      <div className="mt-5 flex items-center gap-4"><img src={staff.avatar} alt={staff.name} className="h-20 w-20 rounded-full border-4 border-white object-cover shadow-xl" /><div><h2 className="text-xl font-black text-slate-900">{staff.name}</h2><p className="mt-1 flex items-center gap-1 text-sm text-slate-500"><MapPin size={14} /> {staff.unit} · {staff.regional}</p><span className="mt-2 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{progressStatus(staff)}</span></div></div>
      <div className="mt-7 rounded-2xl bg-slate-950 p-5 text-white"><div className="flex items-end justify-between"><div><p className="text-xs text-slate-400">Progress Hari Ini</p><strong className="mt-1 block text-3xl">{staff.completedDaily} / {staff.totalDaily}</strong></div><b className="text-blue-300">{progressPercentage(staff).toFixed(0)}%</b></div><div className="mt-4 h-2 overflow-hidden rounded-full bg-white/10"><motion.div initial={{ width: 0 }} animate={{ width: `${progressPercentage(staff)}%` }} className="h-full rounded-full bg-gradient-to-r from-blue-500 to-cyan-400" /></div></div>
      <div className="mt-7"><h3 className="font-black text-slate-900">Aktivitas Daily</h3><div className="mt-3 space-y-2">{activities.filter((item) => item.category === "DAILY").map((activity) => { const completed = staff.completedActivities.find((item) => item.activityId === activity.id); return <div key={activity.id} className={`flex items-center gap-3 rounded-xl border p-3 ${completed ? "border-emerald-100 bg-emerald-50/60" : "border-slate-100"}`}><span className={`grid h-8 w-8 place-items-center rounded-full ${completed ? "bg-emerald-500 text-white" : "bg-slate-100 text-slate-400"}`}>{completed ? <Check size={16} /> : <Circle size={14} />}</span><div className="min-w-0 flex-1"><b className="block text-sm text-slate-800">{activity.shortName}</b><span className="text-xs text-slate-500">{completed ? "Selesai" : "Belum selesai"}</span></div>{completed && <span className="flex items-center gap-1 text-xs font-semibold text-slate-500"><Clock3 size={13} /> {completed.completedAt}</span>}</div>; })}</div></div>
    </motion.aside>
  </>}</AnimatePresence>;
}
