import { useNavigate } from 'react-router-dom';
import { Users, AlertTriangle, Award } from 'lucide-react';
import type { ParentDashboardData } from '../../services/dashboardService';
import { Button } from '../ui/button';

interface ParentDashboardProps {
    data: ParentDashboardData;
    userName: string;
}

export default function ParentDashboard({ data, userName }: ParentDashboardProps) {
    const navigate = useNavigate();
    const { children, alerts, quick_actions } = data;

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Tableau de bord — Parent</h1>
                <p className="text-gray-600 mt-1">Bienvenue, {userName}.</p>
            </div>

            {alerts?.length > 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start">
                    <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5 mr-3" />
                    <div>
                        <h3 className="font-medium text-amber-900">Alertes présence</h3>
                        <p className="text-sm text-amber-800 mt-1">
                            {alerts.length} enfant(s) avec un taux de présence &lt; 80% (à risque).
                        </p>
                        <ul className="mt-2 space-y-1 text-sm text-amber-800">
                            {alerts.map((c) => (
                                <li key={c.id}>{c.name} — {c.attendance_percent ?? 0}%</li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}

            <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <Users className="w-5 h-5 mr-2 text-indigo-600" />
                    Mes enfants
                </h3>
                {children?.length > 0 ? (
                    <ul className="divide-y divide-gray-200">
                        {children.map((child) => (
                            <li key={child.id} className="py-4">
                                <div className="flex justify-between items-start">
                                    <div>
                                        <p className="font-medium text-gray-900">{child.name}</p>
                                        <p className="text-sm text-gray-500">
                                            {child.filiere ?? '-'} — Groupe {child.groupe ?? '-'}
                                        </p>
                                        {child.attendance_percent != null && (
                                            <p className="text-sm mt-1">
                                                Présence : <span className={child.is_risk ? 'text-amber-600 font-medium' : 'text-gray-600'}>{child.attendance_percent}%</span>
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right">
                                        {child.latest_grades?.length > 0 ? (
                                            <ul className="text-xs text-gray-500 space-y-0.5">
                                                {child.latest_grades.slice(0, 3).map((g, i) => (
                                                    <li key={i}>{g.evaluation}: {g.value}</li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <span className="text-gray-400 text-sm">Aucune note</span>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-gray-500">Aucun enfant rattaché à ce compte.</p>
                )}
            </div>

            <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-3">Raccourcis</h3>
                <Button onClick={() => navigate('/parent/children')}>
                    Voir les enfants
                </Button>
            </div>
        </div>
    );
}
