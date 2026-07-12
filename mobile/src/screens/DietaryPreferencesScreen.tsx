import React from 'react';
import { HealthPreferencesScreen } from './HealthPreferencesScreen';
const SECTIONS = [{ type: 'dietary-restrictions' as const, label: 'Preferencias alimentarias', help: 'Seleccioná las pautas que querés priorizar al buscar recetas y organizar comidas.' }];
export function DietaryPreferencesScreen() { return <HealthPreferencesScreen title="Preferencias alimentarias" sections={SECTIONS} />; }
