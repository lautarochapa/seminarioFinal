import { useCallback, useEffect, useState } from 'react';
import { familyGroupsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { FamilyGroup, FamilyGroupMember } from '@/types/familyGroup';
import type { NormalizedError } from '@/types/api';

interface GroupDetailState {
  group: FamilyGroup | null;
  members: FamilyGroupMember[];
  loading: boolean;
  error: NormalizedError | null;
  refresh: () => void;
}

export function useFamilyGroupDetail(id: number): GroupDetailState {
  const [group, setGroup] = useState<FamilyGroup | null>(null);
  const [members, setMembers] = useState<FamilyGroupMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [version, setVersion] = useState(0);

  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    Promise.all([familyGroupsApi.get(id), familyGroupsApi.members(id)])
      .then(([groupRes, membersRes]) => {
        if (!cancelled) {
          setGroup(groupRes.data);
          const raw = membersRes.data;
          setMembers(Array.isArray(raw) ? raw : [raw as FamilyGroupMember]);
          setLoading(false);
        }
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          if (err instanceof ApiError) {
            setError(err.normalized);
          } else {
            setError({ status: 0, code: 'UNKNOWN', message: 'Error desconocido.', fieldErrors: {}, traceId: '', isNetworkError: false, isTimeoutError: false });
          }
          setLoading(false);
        }
      });

    return () => { cancelled = true; };
  }, [id, version]);

  return { group, members, loading, error, refresh };
}
