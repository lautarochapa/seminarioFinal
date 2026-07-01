import React from 'react';
import {
  Pressable,
  StyleSheet,
  Text,
  View,
  type ViewStyle,
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SHADOW, SPACING, TOUCH_TARGET } from '@/utils/theme';

type MCIcon = React.ComponentProps<typeof MaterialCommunityIcons>['name'];

interface FeatureCardProps {
  icon: MCIcon;
  title: string;
  subtitle?: string;
  onPress?: () => void;
  disabled?: boolean;
  badge?: number | string;
  style?: ViewStyle;
  iconColor?: string;
  iconBg?: string;
  accessibilityLabel?: string;
}

export function FeatureCard({
  icon,
  title,
  subtitle,
  onPress,
  disabled = false,
  badge,
  style,
  iconColor = COLORS.primary,
  iconBg = COLORS.primarySurface,
  accessibilityLabel,
}: FeatureCardProps) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || !onPress}
      accessible
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? title}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [
        styles.card,
        pressed && styles.cardPressed,
        disabled && styles.cardDisabled,
        style,
      ]}
    >
      <View style={[styles.iconWrap, { backgroundColor: iconBg }]}>
        <MaterialCommunityIcons name={icon} size={24} color={disabled ? COLORS.disabled : iconColor} />
        {badge !== undefined && badge !== null ? (
          <View style={styles.badge}>
            <Text style={styles.badgeText} numberOfLines={1}>
              {typeof badge === 'number' && badge > 99 ? '99+' : String(badge)}
            </Text>
          </View>
        ) : null}
      </View>

      <View style={styles.content}>
        <Text style={[styles.title, disabled && styles.titleDisabled]} numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text style={styles.subtitle} numberOfLines={2}>
            {subtitle}
          </Text>
        ) : null}
      </View>

      {onPress ? (
        <MaterialCommunityIcons
          name="chevron-right"
          size={20}
          color={disabled ? COLORS.disabled : COLORS.textHint}
        />
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.md,
    minHeight: TOUCH_TARGET + 8,
    ...SHADOW.sm,
  },
  cardPressed: {
    opacity: 0.85,
    transform: [{ scale: 0.99 }],
  },
  cardDisabled: {
    opacity: 0.5,
  },
  iconWrap: {
    width: 48,
    height: 48,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: COLORS.error,
    borderRadius: RADIUS.full,
    minWidth: 18,
    height: 18,
    paddingHorizontal: 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '700',
  },
  content: {
    flex: 1,
    gap: 2,
  },
  title: {
    fontSize: FONT.subtitleSize,
    fontWeight: FONT.subtitleWeight,
    color: COLORS.textPrimary,
  },
  titleDisabled: {
    color: COLORS.textHint,
  },
  subtitle: {
    fontSize: FONT.captionSize + 1,
    color: COLORS.textSecondary,
    lineHeight: 17,
  },
});
