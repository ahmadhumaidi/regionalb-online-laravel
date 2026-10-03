export type ActivityCategory = "DAILY" | "WEEKLY" | "PERIODIC";

export interface ActivityDefinition {
  id: string;
  name: string;
  shortName: string;
  target: string;
  category: ActivityCategory;
  icon: string;
}

export interface CompletedActivity {
  activityId: string;
  completedAt: string;
}

export interface Staff {
  id: number;
  name: string;
  unit: string;
  campus: string;
  regional: string;
  avatar: string;
  completedDaily: number;
  totalDaily: number;
  completedActivities: CompletedActivity[];
  finishTime?: string;
  lastActivityAt?: string;
}

export interface DailyGamificationResponse {
  date: string;
  totalDailyActivities: number;
  staff: Staff[];
}

export const progressStatus = (staff: Staff) => {
  if (staff.completedDaily <= 0) return "START";
  if (staff.completedDaily >= staff.totalDaily) return "FINISH";
  return `CHECKPOINT ${staff.completedDaily}`;
};

export const progressPercentage = (staff: Staff) =>
  Math.min(100, (staff.completedDaily / staff.totalDaily) * 100);
