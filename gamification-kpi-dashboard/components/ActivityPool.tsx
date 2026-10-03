import { CalendarClock, CheckSquare, Clock3, Files, Flag, Images, Map, MessagesSquare, Moon, PhoneCall, Radio, Share2, Smartphone, Video } from "lucide-react";
import { activities } from "@/lib/data";
import { ActivityCategory } from "@/lib/types";

const icons = { images: Images, video: Video, radio: Radio, moon: Moon, smartphone: Smartphone, messages: MessagesSquare, map: Map, files: Files, flag: Flag, share: Share2, phone: PhoneCall, clock: Clock3, clipboard: CheckSquare };
const meta: Record<ActivityCategory, { label: string; text: string; badge: string }> = {
  DAILY: { label: "DAILY", text: "Menentukan checkpoint & FINISH hari ini", badge: "bg-blue-100 text-blue-700" },
  WEEKLY: { label: "WEEKLY", text: "Target mingguan, tidak menahan FINISH harian", badge: "bg-violet-100 text-violet-700" },
  PERIODIC: { label: "PERIODIC", text: "Target periode, tidak menahan FINISH harian", badge: "bg-amber-100 text-amber-700" },
};

export function ActivityPool() {
  return <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div className="mb-5 flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-blue-600"><CalendarClock size={20} /></span><div><h2 className="text-lg font-black text-slate-900">Daftar Aktivitas KPI</h2><p className="text-sm text-slate-500">Hanya kategori DAILY yang dihitung untuk progres race.</p></div></div>
    <div className="space-y-6">{(["DAILY", "WEEKLY", "PERIODIC"] as ActivityCategory[]).map((category) => <div key={category}><div className="mb-3 flex flex-wrap items-center gap-2"><span className={`rounded-full px-2.5 py-1 text-[10px] font-black tracking-wider ${meta[category].badge}`}>{meta[category].label}</span><span className="text-xs text-slate-500">{meta[category].text}</span></div><div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">{activities.filter((item) => item.category === category).map((item) => { const Icon = icons[item.icon as keyof typeof icons] ?? CheckSquare; return <article key={item.id} className="flex items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-blue-600 shadow-sm"><Icon size={18} /></span><div className="min-w-0"><h3 className="truncate text-sm font-bold text-slate-800">{item.name}</h3><p className="text-xs text-slate-500">Target: {item.target}</p></div></article>; })}</div></div>)}</div>
  </section>;
}
