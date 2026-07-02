import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

interface Props {
  label: string;
  onPrev: () => void;
  onCurrent: () => void;
  onNext: () => void;
}

export function WeekSelector({ label, onPrev, onCurrent, onNext }: Props) {
  return (
    <View style={styles.wrap}>
      <Pressable onPress={onPrev} style={styles.btn} accessibilityLabel="Semana anterior">
        <MaterialCommunityIcons name="chevron-left" size={22} color={COLORS.primary} />
      </Pressable>
      <Pressable onPress={onCurrent} style={styles.center} accessibilityLabel="Semana actual">
        <Text style={styles.label}>{label}</Text>
      </Pressable>
      <Pressable onPress={onNext} style={styles.btn} accessibilityLabel="Semana siguiente">
        <MaterialCommunityIcons name="chevron-right" size={22} color={COLORS.primary} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm },
  btn: { width: 40, height: 40, borderRadius: RADIUS.sm, backgroundColor: COLORS.surface, alignItems: 'center', justifyContent: 'center' },
  center: { flex: 1, minHeight: 40, borderRadius: RADIUS.sm, backgroundColor: COLORS.surface, alignItems: 'center', justifyContent: 'center' },
  label: { fontSize: FONT.labelSize, color: COLORS.textPrimary, fontWeight: '700' },
});
