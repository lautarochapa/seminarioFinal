import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { COLORS, FONT, RADIUS, SPACING } from '@/utils/theme';

const STATUS_CONFIG: Record<string, { label: string; bg: string; color: string }> = {
  draft:       { label: 'Borrador',    bg: COLORS.skeleton,    color: COLORS.textSecondary },
  active:      { label: 'Lista para comprar', bg: COLORS.infoLight, color: COLORS.info },
  in_progress: { label: 'En compra',   bg: COLORS.warningLight, color: COLORS.warning },
  completed:   { label: 'Completada',  bg: COLORS.successLight, color: COLORS.success },
  cancelled:   { label: 'Cancelada',   bg: COLORS.errorLight,   color: COLORS.error },
  pending:     { label: 'Pendiente',   bg: COLORS.warningLight, color: COLORS.warning },
  purchased:   { label: 'Comprado',    bg: COLORS.successLight, color: COLORS.success },
  skipped:     { label: 'Omitido',     bg: COLORS.skeleton,     color: COLORS.textSecondary },
  confirmed:   { label: 'Confirmada',  bg: COLORS.successLight, color: COLORS.success },
  open:        { label: 'Abierto',     bg: COLORS.infoLight,   color: COLORS.info },
};

interface StatusBadgeProps {
  status: string;
}

export function StatusBadge({ status }: StatusBadgeProps) {
  const cfg = STATUS_CONFIG[status] ?? {
    label: status,
    bg: COLORS.skeleton,
    color: COLORS.textSecondary,
  };
  return (
    <View style={[styles.badge, { backgroundColor: cfg.bg }]}>
      <Text style={[styles.text, { color: cfg.color }]}>{cfg.label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    borderRadius: RADIUS.full,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 3,
    alignSelf: 'flex-start',
  },
  text: {
    fontSize: FONT.captionSize,
    fontWeight: '600',
  },
});
