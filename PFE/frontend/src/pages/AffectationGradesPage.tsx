import { useParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { gradesSummaryApi } from '../services/api/gradesSummary';
import { affectationsApi } from '../services/api/affectations';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Loader2, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../context/AuthContext';

export default function AffectationGradesPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const affectationId = Number(id);

  const { data: affectation } = useQuery({
    queryKey: ['affectations', user?.id, user?.role, affectationId],
    queryFn: () => affectationsApi.get(affectationId),
    enabled: !!affectationId,
  });

  const { data: summary, isLoading, error } = useQuery({
    queryKey: ['grades-summary', user?.id, user?.role, affectationId],
    queryFn: () => gradesSummaryApi.summaryByAffectation(affectationId),
    enabled: !!affectationId,
  });

  if (error) toast.error('Erreur chargement des notes');

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" onClick={() => navigate('/affectations')}>
        <ArrowLeft className="w-4 h-4 mr-2" /> Retour
      </Button>
      <h1 className="text-2xl font-bold text-gray-900">
        Notes — {affectation?.module?.label ?? `Affectation #${id}`}
      </h1>
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
        </div>
      ) : (
        <div className="space-y-4">
          {summary?.map((row) => (
            <Card key={row.stagiaire_id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">
                  {row.stagiaire?.user?.name ?? `Stagiaire #${row.stagiaire_id}`}
                  <span className="ml-2 text-base font-normal text-primary-600">
                    Moyenne module : {row.module_average_over_20}/20
                  </span>
                </CardTitle>
              </CardHeader>
              <CardContent className="text-sm">
                <ul className="space-y-1">
                  {row.evaluations?.map((e) => (
                    <li key={e.evaluation_id}>
                      {e.item_label} ({e.type}) : {e.valeur}/{e.max_points} — coef. {e.coefficient}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
