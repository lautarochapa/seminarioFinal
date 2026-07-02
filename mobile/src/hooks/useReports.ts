import { reportsApi } from '@/api/endpoints';
import { useRetailData } from '@/hooks/useRetailList';
import type { ReportPeriod, UserReport } from '@/types/retail';

function numberValue(data: Record<string, unknown>, key: string): string {
  const value = data[key];
  if (typeof value === 'number') return value.toLocaleString('es-AR');
  if (typeof value === 'string') return value;
  return '0';
}

export function useReports(groupId: number | null, period: ReportPeriod) {
  return useRetailData(async (): Promise<UserReport[]> => {
    if (!groupId) return [];
    const [stock, stockValue, waste, purchases, budgetVsActual] = await Promise.all([
      reportsApi.stock(groupId),
      reportsApi.stockValue(groupId),
      reportsApi.waste(groupId, period),
      reportsApi.purchases(groupId, period),
      reportsApi.budgetVsActual(groupId),
    ]);
    const budgetPeriods = Array.isArray(budgetVsActual.data.periods) ? budgetVsActual.data.periods : [];
    const firstBudget = budgetPeriods[0] as Record<string, unknown> | undefined;
    const progress = typeof firstBudget?.consumed_percent === 'number' ? firstBudget.consumed_percent : null;
    return [
      { key: 'stock', title: 'Stock', value: numberValue(stock.data, 'total_items'), subtitle: 'items activos' },
      { key: 'stock-value', title: 'Valor estimado', value: numberValue(stockValue.data, 'total_value'), subtitle: 'stock con precio' },
      { key: 'waste', title: 'Desperdicio', value: numberValue(waste.data, 'total_quantity'), subtitle: 'cantidad descartada' },
      { key: 'purchases', title: 'Compras', value: numberValue(purchases.data, 'total_spent'), subtitle: 'gasto del periodo' },
      { key: 'budget', title: 'Presupuesto', value: progress === null ? 'Sin datos' : `${progress}%`, subtitle: 'consumido', progress },
    ];
  }, [groupId, period]);
}
