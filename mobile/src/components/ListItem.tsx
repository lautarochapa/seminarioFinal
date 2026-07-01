import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { COLORS, FONT_SIZE, RADIUS, SPACING, TOUCH_TARGET } from '@/utils/theme';

interface ListItemProps {
  title: string;
  subtitle?: string;
  onPress?: () => void;
  right?: React.ReactNode;
  accessibilityLabel?: string;
}

export function ListItem({ title, subtitle, onPress, right, accessibilityLabel }: ListItemProps) {
  const content = (
    <View style={styles.row}>
      <View style={styles.text}>
        <Text style={styles.title} numberOfLines={1}>{title}</Text>
        {subtitle ? (
          <Text style={styles.subtitle} numberOfLines={1}>{subtitle}</Text>
        ) : null}
      </View>
      {right ?? <Text style={styles.chevron}>›</Text>}
    </View>
  );

  if (onPress) {
    return (
      <TouchableOpacity
        style={styles.container}
        onPress={onPress}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel ?? title}
      >
        {content}
      </TouchableOpacity>
    );
  }

  return <View style={styles.container}>{content}</View>;
}

const styles = StyleSheet.create({
  container: {
    minHeight: TOUCH_TARGET,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    marginBottom: SPACING.sm,
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.sm,
  },
  text: {
    flex: 1,
    gap: 2,
  },
  title: {
    fontSize: FONT_SIZE.md,
    color: COLORS.textPrimary,
    fontWeight: '500',
  },
  subtitle: {
    fontSize: FONT_SIZE.sm,
    color: COLORS.textSecondary,
  },
  chevron: {
    fontSize: 22,
    color: COLORS.textHint,
  },
});
