import api from '../../lib/axios';
import { unwrapData, type ApiResponse } from '../../lib/api';

export interface TimetableSeance {
  id: number;
  date: string;
  start_time: string;
  end_time: string;
  salle?: string;
  status: string;
  affectation?: {
    module?: { code?: string; label: string };
    groupe?: { label: string };
    formateur?: { user?: { name: string } };
  };
}

export interface TimetableResponse {
  week_start: string;
  week_end: string;
  seances: TimetableSeance[];
  by_date: Record<string, TimetableSeance[]>;
}

const emptyTimetable = (): TimetableResponse => ({
  week_start: '',
  week_end: '',
  seances: [],
  by_date: {},
});

export const timetableApi = {
  get: async (params?: { groupe_id?: number; formateur_id?: number; week_start?: string }): Promise<TimetableResponse> => {
    const res = await api.get<ApiResponse<TimetableResponse>>('/timetable', { params });
    let data: TimetableResponse | undefined;
    try {
      data = unwrapData<TimetableResponse>(res);
    } catch {
      data = undefined;
    }
    if (!data || typeof data !== 'object') {
      return emptyTimetable();
    }
    return {
      week_start: data.week_start ?? '',
      week_end: data.week_end ?? '',
      seances: Array.isArray(data.seances) ? data.seances : [],
      by_date: data.by_date && typeof data.by_date === 'object' && !Array.isArray(data.by_date) ? data.by_date : {},
    };
  },
};
