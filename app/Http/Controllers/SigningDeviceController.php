<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\SigningDevice;
use App\Services\Webauthn\SigningDeviceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webauthn\PublicKeyCredentialOptions;

/**
 * Appareils de signature : enrolement, revocation, et le defi de signature.
 *
 * Reserve aux roles qui signent ou instruisent — un citoyen n'enrole pas
 * d'appareil, il n'a rien a signer.
 */
class SigningDeviceController extends Controller
{
    public function __construct(private readonly SigningDeviceService $devices) {}

    /* ------------------------------------------------------------------ */
    /* Enrôlement */
    /* ------------------------------------------------------------------ */

    /** Les options que le navigateur remet a l'appareil. */
    public function creationOptions(Request $request): JsonResponse
    {
        try {
            $options = $this->devices->creationOptions($request->user(), $request->getHost());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->devices->rememberChallenge('enrolement', $options);

        return $this->optionsResponse($options);
    }

    public function store(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'credential' => ['required', 'string', 'max:20000'],
        ], [
            'label.required' => 'Donnez un nom à cet appareil : vous devrez pouvoir le reconnaître pour le révoquer.',
        ]);

        try {
            $options = $this->devices->recallCreationOptions('enrolement');

            $appareil = $this->devices->register(
                $request->user(),
                $valide['credential'],
                $options,
                $valide['label'],
                $request->getHost(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['label' => $e->getMessage()])->withInput();
        }

        $this->audit($request, 'signing_device.enrolled', $appareil->id);

        return back()->with('status', "L'appareil « {$appareil->label} » peut désormais signer.");
    }

    /**
     * Revoque un appareil.
     *
     * Chacun ne revoque que les siens : un officiel ne doit pas pouvoir
     * priver un collegue de son moyen de signature.
     */
    public function destroy(Request $request, SigningDevice $device): RedirectResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        $nom = $device->label;
        $device->delete();

        $this->audit($request, 'signing_device.revoked', $device->id);

        return back()->with('status', "L'appareil « {$nom} » ne peut plus signer.");
    }

    /* ------------------------------------------------------------------ */
    /* Le défi de signature */
    /* ------------------------------------------------------------------ */

    /**
     * Le defi a presenter a l'appareil pour signer CE dossier.
     *
     * LIE AU DOSSIER, et c'est le point : sans ce lien, une signature obtenue
     * pour un dossier pourrait etre presentee pour un autre.
     */
    public function challenge(Request $request, ReissuanceRequest $reissuanceRequest): JsonResponse
    {
        $this->authorize('sign', $reissuanceRequest);

        try {
            $options = $this->devices->requestOptions($request->user(), $request->getHost());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->devices->rememberChallenge('signature', $options, [
            'request_id' => $reissuanceRequest->id,
        ]);

        return $this->optionsResponse($options);
    }

    /** Les options, serialisees par la bibliotheque et non par json_encode. */
    private function optionsResponse(PublicKeyCredentialOptions $options): JsonResponse
    {
        return JsonResponse::fromJsonString(
            $this->devices->optionsToJson($options),
            200,
            ['Cache-Control' => 'no-store, private, max-age=0'],
        );
    }

    private function audit(Request $request, string $action, int $id): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role->value,
            'action' => $action,
            'auditable_type' => 'signing_device',
            'auditable_id' => $id,
            'ip_address' => $request->ip(),
        ]);
    }
}
