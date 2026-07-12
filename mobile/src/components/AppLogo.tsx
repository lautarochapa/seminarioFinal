import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, RADIUS, SPACING } from '@/utils/theme';

type LogoVariant = 'small' | 'medium' | 'large' | 'iconOnly' | 'withText';

interface AppLogoProps {
  variant?: LogoVariant;
  inverted?: boolean;
}

const SIZE_MAP = {
  small: { icon: 20, container: 32, text: 13, tagline: false },
  medium: { icon: 28, container: 44, text: 17, tagline: false },
  large: { icon: 40, container: 64, text: 22, tagline: true },
  iconOnly: { icon: 28, container: 44, text: 0, tagline: false },
  withText: { icon: 32, container: 52, text: 18, tagline: true },
} as const;

export function AppLogo({ variant = 'medium', inverted = false }: AppLogoProps) {
  const size = SIZE_MAP[variant === 'iconOnly' ? 'iconOnly' : variant];
  const iconColor = inverted ? '#FFFFFF' : COLORS.primary;
  const textColor = inverted ? '#FFFFFF' : COLORS.textPrimary;
  const bgColor = inverted ? 'rgba(255,255,255,0.15)' : COLORS.primarySurface;

  const showText = variant !== 'iconOnly';

  return (
    <View style={styles.wrapper} accessible accessibilityLabel="CocinaComidaControl">
      {/* Icon mark: pot + checkmark */}
      <View
        style={[
          styles.iconContainer,
          {
            width: size.container,
            height: size.container,
            borderRadius: RADIUS.md,
            backgroundColor: bgColor,
          },
        ]}
      >
        <MaterialCommunityIcons
          name="pot-steam"
          size={size.icon}
          color={iconColor}
        />
      </View>

      {showText && (
        <View style={styles.textBlock}>
          <Text
            style={[
              styles.name,
              {
                fontSize: size.text,
                color: textColor,
              },
            ]}
            numberOfLines={1}
          >
            <Text style={[styles.accent, { color: iconColor }]}>Cocina</Text>
            <Text style={{ color: inverted ? 'rgba(255,255,255,0.75)' : COLORS.textSecondary }}>Comida</Text>
            <Text style={[styles.accent, { color: iconColor }]}>Control</Text>
          </Text>
          {size.tagline ? (
            <Text
              style={[
                styles.tagline,
                { color: inverted ? 'rgba(255,255,255,0.65)' : COLORS.textHint },
              ]}
            >
              Tu cocina, tu presupuesto, tu hogar.
            </Text>
          ) : null}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.sm,
  },
  iconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  textBlock: {
    gap: 2,
  },
  name: {
    fontWeight: '700',
    letterSpacing: -0.3,
  },
  accent: {
    fontWeight: '800',
  },
  tagline: {
    fontSize: 11,
    fontWeight: '400',
    letterSpacing: 0.1,
  },
});
