import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export function UnreadBadge({ count }: { count: number }) {
  if (count <= 0) return null;
  return (
    <View style={styles.badge}>
      <Text style={styles.text}>{count}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: { minWidth: 24, height: 24, borderRadius: RADIUS.full, backgroundColor: COLORS.error, alignItems: 'center', justifyContent: 'center', paddingHorizontal: SPACING.xs },
  text: { color: COLORS.textInverse, fontSize: FONT.captionSize, fontWeight: '800' },
});
