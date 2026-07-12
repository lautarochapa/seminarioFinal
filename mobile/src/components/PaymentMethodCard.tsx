import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import type { PaymentMethod } from '@/types/retail';

export function PaymentMethodCard({ item }: { item: PaymentMethod }) {
  return (
    <View style={styles.card}>
      <MaterialCommunityIcons name="credit-card-outline" size={24} color={COLORS.primary} />
      <View style={styles.body}>
        <Text style={styles.title}>{item.name}</Text>
        <Text style={styles.meta}>{item.type}{item.issuer ? ` - ${item.issuer}` : ''}</Text>
      </View>
      <Text style={styles.status}>{item.status}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md, backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, ...SHADOW.sm },
  body: { flex: 1 },
  title: { fontSize: FONT.bodySize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  status: { fontSize: FONT.captionSize, color: COLORS.textHint, fontWeight: '600' },
});
