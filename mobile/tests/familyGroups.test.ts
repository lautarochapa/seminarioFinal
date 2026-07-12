import type { FamilyGroupsResponse, FamilyGroup } from '../src/types/familyGroup';

describe('FamilyGroupsResponse shape', () => {
  it('family-groups response has NO meta field (not paginated)', () => {
    const response: FamilyGroupsResponse = {
      data: [
        {
          id: 1,
          name: 'Familia Demo',
          owner_user_id: 1,
          city_id: 1,
          default_address: 'Mitre 1234',
          default_latitude: '-41.13',
          default_longitude: '-71.31',
          status: 'active',
          created_at: '2026-07-01T00:00:00Z',
          updated_at: '2026-07-01T00:00:00Z',
          deleted_at: null,
        },
      ],
      trace_id: 'test-trace',
    };

    expect(Array.isArray(response.data)).toBe(true);
    expect(response.data.length).toBe(1);
    // Verify NO meta property exists in the type
    expect((response as unknown as Record<string, unknown>)['meta']).toBeUndefined();
    expect(response.trace_id).toBe('test-trace');
  });

  it('family group has required fields', () => {
    const group: FamilyGroup = {
      id: 1,
      name: 'Test',
      owner_user_id: 2,
      city_id: null,
      default_address: null,
      default_latitude: null,
      default_longitude: null,
      status: 'active',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
      deleted_at: null,
    };
    expect(group.id).toBe(1);
    expect(group.owner_user_id).toBe(2);
  });

  it('handles empty group list gracefully', () => {
    const response: FamilyGroupsResponse = { data: [], trace_id: '' };
    expect(response.data.length).toBe(0);
  });
});
