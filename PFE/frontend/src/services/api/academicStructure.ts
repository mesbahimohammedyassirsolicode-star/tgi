import api from '../../lib/axios';
import { unwrapData, type ApiResponse } from '../../lib/api';

export interface AnneeScolaire {
  id: number;
  year_start: number;
  year_end: number;
  label: string;
  is_current: boolean;
  start_date: string;
  end_date: string;
}

export interface Niveau {
  id: number;
  label: string;
  code: string;
}

export interface Filiere {
  id: number;
  niveau_id: number;
  label: string;
  code: string;
  description?: string;
  niveau?: Niveau;
}

export const academicStructureApi = {
  getYears: () => api.get<ApiResponse<AnneeScolaire[]>>('/academic-structure/years').then(unwrapData),
  createYear: (body: Partial<AnneeScolaire>) =>
    api.post<ApiResponse<AnneeScolaire>>('/academic-structure/years', body).then(unwrapData),
  updateYear: (id: number, body: Partial<AnneeScolaire>) =>
    api.put<ApiResponse<AnneeScolaire>>(`/academic-structure/years/${id}`, body).then(unwrapData),
  deleteYear: (id: number) => api.delete(`/academic-structure/years/${id}`),
  getLevels: () => api.get<ApiResponse<Niveau[]>>('/academic-structure/levels').then(unwrapData),
  getFilieres: (niveauId?: number) =>
    api
      .get<ApiResponse<Filiere[]>>('/academic-structure/filieres', { params: niveauId ? { niveau_id: niveauId } : {} })
      .then(unwrapData),
  createFiliere: (body: { niveau_id: number; label: string; code: string; description?: string }) =>
    api.post<ApiResponse<Filiere>>('/academic-structure/filieres', body).then(unwrapData),
  updateFiliere: (id: number, body: Partial<Filiere>) =>
    api.put<ApiResponse<Filiere>>(`/academic-structure/filieres/${id}`, body).then(unwrapData),
  deleteFiliere: (id: number) => api.delete(`/academic-structure/filieres/${id}`),
};
