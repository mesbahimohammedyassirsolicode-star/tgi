import { useParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { parentApi } from '../../services/api/parent';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Loader2, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../../context/AuthContext';

export default function ParentChildDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { data: children, isLoading, error } = useQuery({
    queryKey: ['parent', 'children', user?.id, user?.role],
    queryFn: parentApi.getChildren,
  });
  const child = children?.find((c) => c.id === Number(id));

  const { data: grades } = useQuery({
    queryKey: ['parent', 'children', user?.id, user?.role, id, 'grades'],
    queryFn: () => parentApi.getChildGrades(Number(id)),
    enabled: !!id,
  });

  const { data: attendance } = useQuery({
    queryKey: ['parent', 'children', user?.id, user?.role, id, 'attendance'],
    queryFn: () => parentApi.getChildAttendance(Number(id)),
    enabled: !!id,
  });

  if (error) toast.error('Erreur chargement');

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" onClick={() => navigate('/parent/children')}>
        <ArrowLeft className="w-4 h-4 mr-2" /> Retour
      </Button>
      {isLoading || !child ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <>
          <h1 className="text-2xl font-bold text-gray-900">{child.user?.name ?? `Enfant #${id}`}</h1>
          <Card>
            <CardHeader>
              <CardTitle>Notes récentes</CardTitle>
            </CardHeader>
            <CardContent className="text-sm">
              {Array.isArray(grades) && grades.length > 0 ? (
                <ul className="space-y-1">
                  {grades.slice(0, 10).map((g: { valeur: number; evaluation?: { item_label: string } }, i: number) => (
                    <li key={i}>{g.evaluation?.item_label ?? 'Note'} : {g.valeur}</li>
                  ))}
                </ul>
              ) : (
                <p className="text-gray-500">Aucune note.</p>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Présence</CardTitle>
            </CardHeader>
            <CardContent className="text-sm">
              {attendance?.summary ? (
                <p>
                  Taux : {attendance.summary.rate_percent}% ({attendance.summary.present_count}/{attendance.summary.total_count} séances)
                </p>
              ) : (
                <p className="text-gray-500">Aucune donnée.</p>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}
