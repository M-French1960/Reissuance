<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        if ($request->wantsJson()) {
            return response()->json(
                ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
                $healthy ? 200 : 503
            );
        }

        return view('health', ['checks' => $checks, 'healthy' => $healthy]);
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function database(): array
    {
        try {
            $version = DB::selectOne('SELECT version() AS v')->v ?? '';

            return [
                'label' => 'Connexion MySQL',
                'ok' => true,
                'detail' => explode(' (', $version)[0],
            ];
        } catch (Throwable $e) {
            return ['label' => 'Connexion MySQL', 'ok' => false, 'detail' => $e->getMessage()];
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
                'label' => 'Déclencheur de machine à états',
                'ok' => $present && $count > 0,
                'detail' => $present
                    ? "Actif, {$count} transitions autorisées"
                    : 'ABSENT — les transitions interdites ne seraient plus bloquées',
            ];
        } catch (Throwable $e) {
            return ['label' => 'Déclencheur de machine à états', 'ok' => false, 'detail' => $e->getMessage()];
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
                'label' => "Journal d'audit en ajout seul",
                'ok' => $appendOnly,
                'detail' => $appendOnly
                    ? "Le rôle {$role} ne peut ni modifier ni supprimer une entrée"
                    : "ALERTE : le rôle {$role} peut altérer le journal d'audit",
            ];
        } catch (Throwable $e) {
            return ['label' => "Journal d'audit en ajout seul", 'ok' => false, 'detail' => $e->getMessage()];
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
            'label' => 'Stockage privé des pièces',
            'ok' => $outsideWebroot && $writable,
            'detail' => $outsideWebroot
                ? ($writable ? 'Hors de la racine web, accessible en écriture' : 'Hors racine web mais NON accessible en écriture')
                : 'ALERTE : le stockage est dans la racine web, donc exposé',
        ];
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function blindIndexKey(): array
    {
        $set = (string) config('phoenix.blind_index_key') !== '';

        return [
            'label' => "Clé de l'index aveugle",
            'ok' => $set,
            'detail' => $set
                ? 'Présente — la recherche par numéro de pièce est opérante'
                : 'Absente — exécuter php artisan phoenix:generate-index-key',
        ];
    }
}
