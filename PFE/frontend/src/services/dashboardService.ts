import api from '../lib/axios';

export interface DashboardStats {
    stats: {
        stagiaires_count: number;
        formateurs_count: number;
        groupes_count: number;
        filieres_count: number;
        affectations_count: number;
    };
    charts: {
        students_per_filiere: { name: string; value: number }[];
    };
    recent_users: {
        id: number;
        name: string;
        role: string;
        created_at: string;
    }[];
}

export const dashboardService = {
    async getStats(): Promise<DashboardStats> {
        const response = await api.get<{ data: DashboardStats }>('/dashboard/stats');
        const payload = response.data?.data ?? response.data;
        return payload as DashboardStats;
    },
};
