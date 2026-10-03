import { Crown } from "lucide-react";
import { Staff } from "@/lib/types";

export function Leaderboard({ staff }: { staff: Staff[] }) {
  const ranked = [...staff].sort((a, b) => {
    const aFinish = a.completedDaily >= a.totalDaily;
    const bFinish = b.completedDaily >= b.totalDaily;
    if (aFinish !== bFinish) return aFinish ? -1 : 1;
    if (aFinish && bFinish) return (a.finishTime ?? "99:99").localeCompare(b.finishTime ?? "99:99");
    if (a.completedDaily !== b.completedDaily) return b.completedDaily - a.completedDaily;
    return (a.lastActivityAt ?? "99:99").localeCompare(b.lastActivityAt ?? "99:99");
  }).slice(0, 5);
  const medals = ["🥇", "🥈", "🥉"];
  return <aside className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div className="mb-4 flex items-center gap-2"><span className="grid h-9 w-9 place-items-center rounded-xl bg-amber-50 text-amber-600"><Crown size={19} /></span><div><h2 className="font-black text-slate-900">Leaderboard Hari Ini</h2><p className="text-xs text-slate-500">Finish tercepat & progres terbanyak</p></div></div>
    <div className="space-y-2">{ranked.map((person, index) => <div key={person.id} className="flex items-center gap-3 rounded-xl border border-slate-100 p-2.5"><span className="w-6 text-lg">{medals[index] ?? `#${index + 1}`}</span><img src={person.avatar} alt="" className="h-9 w-9 rounded-full object-cover" /><div className="min-w-0 flex-1"><b className="block truncate text-sm text-slate-900">{person.name}</b><span className="text-xs text-slate-500">{person.completedDaily}/{person.totalDaily} aktivitas</span></div><span className="text-right text-[11px] font-bold text-emerald-600">{person.finishTime ? <>Finish<br />{person.finishTime}</> : person.lastActivityAt}</span></div>)}</div>
  </aside>;
}
