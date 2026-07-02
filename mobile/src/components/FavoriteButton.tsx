import React from 'react';
import { ActivityIndicator, Pressable, StyleSheet } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { COLORS, TOUCH_TARGET } from '@/utils/theme';

interface Props {
  active: boolean;
  loading?: boolean;
  onPress: () => void;
}

export function FavoriteButton({ active, loading = false, onPress }: Props) {
  return (
    <Pressable
      onPress={onPress}
      disabled={loading}
      accessibilityRole="button"
      accessibilityLabel={active ? 'Quitar de favoritos' : 'Agregar a favoritos'}
      style={styles.btn}
    >
      {loading ? (
        <ActivityIndicator size="small" color={COLORS.primary} />
      ) : (
        <MaterialCommunityIcons
          name={active ? 'heart' : 'heart-outline'}
          size={24}
          color={active ? COLORS.error : COLORS.primary}
        />
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: { width: TOUCH_TARGET, height: TOUCH_TARGET, alignItems: 'center', justifyContent: 'center' },
});
