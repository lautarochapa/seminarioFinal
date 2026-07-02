import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export function DistanceBadge({ distanceKm }: { distanceKm?: number | null }) {
  if (distanceKm === null || distanceKm === undefined) return null;
  return (
    <View style={styles.badge}>
      <MaterialCommunityIcons name="map-marker-distance" size={14} color={COLORS.primary} />
      <Text style={styles.text}>{distanceKm.toFixed(1)} km</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.primarySurface,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 3,
  },
  text: { color: COLORS.primaryDark, fontSize: FONT.captionSize, fontWeight: '600' },
});
