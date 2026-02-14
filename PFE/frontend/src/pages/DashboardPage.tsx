import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../context/AuthContext';
import { dashboardService } from '../services/dashboardService';
import AdminDashboard from '../components/dashboard/AdminDashboard';
import TeacherDashboard from '../components/dashboard/TeacherDashboard';
import StudentDashboard from '../components/dashboard/StudentDashboard';
import ParentDashboard from '../components/dashboard/ParentDashboard';
import type {
    AdminDashboardData,
    TeacherDashboardData,
    StudentDashboardData,
    ParentDashboardData,
} from '../services/dashboardService';
import { Loader2 } from 'lucide-react';

export default function DashboardPage() {
    const { user } = useAuth();
    const { data, isLoading, error } = useQuery({
        queryKey: ['dashboard', user?.id],
        queryFn: () => dashboardService.getDashboard(),
        enabled: !!user,
    });

    if (!user) {
        return (
            <div className="p-6 text-gray-500">Veuillez vous connecter.</div>
        );
    }

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-[200px]">
                <Loader2 className="w-8 h-8 animate-spin text-indigo-600" />
            </div>
        );
    }

    if (error || !data) {
        const errMessage =
            (error as { response?: { data?: { message?: string; error?: string } } })?.response?.data?.message
            ?? (error as { response?: { data?: { error?: string } } })?.response?.data?.error
            ?? (error as Error)?.message;
        return (
            <div className="p-6 text-red-600 space-y-2">
                <p>Erreur de chargement du tableau de bord. Réessayez plus tard.</p>
                {errMessage && (
                    <p className="text-sm text-gray-600 font-mono">{errMessage}</p>
                )}
            </div>
        );
    }

    // Use API role first so backend is source of truth; fallback to auth user.role for correct component
    const role = data.role ?? (user.role as 'admin' | 'formateur' | 'stagiaire' | 'parent');
    const payload = data.data as Record<string, unknown>;
    const userName = user.name ?? '';

    switch (role) {
        case 'admin':
            return <AdminDashboard data={payload as AdminDashboardData} userName={userName} />;
        case 'formateur':
            return <TeacherDashboard data={payload as TeacherDashboardData} userName={userName} />;
        case 'stagiaire':
            return <StudentDashboard data={payload as StudentDashboardData} userName={userName} />;
        case 'parent':
            return <ParentDashboard data={payload as ParentDashboardData} userName={userName} />;
        default:
            return (
                <div className="p-6">
                    <h1 className="text-2xl font-bold text-gray-900">Tableau de bord</h1>
                    <p className="text-gray-600 mt-2">Rôle non reconnu ou données indisponibles.</p>
                </div>
            );
    }
}
