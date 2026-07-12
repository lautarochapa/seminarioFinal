import React, { forwardRef, useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { AppInput } from './AppInput';
import { COLORS, TOUCH_TARGET } from '@/utils/theme';
import type { TextInputProps } from 'react-native';

interface PasswordInputProps extends Omit<TextInputProps, 'secureTextEntry'> {
  label?: string;
  error?: string;
  hint?: string;
}

export const PasswordInput = forwardRef<TextInput, PasswordInputProps>(
  function PasswordInput({ label = 'Contraseña', error, hint, ...rest }, ref) {
    const [visible, setVisible] = useState(false);

    return (
      <View>
        <AppInput
          ref={ref}
          label={label}
          error={error}
          hint={hint}
          secureTextEntry={!visible}
          autoCapitalize="none"
          autoCorrect={false}
          {...rest}
        />
        <Pressable
          style={styles.toggle}
          onPress={() => setVisible((v) => !v)}
          accessibilityLabel={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
          accessibilityRole="button"
          hitSlop={8}
        >
          <MaterialCommunityIcons
            name={visible ? 'eye-off-outline' : 'eye-outline'}
            size={20}
            color={COLORS.textSecondary}
          />
        </Pressable>
      </View>
    );
  },
);

const styles = StyleSheet.create({
  toggle: {
    position: 'absolute',
    right: 14,
    top: 32,
    height: TOUCH_TARGET - 8,
    justifyContent: 'center',
  },
});
