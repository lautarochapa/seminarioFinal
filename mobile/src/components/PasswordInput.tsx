import React, { forwardRef, useState } from 'react';
import { StyleSheet, TouchableOpacity, View, Text, TextInput } from 'react-native';
import { AppInput } from './AppInput';
import { COLORS, TOUCH_TARGET } from '@/utils/theme';
import type { TextInputProps } from 'react-native';

interface PasswordInputProps extends Omit<TextInputProps, 'secureTextEntry'> {
  label?: string;
  error?: string;
}

export const PasswordInput = forwardRef<TextInput, PasswordInputProps>(
  function PasswordInput({ label = 'Contraseña', error, ...rest }, ref) {
    const [visible, setVisible] = useState(false);

    return (
      <View>
        <AppInput
          ref={ref}
          label={label}
          error={error}
          secureTextEntry={!visible}
          autoCapitalize="none"
          autoCorrect={false}
          {...rest}
        />
        <TouchableOpacity
          style={styles.toggle}
          onPress={() => setVisible((v) => !v)}
          accessibilityLabel={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
          accessibilityRole="button"
        >
          <Text style={styles.toggleText}>{visible ? 'Ocultar' : 'Mostrar'}</Text>
        </TouchableOpacity>
      </View>
    );
  },
);

const styles = StyleSheet.create({
  toggle: {
    position: 'absolute',
    right: 12,
    top: 28,
    minHeight: TOUCH_TARGET,
    justifyContent: 'center',
  },
  toggleText: {
    fontSize: 13,
    color: COLORS.primary,
    fontWeight: '500',
  },
});
