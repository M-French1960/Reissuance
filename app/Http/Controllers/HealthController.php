<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Page de sante du jalon 1.
 *
 * Verifie ce qui casse silencieusement : la connexion, mais aussi que le
 * declencheur de machine a etats et la revocation d'ecriture sur le journal
 * d'audit sont bien en place. Une base joignable dont le declencheur a
 * disparu est un systeme sans anti-fraude qui a l'air de fonctionner.
 *
 * DEUX PUBLICS, DEUX REPONSES (D-058). La route est publique, parce qu'une
 * sonde de supervision n'a pas de session. Mais le DETAIL des verifications
 * renseignait un visiteur anonyme sur la version du serveur de base, le nom du
 * compte applicatif, l'hote — le message brut d'une exception PDO y passait tel
 * quel — et sur l'etat des droits du journal d'audit. C'est une carte du
 * systeme offerte a qui la demande.
 *
 * Un anonyme recoit donc le VERDICT et rien d'autre ; le detail est reserve a
 * l'administrateur authentifie, qui en a l'usage. Le code HTTP, lui, ne change
 * pas : 200 ou 503, ce dont une sonde a besoin.
 */
class HealthController extends Controller
{
    /**
     * Au-dela de ce delai, un travail en attente signifie que la file ne
     * defile plus.
     *
     * Quinze minutes : assez pour absorber un pic ou un redemarrage de worker,
     * assez court pour qu'un demandeur ne reste pas une demi-journee sans
     * nouvelle de son dossier.
     */
    private const QUEUE_STALE_MINUTES = 15;

