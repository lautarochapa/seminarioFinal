import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useNetworkStatus } from '@/hooks/useNetworkStatus';
import { COLORS, FONT, SPACING } from '@/utils/theme';

export function OfflineBanner() {
  const state = useNetworkStatus();

  if (state === 'online') return null;

  const message = state === 'reconnecting'
    ? 'Reconectando...'
    : 'Sin conexión. Algunos datos pueden estar desactualizados.';

  return (
    <View style={styles.banner} accessibilityRole="alert" accessibilityLiveRegion="polite">
      <MaterialCommunityIcons name={state === 'reconnecting' ? 'wifi-sync' : 'wifi-off'} size={16} color={COLORS.surface} />
      <Text style={styles.text}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: SPACING.xs,
    backgroundColor: COLORS.warning,
    paddingVertical: 6,
    paddingHorizontal: SPACING.sm,
  },
  text: {
    color: COLORS.surface,
    fontSize: FONT.captionSize,
    fontWeight: '700',
  },
});
