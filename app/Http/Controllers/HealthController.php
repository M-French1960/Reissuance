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
    public function __invoke(Request $request): View|JsonResponse
    {
        $checks = [
            $this->database(),
            $this->stateMachineTrigger(),
            $this->auditLogIsAppendOnly(),
            $this->privateDisk(),
            $this->blindIndexKey(),
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
