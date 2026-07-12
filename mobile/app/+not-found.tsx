import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Link, Stack } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

export default function NotFoundScreen() {
  return (
    <>
      <Stack.Screen options={{ title: 'No encontrado' }} />
      <View style={styles.container}>
        <View style={styles.iconWrap}>
          <MaterialCommunityIcons name="map-marker-question-outline" size={40} color={COLORS.textSecondary} />
        </View>
        <Text style={styles.title}>Esta pantalla no existe</Text>
        <Text style={styles.message}>La ruta a la que intentaste acceder no está disponible.</Text>
        <Link href={'/(app)' as never} replace style={styles.link} accessibilityRole="link">
          Volver a inicio
        </Link>
      </View>
    </>
  );
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
    backgroundColor: COLORS.surfaceElevated,
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
  link: {
    marginTop: SPACING.md,
    fontSize: FONT.bodySize,
    fontWeight: '800',
    color: COLORS.primary,
    minHeight: 44,
    textAlignVertical: 'center',
  },
});
