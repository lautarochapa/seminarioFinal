import type { Notification } from '@/types/retail';

// The notifications table has no entity_type/entity_id, so we can only route to the
// relevant section (not a specific record). See Requerimientos de base de datos.
const TYPE_ROUTES: Record<string, string> = {
  vencimientos: '/(app)/stock',
  bajo_stock: '/(app)/stock',
  menu: '/(app)/planning',
  presupuesto: '/(app)/budgets',
  compras: '/(app)/purchases',
};

export function routeForNotification(notification: Notification): string | null {
  return TYPE_ROUTES[notification.type] ?? null;
}
