import { useNavigate } from 'react-router-dom';
import { BookOpen, Calendar, TrendingUp, Award } from 'lucide-react';
import type { StudentDashboardData } from '../../services/dashboardService';
import { Button } from '../ui/button';

interface StudentDashboardProps {
    data: StudentDashboardData;
    userName: string;
}

export default function StudentDashboard({ data, userName }: StudentDashboardProps) {
    const navigate = useNavigate();
    const { filiere, groupe, syllabus_progress, latest_grades, quick_actions } = data;

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Tableau de bord — Stagiaire</h1>
                <p className="text-gray-600 mt-1">Bienvenue, {userName}.</p>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-3">Ma formation</h3>
                {filiere && groupe ? (
                    <p className="text-gray-600">
                        <span className="font-medium">Filière :</span> {filiere.code} — {filiere.label}
                        <span className="mx-2">|</span>
                        <span className="font-medium">Groupe :</span> {groupe.label}
                    </p>
                ) : (
                    <p className="text-amber-700">Aucune filière ou groupe assigné. Contactez l&apos;administration.</p>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <TrendingUp className="w-5 h-5 mr-2 text-indigo-600" />
                        Progression du syllabus
                    </h3>
                    {syllabus_progress?.length > 0 ? (
                        <ul className="space-y-3">
                            {syllabus_progress.map((p, i) => (
                                <li key={i} className="flex justify-between items-center">
                                    <span className="text-sm">{p.module}</span>
                                    <span className="font-medium">{p.progress_percent}%</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-gray-500">Aucune progression enregistrée.</p>
                    )}
                    <Button variant="outline" size="sm" className="mt-4" onClick={() => navigate('/progress')}>
                        Voir la progression
                    </Button>
                </div>

                <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <Award className="w-5 h-5 mr-2 text-indigo-600" />
                        Dernières notes
                    </h3>
                    {latest_grades?.length > 0 ? (
                        <ul className="space-y-2">
                            {latest_grades.slice(0, 5).map((g, i) => (
                                <li key={i} className="flex justify-between text-sm">
                                    <span>{g.evaluation} ({g.module})</span>
                                    <span className="font-medium">{g.value}</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-gray-500">Aucune note pour le moment.</p>
                    )}
                </div>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-3">Raccourcis</h3>
                <Button onClick={() => navigate('/timetable')}>
                    <Calendar className="w-4 h-4 mr-2" />
                    Emploi du temps
                </Button>
            </div>
        </div>
    );
}
