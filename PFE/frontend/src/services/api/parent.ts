import api from '../../lib/axios';
import { unwrapData, type ApiResponse } from '../../lib/api';

export interface ChildStagiaire {
  id: number;
  cef_number: string;
  status: string;
  user?: { name: string; email: string };
  groupes?: { label: string; filiere?: { label: string } }[];
}

export type ChildGradeRow = { evaluation?: { item_label: string }; valeur: number };

export interface ChildAttendanceResponse {
  attendances: unknown[];
  summary: { from: string; to: string; present_count: number; total_count: number; rate_percent: number };
}

export const parentApi = {
  getChildren: () => api.get<ApiResponse<ChildStagiaire[]>>('/parent/children').then(unwrapData),
  getChildGrades: (stagiaireId: number) =>
    api.get<ApiResponse<ChildGradeRow[]>>(`/parent/children/${stagiaireId}/grades`).then(unwrapData),
  getChildAttendance: (stagiaireId: number, params?: { from?: string; to?: string }) =>
    api.get<ApiResponse<ChildAttendanceResponse>>(`/parent/children/${stagiaireId}/attendance`, { params }).then(unwrapData),
};
