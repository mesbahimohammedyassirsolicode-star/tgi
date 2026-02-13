import api from '../../lib/axios';
import { unwrapData, type ApiResponse } from '../../lib/api';

export interface TimetableSeance {
  id: number;
  date: string;
  start_time: string;
  end_time: string;
  salle?: string;
  status: string;
  affectation?: { module?: { label: string }; groupe?: { label: string }; formateur?: { user?: { name: string } } };
}

export interface TimetableResponse {
  week_start: string;
  week_end: string;
  seances: TimetableSeance[];
  by_date: Record<string, TimetableSeance[]>;
}

export const timetableApi = {
  get: (params?: { groupe_id?: number; formateur_id?: number; week_start?: string }) =>
    api.get<ApiResponse<TimetableResponse>>('/timetable', { params }).then(unwrapData),
};
