import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from './AppButton';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

type EmptyIcon =
  | 'home-outline'
  | 'basket-outline'
  | 'package-variant-closed'
  | 'package-variant'
  | 'clipboard-list-outline'
  | 'chef-hat'
  | 'account-group-outline'
  | 'pot-outline'
  | 'store-search-outline'
  | 'map-marker-off-outline'
  | 'ticket-percent-outline'
  | 'credit-card-off-outline'
  | 'bell-off-outline'
  | 'cart-outline'
  | 'currency-usd-off'
  | 'chart-box-outline';

interface EmptyStateProps {
  message: string;
  icon?: EmptyIcon;
  actionTitle?: string;
  onAction?: () => void;
}

export function EmptyState({
  message,
  icon = 'clipboard-list-outline',
  actionTitle,
  onAction,
}: EmptyStateProps) {
  return (
    <View style={styles.container}>
      <View style={styles.iconWrap}>
        <MaterialCommunityIcons name={icon} size={40} color={COLORS.primary} />
      </View>
      <Text style={styles.message}>{message}</Text>
      {actionTitle && onAction ? (
        <AppButton title={actionTitle} onPress={onAction} style={styles.button} />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xl,
    gap: SPACING.md,
  },
  iconWrap: {
    width: 72,
    height: 72,
    borderRadius: RADIUS.xl,
    backgroundColor: COLORS.primarySurface,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.sm,
  },
  message: {
    fontSize: FONT.bodySize,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: FONT.bodyLineHeight,
  },
  button: {
    marginTop: SPACING.sm,
    paddingHorizontal: SPACING.xl,
  },
});
