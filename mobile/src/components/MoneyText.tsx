import React from 'react';
import { Text, TextStyle } from 'react-native';

interface MoneyTextProps {
  amount: number | null | undefined;
  currency?: string;
  style?: TextStyle;
}

function formatARS(amount: number): string {
  return amount.toLocaleString('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export function MoneyText({ amount, currency = 'ARS', style }: MoneyTextProps) {
  if (amount == null) return <Text style={style}>—</Text>;
  const symbol = currency === 'ARS' ? '$' : currency === 'USD' ? 'U$S' : currency;
  return <Text style={style}>{symbol} {formatARS(amount)}</Text>;
}
