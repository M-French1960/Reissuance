<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rappels de HR-Skills Pay.
 *
 * Deux regles, et la seconde est la plus importante :
 *
 *  1. **La signature est verifiee sur le corps BRUT**, en comparaison a temps
 *     constant. Sans elle, n'importe qui connaissant l'URL declarerait un
 *     paiement acquitte — et obtiendrait un acte d'etat civil sans payer.
 *
 *  2. **La charge du rappel n'est jamais crue sur parole.** On y lit une
 *     reference, puis on RE-INTERROGE le prestataire pour connaitre l'etat
 *     reel. Un rappel signe prouve qu'il vient bien d'eux ; il ne prouve pas
 *     que son contenu est a jour. C'est d'ailleurs ce que leur propre
 *     documentation recommande de faire.
 *
 * On repond 200 immediatement : leur documentation donne 10 secondes.
 */
class HrSkillsWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            // Aucune precision au demandeur : un message detaille aiderait a
            // deviner ce qui manque.
            Log::warning('Rappel HR-Skills Pay rejete : signature invalide.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['received' => false], 403);
        }

        $reference = $this->reference($request);

        if ($reference === null) {
            return response()->json(['received' => true]);
        }

        $paiement = Payment::query()
            ->where('provider_reference', $reference)
            ->latest('id')
            ->first();

        if ($paiement === null) {
            // Un rappel pour une transaction qu'on ne connait pas : on
            // l'ignore, sans erreur — un rejeu tardif est possible.
            Log::info('Rappel HR-Skills Pay sans encaissement correspondant.');

            return response()->json(['received' => true]);
        }

        AuditLog::create([
            'action' => 'payment.webhook_received',
            'auditable_type' => 'payment',
            'auditable_id' => $paiement->id,
            'reason' => (string) $request->header('X-Webhook-Event'),
            'ip_address' => $request->ip(),
        ]);

        try {
            // On ne recopie PAS l'etat annonce : on le redemande.
            $this->payments->reconcile($paiement);
        } catch (RuntimeException $e) {
            Log::warning('Rapprochement impossible apres un rappel.', [
                'payment_id' => $paiement->id,
                'exception' => $e::class,
            ]);
        }

        return response()->json(['received' => true]);
    }

    private function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('phoenix.payments.hrskills.webhook_secret');

        // Sans secret configure, AUCUN rappel n'est accepte. Le mode ouvert
        // serait ici une porte d'entree vers des actes non payes.
        if (trim($secret) === '') {
            return false;
        }

        $recue = (string) $request->header('X-Hub-Signature');

        if ($recue === '') {
            return false;
        }

        $attendue = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($attendue, $recue);
    }

    private function reference(Request $request): ?string
    {
        $valeur = $request->json('data.reference');

        return is_string($valeur) && trim($valeur) !== '' ? $valeur : null;
    }
}
