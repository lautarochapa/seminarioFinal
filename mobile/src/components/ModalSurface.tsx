import React from 'react';
import { View, type ViewProps } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';

export function ModalSurface({ style, children, ...props }: ViewProps) {
  const insets = useSafeAreaInsets();
  return <View {...props} style={[style, { paddingTop: insets.top, paddingBottom: insets.bottom }]}>
    <StatusBar style="dark" />
    {children}
  </View>;
}
