import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { formatDate } from '@/utils/retail';
import type { Notification } from '@/types/retail';

export function NotificationCard({ item, onRead }: { item: Notification; onRead: () => void }) {
  const unread = !item.read_at;
  return (
    <Pressable style={[styles.card, unread && styles.unread]} onPress={onRead}>
      <View style={styles.row}>
        <Text style={styles.title}>{item.title}</Text>
        <Text style={styles.type}>{item.type}</Text>
      </View>
      <Text style={styles.message}>{item.message}</Text>
      <Text style={styles.meta}>{item.channel} - {formatDate(item.created_at)}</Text>
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
  meta: { fontSize: FONT.captionSize, color: COLORS.textHint },
});
