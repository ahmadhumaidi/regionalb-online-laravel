import { CheckCircle2, Gauge, PlayCircle, Trophy, Users } from "lucide-react";
import { Staff } from "@/lib/types";

export function DailyStats({ staff }: { staff: Staff[] }) {
  const start = staff.filter((person) => person.completedDaily === 0).length;
  const finish = staff.filter((person) => person.completedDaily >= person.totalDaily).length;
  const progress = staff.length - start - finish;
  const completion = staff.length ? Math.round((finish / staff.length) * 100) : 0;
  const cards = [
    ["Total Staff", staff.length, Users, "text-blue-600", "bg-blue-50"],
    ["Belum Mulai", start, PlayCircle, "text-slate-600", "bg-slate-100"],
    ["Sedang Progress", progress, Gauge, "text-amber-600", "bg-amber-50"],
    ["Sudah Finish", finish, Trophy, "text-emerald-600", "bg-emerald-50"],
    ["Completion Rate", `${completion}%`, CheckCircle2, "text-violet-600", "bg-violet-50"],
  ] as const;
  return <section className="grid grid-cols-2 gap-3 lg:grid-cols-5">
    {cards.map(([label, value, Icon, color, background], index) => <article key={label} className={`rounded-2xl border border-white bg-white p-4 shadow-sm ${index === 4 ? "col-span-2 lg:col-span-1" : ""}`}><div className={`mb-3 grid h-9 w-9 place-items-center rounded-xl ${background} ${color}`}><Icon size={18} /></div><strong className="block text-2xl font-black tracking-tight text-slate-900">{value}</strong><span className="text-sm text-slate-500">{label}</span></article>)}
  </section>;
}