    public function __invoke(Request $request): View|JsonResponse
    {
        $checks = [
            $this->database(),
            $this->stateMachineTrigger(),
            $this->auditLogIsAppendOnly(),
            $this->privateDisk(),
            $this->blindIndexKey(),
            $this->notificationQueue(),
        ];

        $healthy = collect($checks)->every(fn (array $c): bool => $c['ok']);
        $detaille = $request->user()?->role === UserRole::Admin;

        if ($request->wantsJson()) {
            return response()->json(
                array_filter([
                    'status' => $healthy ? 'ok' : 'degraded',
                    'checks' => $detaille ? $checks : null,
                ], static fn ($valeur): bool => $valeur !== null),
                $healthy ? 200 : 503
            );
        }

        return view('health', [
            'checks' => $detaille ? $checks : [],
            'healthy' => $healthy,
            'detaille' => $detaille,
        ]);
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function database(): array
    {
        try {
            $version = DB::selectOne('SELECT version() AS v')->v ?? '';

            return [
                'label' => __('admin.health.checks.database'),
                'ok' => true,
                'detail' => explode(' (', $version)[0],
            ];
        } catch (Throwable $e) {
            return ['label' => __('admin.health.checks.database'), 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function stateMachineTrigger(): array
    {
        try {
            // On passe par la vue phoenix_guards, et non par
            // information_schema.TRIGGERS : celle-ci est filtree par les
            // droits du lecteur, et le compte applicatif n'a deliberement
            // aucun droit sur les declencheurs. Lu directement, le controle
            // annoncait « ABSENT » sur une base saine. Voir la migration
            // 2026_01_11_000200 et D-052.
            $present = DB::selectOne(
                'SELECT 1 AS ok FROM phoenix_guards WHERE name = ?',
                ['phoenix_guard_request_status_trigger']
            ) !== null;

            $count = DB::table('allowed_transitions')->count();

            return [
                'label' => __('admin.health.checks.state_machine'),
                'ok' => $present && $count > 0,
                'detail' => $present
                    ? __('admin.health.checks.state_machine_ok', ['count' => $count])
                    : __('admin.health.checks.state_machine_missing'),
            ];
        } catch (Throwable $e) {
            return ['label' => __('admin.health.checks.state_machine'), 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function auditLogIsAppendOnly(): array
    {
        try {
            $role = (string) config('database.connections.mysql.username');

            // MySQL n'a pas d'equivalent de has_table_privilege(). On lit les
            // trois niveaux de droits, parce qu'ils s'ADDITIONNENT : un droit
            // de base ou global rendrait le journal alterable meme si le droit
            // de table est correct (D-051).
            $alterants = DB::select(
                "SELECT 'table' AS portee, PRIVILEGE_TYPE AS droit
                   FROM information_schema.TABLE_PRIVILEGES
                  WHERE GRANTEE = CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@',
                                         QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))
                    AND TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
                    AND PRIVILEGE_TYPE IN ('UPDATE', 'DELETE')
                 UNION ALL
                 SELECT 'base', PRIVILEGE_TYPE
                   FROM information_schema.SCHEMA_PRIVILEGES
                  WHERE GRANTEE = CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@',
                                         QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))
                    AND TABLE_SCHEMA = DATABASE()
                    AND PRIVILEGE_TYPE IN ('UPDATE', 'DELETE')
                 UNION ALL
                 SELECT 'globale', PRIVILEGE_TYPE
                   FROM information_schema.USER_PRIVILEGES
                  WHERE GRANTEE = CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@',
                                         QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1)))
                    AND PRIVILEGE_TYPE IN ('UPDATE', 'DELETE')"
            );

            $appendOnly = $alterants === [];

            return [
                'label' => __('admin.health.checks.audit_append_only'),
                'ok' => $appendOnly,
                'detail' => $appendOnly
                    ? __('admin.health.checks.audit_ok', ['role' => $role])
                    : __('admin.health.checks.audit_alert', ['role' => $role]),
            ];
        } catch (Throwable $e) {
            return ['label' => __('admin.health.checks.audit_append_only'), 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function privateDisk(): array
    {
        $root = (string) config('filesystems.disks.private.root');
        $public = public_path();
        $outsideWebroot = ! str_starts_with(realpath($root) ?: $root, realpath($public) ?: $public);
        $writable = is_dir($root) && is_writable($root);

        return [
            'label' => __('admin.health.checks.private_storage'),
            'ok' => $outsideWebroot && $writable,
            'detail' => $outsideWebroot
                ? ($writable
                    ? __('admin.health.checks.private_storage_ok')
                    : __('admin.health.checks.private_storage_readonly'))
                : __('admin.health.checks.private_storage_alert'),
        ];
    }

    /**
     * LA FILE DES NOTIFICATIONS AVANCE-T-ELLE ? (D-089)
     *
     * POURQUOI CETTE VERIFICATION MANQUAIT, ET CE QU'ELLE COUTE. Toutes les
     * notifications du service sont mises en file : c'est D-006 qui l'impose,
     * pour qu'un envoi rate n'annule jamais une decision deja prise. La
     * contrepartie est qu'AUCUNE n'est envoyee si le worker s'arrete — et rien
     * ne le disait. Le systeme repondait 200, les ecrans fonctionnaient, les
     * decisions s'enregistraient, et plus personne n'etait prevenu de rien :
     * ni le demandeur de l'avancement de son dossier, ni le maire qu'un acte
     * attend sa signature. Une panne parfaitement silencieuse.
     *
     * CE QU'ON REGARDE : l'AGE du plus ancien travail en attente, pas leur
     * nombre. Une file de deux cents travaux qui defile est saine ; un seul
     * travail vieux d'une heure veut dire que plus rien ne defile. Compter
     * aurait donne une alerte a chaque pic d'activite, et aucune le jour ou le
     * worker meurt sur une file calme.
     *
     * Les travaux EN ECHEC sont comptes a part : ils ne bloquent pas la file,
     * mais chacun est une notification que personne n'a recue.
     *
     * @return array{label: string, ok: bool, detail: string}
     */
    private function notificationQueue(): array
    {
        try {
            $plusAncien = DB::table('jobs')->min('available_at');
            $echecs = DB::table('failed_jobs')->count();
        } catch (Throwable $e) {
            return [
                'label' => __('admin.health.checks.queue'),
                'ok' => false,
                'detail' => __('admin.health.checks.queue_unreadable'),
            ];
        }

        $minutes = $plusAncien === null ? 0 : (int) round((time() - (int) $plusAncien) / 60);
        $bloquee = $minutes >= self::QUEUE_STALE_MINUTES;

        return [
            'label' => __('admin.health.checks.queue'),
            'ok' => ! $bloquee && $echecs === 0,
            'detail' => match (true) {
                $bloquee => __('admin.health.checks.queue_stalled', ['minutes' => $minutes]),
                $echecs > 0 => __('admin.health.checks.queue_failed', ['count' => $echecs]),
                $plusAncien === null => __('admin.health.checks.queue_empty'),
                default => __('admin.health.checks.queue_moving', ['minutes' => $minutes]),
            },
        ];
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function blindIndexKey(): array
    {
        $set = (string) config('phoenix.blind_index_key') !== '';

        return [
            'label' => __('admin.health.checks.blind_index'),
            'ok' => $set,
            'detail' => $set
                ? __('admin.health.checks.blind_index_ok')
                : __('admin.health.checks.blind_index_missing'),
        ];
    }
}
