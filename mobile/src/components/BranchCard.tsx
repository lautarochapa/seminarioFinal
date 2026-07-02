import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { DistanceBadge } from '@/components/DistanceBadge';
import { ServiceBadge } from '@/components/ServiceBadge';
import { COLORS, FONT, RADIUS, SHADOW, SPACING } from '@/utils/theme';
import { branchHasCoordinates } from '@/utils/retail';
import type { SupermarketBranch } from '@/types/retail';

export function BranchCard({ item, onPress }: { item: SupermarketBranch; onPress: () => void }) {
  return (
    <Pressable style={({ pressed }) => [styles.card, pressed && styles.pressed]} onPress={onPress}>
      <View style={styles.top}>
        <View style={styles.icon}>
          <MaterialCommunityIcons name="map-marker-outline" size={22} color={COLORS.primary} />
        </View>
        <View style={styles.body}>
          <Text style={styles.title} numberOfLines={1}>{item.name}</Text>
          <Text style={styles.meta} numberOfLines={1}>{item.chain?.name || 'Cadena sin informar'} - {item.city?.name || 'Ciudad sin informar'}</Text>
          <Text style={styles.address} numberOfLines={2}>{item.address || 'Sin direccion'}</Text>
        </View>
        <MaterialCommunityIcons name="chevron-right" size={20} color={COLORS.textHint} />
      </View>
      <View style={styles.badges}>
        <ServiceBadge label="Delivery" enabled={item.delivery_available} />
        <ServiceBadge label="Pickup" enabled={item.pickup_available} />
        <DistanceBadge distanceKm={item.distance_km} />
        {!branchHasCoordinates(item) ? <Text style={styles.noCoords}>Sin coordenadas</Text> : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.surface, borderRadius: RADIUS.md, padding: SPACING.md, gap: SPACING.sm, ...SHADOW.sm },
  pressed: { opacity: 0.85 },
  top: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  icon: { width: 42, height: 42, borderRadius: RADIUS.md, backgroundColor: COLORS.primarySurface, alignItems: 'center', justifyContent: 'center' },
  body: { flex: 1, gap: 2 },
  title: { fontSize: FONT.subtitleSize, fontWeight: '700', color: COLORS.textPrimary },
  meta: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
  address: { fontSize: FONT.captionSize, color: COLORS.textHint },
  badges: { flexDirection: 'row', gap: SPACING.xs, flexWrap: 'wrap' },
  noCoords: { fontSize: FONT.captionSize, color: COLORS.warning, fontWeight: '600' },
});
