import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppButton } from './AppButton';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

interface Props {
  children: React.ReactNode;
  onReset?: () => void;
  onGoHome?: () => void;
}

interface State {
  error: Error | null;
}

export class ErrorBoundary extends React.Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error): void {
    if (__DEV__) {
      console.error('ErrorBoundary caught an error:', error);
    }
  }

  private handleReset = (): void => {
    this.setState({ error: null });
    this.props.onReset?.();
  };

  private handleGoHome = (): void => {
    this.setState({ error: null });
    this.props.onGoHome?.();
  };

  render() {
    if (!this.state.error) {
      return this.props.children;
    }

    return (
      <View style={styles.container}>
        <View style={styles.iconWrap}>
          <MaterialCommunityIcons name="alert-circle-outline" size={40} color={COLORS.error} />
        </View>
        <Text style={styles.title}>Ocurrió un error inesperado</Text>
        <Text style={styles.message}>
          La aplicación encontró un problema. Podés intentar de nuevo o volver al inicio.
        </Text>
        {__DEV__ && this.state.error.message ? (
          <Text selectable style={styles.debug}>
            {this.state.error.message}
          </Text>
        ) : null}
        <AppButton title="Reintentar" onPress={this.handleReset} fullWidth style={styles.button} />
        {this.props.onGoHome ? (
          <AppButton title="Volver a inicio" variant="outline" onPress={this.handleGoHome} fullWidth />
        ) : null}
      </View>
    );
  }
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xl,
    gap: SPACING.md,
    backgroundColor: COLORS.background,
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
  title: {
    fontSize: FONT.subtitleSize,
    fontWeight: '800',
    color: COLORS.textPrimary,
    textAlign: 'center',
  },
  message: {
    fontSize: FONT.bodySize,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: FONT.bodyLineHeight,
  },
  debug: {
    fontSize: FONT.captionSize,
    color: COLORS.textHint,
    fontFamily: 'monospace',
    textAlign: 'center',
  },
  button: {
    marginTop: SPACING.sm,
  },
});
