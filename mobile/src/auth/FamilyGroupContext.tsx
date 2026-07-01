import React, { createContext, useCallback, useContext, useState } from 'react';
import type { FamilyGroup } from '@/types/familyGroup';

interface FamilyGroupContextValue {
  selectedGroup: FamilyGroup | null;
  selectGroup: (group: FamilyGroup) => void;
  clearGroup: () => void;
}

const FamilyGroupContext = createContext<FamilyGroupContextValue | null>(null);

export function FamilyGroupProvider({ children }: { children: React.ReactNode }) {
  const [selectedGroup, setSelectedGroup] = useState<FamilyGroup | null>(null);

  const selectGroup = useCallback((group: FamilyGroup) => {
    setSelectedGroup(group);
  }, []);

  const clearGroup = useCallback(() => {
    setSelectedGroup(null);
  }, []);

  return (
    <FamilyGroupContext.Provider value={{ selectedGroup, selectGroup, clearGroup }}>
      {children}
    </FamilyGroupContext.Provider>
  );
}

export function useFamilyGroupContext(): FamilyGroupContextValue {
  const ctx = useContext(FamilyGroupContext);
  if (!ctx) throw new Error('useFamilyGroupContext must be used inside FamilyGroupProvider');
  return ctx;
}
