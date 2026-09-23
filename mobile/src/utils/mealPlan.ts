import type { MealPlan, MealPlanEntry } from '@/types/mealPlan';

export function localDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function weekRange(offset = 0, today = new Date()) {
  const start = new Date(today);
  start.setHours(12, 0, 0, 0);
  start.setDate(start.getDate() - (start.getDay() || 7) + 1 + offset * 7);
  const end = new Date(start);
  end.setDate(start.getDate() + 6);
  return { start_date: localDate(start), end_date: localDate(end) };
}

export function entriesInWeek(plans: MealPlan[], start: string, end: string): [string, MealPlanEntry[]][] {
  const days: Record<string, MealPlanEntry[]> = {};
  for (const plan of plans) {
    if (['cancelled', 'archived'].includes(plan.status)) continue;
    for (const entry of plan.items ?? []) {
      const date = entry.date.slice(0, 10);
      if (date < start || date > end || entry.status === 'cancelled') continue;
      (days[date] ??= []).push(entry);
    }
  }
  return Object.entries(days).sort(([a], [b]) => a.localeCompare(b));
}

const labels: Record<string, string> = {
  daily: 'Diario', weekly: 'Semanal', monthly: 'Mensual', manual: 'Manual', automatic: 'Automático',
  draft: 'Borrador', active: 'Activo', planned: 'Planificada', completed: 'Completado',
  cooked: 'Cocinada', skipped: 'Omitida', eating_out: 'Comer afuera', cancelled: 'Cancelado', generated: 'Generado',
};
export function mealPlanLabel(value: string): string { return labels[value] ?? 'Sin definir'; }
