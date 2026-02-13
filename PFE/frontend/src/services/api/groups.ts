import api from '../../lib/axios';
import { unwrapData, unwrapMeta, type ApiResponse } from '../../lib/api';

export interface Groupe {
  id: number;
  filiere_id: number;
  annee_scolaire_id: number;
  label: string;
  year_level: number;
  capacity: number;
  filiere?: { id: number; label: string; code: string };
  annee_scolaire?: { id: number; label: string };
  stagiaires?: unknown[];
}

export interface GroupsListResponse {
  items: Groupe[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export const groupsApi = {
  list: (params?: { filiere_id?: number; year_id?: number; page?: number; per_page?: number }) =>
    api.get<ApiResponse<Groupe[]>>('/groups', { params }).then((res) => ({
      items: unwrapData(res),
      meta: unwrapMeta(res),
    })),
  get: (id: number) => api.get<ApiResponse<Groupe>>(`/groups/${id}`).then(unwrapData),
  create: (body: { filiere_id: number; annee_scolaire_id: number; label: string; year_level: number; capacity?: number }) =>
    api.post<ApiResponse<Groupe>>('/groups', body).then(unwrapData),
  update: (id: number, body: Partial<Groupe>) =>
    api.put<ApiResponse<Groupe>>(`/groups/${id}`, body).then(unwrapData),
  delete: (id: number) => api.delete(`/groups/${id}`),
  enroll: (groupId: number, stagiaireIds: number[]) =>
    api.post<ApiResponse<{ message: string }>>(`/groups/${groupId}/enroll`, { stagiaire_ids: stagiaireIds }).then(unwrapData),
};
