import React, { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { stockApi } from '@/api/endpoints';
import { buildStockAlertMessages, type StockAlertMessage } from '@/utils/stockAlerts';
import { COLORS, FONT_SIZE, RADIUS, SPACING } from '@/utils/theme';

/**
 * Muestra, encima del listado de stock, avisos legibles de:
 *  - productos por debajo del stock minimo (GET /stock/low-stock)
 *  - productos proximos a vencer o vencidos (GET /stock/expiring)
 * Usa endpoints existentes; no hay push ni jobs nuevos.
 */
export function StockAlertsBanner({ groupId }: { groupId: number | null }) {
  const [messages, setMessages] = useState<StockAlertMessage[]>([]);

  const load = useCallback(async () => {
    if (!groupId) {
      setMessages([]);
      return;
    }
    try {
      const [low, expiring] = await Promise.all([
        stockApi.lowStock(groupId),
        stockApi.expiring(groupId),
      ]);
      setMessages(buildStockAlertMessages(low.data ?? [], expiring.data ?? []));
    } catch {
      setMessages([]);
    }
  }, [groupId]);

  useEffect(() => {
    void load();
  }, [load]);

  if (messages.length === 0) {
    return null;
  }

  return (
    <View style={styles.wrap} accessibilityRole="summary">
      {messages.map((message) => (
        <View key={message.key} style={styles.row}>
          <MaterialCommunityIcons
            name={message.kind === 'low_stock' ? 'alert-decagram-outline' : 'clock-alert-outline'}
            size={16}
            color={message.kind === 'low_stock' ? COLORS.warning : COLORS.error}
          />
          <Text style={styles.text}>{message.text}</Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    gap: SPACING.xs,
    marginHorizontal: SPACING.md,
    marginTop: SPACING.md,
    borderLeftWidth: 3,
    borderLeftColor: COLORS.warning,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs },
  text: { flex: 1, fontSize: FONT_SIZE.sm, color: COLORS.textPrimary },
});
