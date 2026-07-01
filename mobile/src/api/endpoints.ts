import { apiClient } from './client';
import type { LoginRequest, LoginResponse, MeResponse } from '@/types/auth';
import type { ApiResponse } from '@/types/api';
import type { Profile } from '@/types/profile';
import type {
  FamilyGroup,
  FamilyGroupMember,
  FamilyGroupsResponse,
  CreateFamilyGroupRequest,
  InviteMemberRequest,
} from '@/types/familyGroup';

export const authApi = {
  login(payload: LoginRequest): Promise<LoginResponse> {
    return apiClient.post<LoginResponse>('/api/v1/auth/login', payload, { skipAuth: true });
  },
  me(): Promise<MeResponse> {
    return apiClient.get<MeResponse>('/api/v1/auth/me');
  },
  logout(): Promise<void> {
    return apiClient.post<void>('/api/v1/auth/logout');
  },
};

export const profileApi = {
  get(): Promise<ApiResponse<Profile>> {
    return apiClient.get<ApiResponse<Profile>>('/api/v1/users/me/profile');
  },
};

export const familyGroupsApi = {
  list(): Promise<FamilyGroupsResponse> {
    return apiClient.get<FamilyGroupsResponse>('/api/v1/family-groups');
  },
  get(id: number): Promise<ApiResponse<FamilyGroup>> {
    return apiClient.get<ApiResponse<FamilyGroup>>(`/api/v1/family-groups/${id}`);
  },
  create(payload: CreateFamilyGroupRequest): Promise<ApiResponse<FamilyGroup>> {
    return apiClient.post<ApiResponse<FamilyGroup>>('/api/v1/family-groups', payload);
  },
  members(id: number): Promise<ApiResponse<FamilyGroupMember[]>> {
    return apiClient.get<ApiResponse<FamilyGroupMember[]>>(`/api/v1/family-groups/${id}/members`);
  },
  invite(id: number, payload: InviteMemberRequest): Promise<ApiResponse<unknown>> {
    return apiClient.post<ApiResponse<unknown>>(`/api/v1/family-groups/${id}/invitations`, payload);
  },
};
