import api from '../lib/axios';

export interface DashboardResponse<T = unknown> {
    role: 'admin' | 'formateur' | 'stagiaire' | 'parent';
    data: T;
}

export interface AdminDashboardData {
    stats: {
        total_students: number;
        total_teachers: number;
        total_filieres: number;
        total_groupes: number;
    };
    charts: {
        students_per_filiere: { name: string; value: number }[];
    };
    quick_actions: { label: string; path: string }[];
}

export interface TeacherDashboardData {
    todays_sessions: {
        id: number;
        module: string;
        groupe: string;
        filiere: string;
        start_time: string;
        end_time: string;
    }[];
    assigned_modules: {
        module_id: number;
        module_code: string;
        module_label: string;
        groupes: {
            id: number;
            label: string;
            filiere: { id: number; code: string; label: string } | null;
        }[];
    }[];
    quick_actions: { label: string; path: string }[];
}

export interface StudentDashboardData {
    filiere: { id: number; code: string; label: string } | null;
    groupe: { id: number; label: string } | null;
    syllabus_progress: { module: string; progress_percent: number; completed_count: number; total_count: number }[];
    latest_grades: { evaluation: string; module: string; value: number; date: string }[];
    quick_actions: { label: string; path: string }[];
}

export interface ParentChild {
    id: number;
    name: string;
    filiere: string | null;
    groupe: string | null;
    attendance_percent: number | null;
    is_risk: boolean;
    latest_grades: { evaluation: string; module: string; value: number }[];
}

export interface ParentDashboardData {
    children: ParentChild[];
    alerts: ParentChild[];
    quick_actions: { label: string; path: string }[];
}

export const dashboardService = {
    async getDashboard(): Promise<DashboardResponse> {
        const res = await api.get<{ role?: string; data?: unknown } | { data: { role?: string; data?: unknown } }>('/dashboard');
        const raw = res.data ?? {};
        // Support both { role, data } and wrapped { data: { role, data } }
        const payload =
            raw && typeof raw === 'object' && 'data' in raw && raw.data && typeof raw.data === 'object' && 'role' in (raw.data as object)
                ? (raw.data as { role: string; data: unknown })
                : (raw as { role?: string; data?: unknown });
        return {
            role: (payload.role ?? 'admin') as DashboardResponse['role'],
            data: payload.data ?? {},
        };
    },
};
