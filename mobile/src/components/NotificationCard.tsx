import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { formatDate } from '@/utils/retail';
import { routeForNotification } from '@/utils/notificationNavigation';
import type { Notification } from '@/types/retail';

export function NotificationCard({ item, onRead, onNavigate }: { item: Notification; onRead: () => void; onNavigate?: (route: string) => void }) {
  const unread = !item.read_at;
  const route = routeForNotification(item);

  function handlePress() {
    onRead();
    if (route && onNavigate) onNavigate(route);
  }

  return (
    <Pressable
      style={[styles.card, unread && styles.unread]}
      onPress={handlePress}
      accessibilityRole="button"
      accessibilityLabel={`${item.title}. ${unread ? 'No leída.' : 'Leída.'}`}
      accessibilityHint={route ? 'Toca para ver el detalle relacionado' : undefined}
    >
      <View style={styles.row}>
        <Text style={styles.title}>{item.title}</Text>
        <Text style={styles.type}>{item.type}</Text>
      </View>
      <Text style={styles.message}>{item.message}</Text>
      <View style={styles.footer}>
        <Text style={styles.meta}>{item.channel} - {formatDate(item.created_at)}</Text>
        {route ? <MaterialCommunityIcons name="chevron-right" size={16} color={COLORS.textHint} /> : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.xs, ...SHADOW.sm },
  unread: { borderLeftWidth: 4, borderLeftColor: COLORS.primary },
  row: { flexDirection: 'row', justifyContent: 'space-between', gap: SPACING.sm },
  title: { flex: 1, fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  type: { fontSize: FONT.captionSize, color: COLORS.primary, fontWeight: '700' },
  message: { fontSize: FONT.captionSize + 1, color: COLORS.textSecondary },
  footer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  meta: { fontSize: FONT.captionSize, color: COLORS.textHint },
});
