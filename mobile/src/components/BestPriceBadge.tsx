import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export function BestPriceBadge() {
  return (
    <View style={styles.badge}>
      <MaterialCommunityIcons name="star" size={13} color={COLORS.warning} />
      <Text style={styles.text}>Mejor precio</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    gap: 4,
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.warningLight,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 3,
  },
  text: { color: COLORS.warning, fontSize: FONT.captionSize, fontWeight: '700' },
});
