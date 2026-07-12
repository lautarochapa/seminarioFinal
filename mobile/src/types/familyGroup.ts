export interface FamilyGroup {
  id: number;
  name: string;
  owner_user_id: number;
  city_id: number | null;
  default_address: string | null;
  default_latitude: string | null;
  default_longitude: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface FamilyGroupMember {
  id: number;
  user_id: number;
  name: string;
  email: string;
  role: 'owner' | 'admin' | 'member';
  status: string;
  joined_at: string;
}

export interface FamilyGroupsResponse {
  data: FamilyGroup[];
  trace_id: string;
}

export interface CreateFamilyGroupRequest {
  name: string;
  default_address?: string;
  city_id?: number;
}

export interface InviteMemberRequest {
  email: string;
}
