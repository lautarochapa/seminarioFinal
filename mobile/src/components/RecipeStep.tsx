import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';
import type { RecipeStep as RecipeStepType } from '@/types/recipe';

export function RecipeStep({ step }: { step: RecipeStepType }) {
  return (
    <View style={styles.row}>
      <View style={styles.num}><Text style={styles.numText}>{step.step_number}</Text></View>
      <View style={styles.body}>
        <Text style={styles.text}>{step.description}</Text>
        {step.estimated_minutes ? <Text style={styles.time}>{step.estimated_minutes} min</Text> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: SPACING.sm, paddingVertical: SPACING.sm },
  num: { width: 28, height: 28, borderRadius: RADIUS.full, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  numText: { color: COLORS.textInverse, fontWeight: '700' },
  body: { flex: 1, gap: 3 },
  text: { fontSize: FONT.bodySize, color: COLORS.textPrimary, lineHeight: FONT.bodyLineHeight },
  time: { fontSize: FONT.captionSize, color: COLORS.textSecondary },
});
