import { useNavigate } from 'react-router-dom';
import { Users, GraduationCap, Briefcase, School, BookOpen, ClipboardList } from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import type { AdminDashboardData } from '../../services/dashboardService';
import { Button } from '../ui/button';

interface AdminDashboardProps {
    data: AdminDashboardData;
    userName: string;
}

export default function AdminDashboard({ data, userName }: AdminDashboardProps) {
    const navigate = useNavigate();
    const { stats, charts, quick_actions } = data;

    const statCards = [
        { title: 'Stagiaires', value: stats.total_students, icon: GraduationCap, color: 'text-blue-600', bg: 'bg-blue-100' },
        { title: 'Formateurs', value: stats.total_teachers, icon: Briefcase, color: 'text-emerald-600', bg: 'bg-emerald-100' },
        { title: 'Filières', value: stats.total_filieres, icon: School, color: 'text-orange-600', bg: 'bg-orange-100' },
        { title: 'Groupes', value: stats.total_groupes, icon: Users, color: 'text-purple-600', bg: 'bg-purple-100' },
    ];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Tableau de bord — Administration</h1>
                <p className="text-gray-600 mt-1">Bienvenue, {userName}.</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {statCards.map(({ title, value, icon: Icon, color, bg }) => (
                    <div key={title} className="p-4 bg-white rounded-lg border border-gray-200 shadow-sm flex items-center">
                        <div className={`p-3 rounded-full ${bg} mr-4`}>
                            <Icon className={`w-6 h-6 ${color}`} />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-gray-500">{title}</p>
                            <p className="text-2xl font-bold text-gray-900">{value}</p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Stagiaires par filière</h3>
                    <div className="h-64">
                        {charts.students_per_filiere?.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={charts.students_per_filiere}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="name" />
                                    <YAxis />
                                    <Tooltip />
                                    <Bar dataKey="value" fill="#4f46e5" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="h-full flex items-center justify-center text-gray-400">Aucune donnée</div>
                        )}
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Raccourcis</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {quick_actions.map(({ label, path }) => (
                            <Button
                                key={path}
                                variant="outline"
                                className="h-auto py-4 justify-start"
                                onClick={() => navigate(path)}
                            >
                                <BookOpen className="w-5 h-5 mr-2 text-gray-600" />
                                {label}
                            </Button>
                        ))}
                        <Button variant="outline" className="h-auto py-4 justify-start" onClick={() => navigate('/users')}>
                            <Users className="w-5 h-5 mr-2" />
                            Gérer les utilisateurs
                        </Button>
                        <Button variant="outline" className="h-auto py-4 justify-start" onClick={() => navigate('/groups')}>
                            <ClipboardList className="w-5 h-5 mr-2" />
                            Groupes
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
