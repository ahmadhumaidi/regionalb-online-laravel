import { ActivityDefinition, Staff } from "./types";

export const activities: ActivityDefinition[] = [
  { id: "igfb", name: "Konten Instagram & Facebook", shortName: "Konten IG/FB", target: "1/hari", category: "DAILY", icon: "images" },
  { id: "tiktok", name: "TikTok", shortName: "TikTok", target: "1/hari", category: "DAILY", icon: "video" },
  { id: "live-day", name: "Live Streaming Day", shortName: "Live Day", target: "1 jam/hari", category: "DAILY", icon: "radio" },
  { id: "live-night", name: "Live Streaming Night", shortName: "Live Night", target: "1 jam/minggu", category: "WEEKLY", icon: "moon" },
  { id: "story", name: "Story Instagram", shortName: "Story IG", target: "1/hari", category: "DAILY", icon: "smartphone" },
  { id: "affiliate", name: "Sapa Grup Affiliate", shortName: "Sapa Affiliate", target: "1/minggu", category: "WEEKLY", icon: "messages" },
  { id: "canvassing", name: "Canvassing", shortName: "Canvassing", target: "3/minggu", category: "WEEKLY", icon: "map" },
  { id: "brosur", name: "Sebar Brosur", shortName: "Sebar Brosur", target: "200/minggu", category: "WEEKLY", icon: "files" },
  { id: "spanduk", name: "Spanduk Kerja Sama", shortName: "Spanduk", target: "6 pcs / 2 bulan", category: "PERIODIC", icon: "flag" },
  { id: "share", name: "Share Konten Facebook", shortName: "Share FB", target: "5/hari", category: "DAILY", icon: "share" },
  { id: "fu", name: "FU BDC", shortName: "FU BDC", target: "30 FU/hari", category: "DAILY", icon: "phone" },
  { id: "absen", name: "Absen Masuk", shortName: "Absen", target: "Ontime", category: "DAILY", icon: "clock" },
  { id: "laporan", name: "Laporan Aktivitas", shortName: "Laporan", target: "1/hari", category: "DAILY", icon: "clipboard" },
];

const dailyIds = activities.filter((item) => item.category === "DAILY").map((item) => item.id);
const names = [
  ["Ahmad Fauzan", "Unit Kediri", "Universitas Kadiri", "Regional 4", 0],
  ["Rina Andayani", "Unit Malang", "Unmer Malang", "Regional 4", 1],
  ["Dimas Pratama", "Unit Surabaya", "UM Surabaya", "Regional 5", 2],
  ["Fajar Ramadhan", "Unit Bogor", "STIE GICI", "Regional 2", 2],
  ["Nisa Rahma", "Unit Jakarta", "Unas", "Regional 1", 3],
  ["Bagus Saputra", "Unit Bandung", "USB YPKP", "Regional 3", 4],
  ["Dewi Lestari", "Unit Bekasi", "UICI", "Regional 2", 4],
  ["Reza Maulana", "Unit Semarang", "STEKOM", "Regional 3", 5],
  ["Andi Kurniawan", "Unit Makassar", "UMI", "Regional 7", 5],
  ["Sinta Maharani", "Unit Solo", "UBY", "Regional 4", 6],
  ["Rizky Hidayat", "Unit Medan", "UNPRI", "Regional 6", 6],
  ["Putri Amelia", "Unit Tangerang", "Umt", "Regional 1", 7],
  ["Ayu Permata", "Unit Depok", "UICI", "Regional 2", 8],
  ["Bayu Aditya", "Unit Sidoarjo", "UM Surabaya", "Regional 5", 8],
  ["Raka Wijaya", "Unit Yogyakarta", "UBY", "Regional 4", 8],
] as const;

export const initialStaff: Staff[] = names.map(([name, unit, campus, regional, completed], index) => ({
  id: index + 1,
  name,
  unit,
  campus,
  regional,
  avatar: `https://i.pravatar.cc/128?img=${index + 11}`,
  completedDaily: completed,
  totalDaily: 8,
  completedActivities: dailyIds.slice(0, completed).map((activityId, activityIndex) => ({
    activityId,
    completedAt: `${String(8 + Math.floor(activityIndex / 2)).padStart(2, "0")}:${activityIndex % 2 ? "31" : "05"}`,
  })),
  finishTime: completed === 8 ? ["13:48", "14:21", "14:42"][index - 12] : undefined,
  lastActivityAt: completed > 0 ? `${String(9 + completed).padStart(2, "0")}:${(index * 7) % 60}`.replace(/:(\d)$/, ":0$1") : undefined,
}));
