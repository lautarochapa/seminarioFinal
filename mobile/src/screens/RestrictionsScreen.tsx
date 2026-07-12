import React from 'react';
import { HealthPreferencesScreen } from './HealthPreferencesScreen';
const SECTIONS = [
  { type: 'allergies' as const, label: 'Alergias', help: 'Marcá alergias que requieren una advertencia.' },
  { type: 'health-conditions' as const, label: 'Condiciones de salud', help: 'Registrá condiciones médicas relevantes para tu alimentación.' },
];
export function RestrictionsScreen() { return <HealthPreferencesScreen title="Restricciones y alergias" sections={SECTIONS} />; }
