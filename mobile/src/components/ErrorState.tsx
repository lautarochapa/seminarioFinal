import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from './AppButton';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

type ErrorType = 'network' | 'server' | 'auth' | 'generic';

interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
  traceId?: string;
  type?: ErrorType;
}

const ICON_MAP: Record<ErrorType, React.ComponentProps<typeof MaterialCommunityIcons>['name']> = {
  network: 'wifi-off',
  server: 'server-off',
  auth: 'lock-outline',
  generic: 'alert-circle-outline',
};

export function ErrorState({ message, onRetry, traceId, type = 'generic' }: ErrorStateProps) {
  const icon = ICON_MAP[type];

  return (
    <View style={styles.container}>
      <View style={styles.iconWrap}>
        <MaterialCommunityIcons name={icon} size={40} color={COLORS.error} />
      </View>
      <Text style={styles.message}>{message}</Text>
      {traceId ? (
        <Text style={styles.trace} selectable accessibilityLabel={`Código de error: ${traceId}`}>
          {`#${traceId.slice(0, 8)}`}
        </Text>
      ) : null}
      {onRetry ? (
        <AppButton
          title="Reintentar"
          onPress={onRetry}
          variant="outline"
          style={styles.button}
        />
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
    backgroundColor: COLORS.errorLight,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.sm,
  },
  message: {
    fontSize: FONT.bodySize,
    color: COLORS.textPrimary,
    textAlign: 'center',
    lineHeight: FONT.bodyLineHeight,
  },
  trace: {
    fontSize: FONT.captionSize,
    color: COLORS.textHint,
    fontFamily: 'monospace',
  },
  button: {
    marginTop: SPACING.sm,
    paddingHorizontal: SPACING.xl,
  },
});
