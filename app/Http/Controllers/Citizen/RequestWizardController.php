<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Services\RequestTransitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Assistant de demande en quatre etapes.
 *
 * Sans Livewire (D-010), chaque etape est un aller-retour serveur :
 * POST -> validation -> enregistrement du brouillon -> redirection -> GET.
 * Une coupure entre deux etapes ne perd donc jamais ce qui a deja ete saisi,
 * ce qui vaut mieux qu'une sauvegarde continue sur reseau instable (8.1).
 *
 * Le prototype laissait atteindre le paiement sans nom, sans e-mail et sans
 * photo, en trois clics (docs/AUDIT_FRONTEND.md 5.3). Ici, chaque etape est
 * validee cote serveur et aucune n'est atteignable sans que la precedente ne
 * soit complete.
 */
class RequestWizardController extends Controller
{
    public const STEPS = [
        1 => 'Vos informations',
        2 => "Détails de l'acte",
        3 => 'Centre et pièces',
        4 => 'Vérification et envoi',
    ];

    /** Cree ou reprend le brouillon en cours, et renvoie a la bonne etape. */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile?->completed_at === null) {
            return redirect()->route('citizen.profile.edit')->with(
                'status',
                'Complétez votre profil avant de déposer une demande.'
            );
        }

        $draft = $this->currentDraft($request);

        if ($draft === null) {
            $draft = ReissuanceRequest::create([
                'reference' => ReissuanceRequest::generateReference(),
                'user_id' => $user->id,
                'reason' => 'lost',
            ]);
        }

        return redirect()->route('citizen.requests.step', [
            'reissuanceRequest' => $draft,
            'step' => min($draft->last_completed_step + 1, 4),
        ]);
    }

    public function show(Request $request, ReissuanceRequest $reissuanceRequest, int $step): View|RedirectResponse
    {
        $this->authorize('update', $reissuanceRequest);

        if ($step < 1 || $step > 4) {
            abort(404);
        }

        // On ne saute pas une etape : la suivante n'est atteignable que si la
        // precedente est complete.
        if ($step > $reissuanceRequest->last_completed_step + 1) {
            return redirect()->route('citizen.requests.step', [
                'reissuanceRequest' => $reissuanceRequest,
                'step' => $reissuanceRequest->last_completed_step + 1,
            ])->with('status', 'Terminez cette étape avant de passer à la suivante.');
        }

        return view("citizen.wizard.step-{$step}", [
            'draft' => $reissuanceRequest->load('attachments', 'center'),
            'step' => $step,
            'steps' => self::STEPS,
            'centers' => $step === 3
                ? CivilStatusCenter::with('commune:id,name')->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'profile' => $request->user()->profile,
        ]);
    }

    public function save(Request $request, ReissuanceRequest $reissuanceRequest, int $step): RedirectResponse
    {
        $this->authorize('update', $reissuanceRequest);

        $validated = $request->validate($this->rulesFor($step), $this->messagesFor($step));

        if ($step === 3) {
            $center = CivilStatusCenter::findOrFail($validated['civil_status_center_id']);
            // La commune est deduite du centre, jamais prise dans la requete :
            // elle determine quel maire est competent.
            $validated['commune_id'] = $center->commune_id;
        }

        $reissuanceRequest->fill($validated);
        $reissuanceRequest->last_completed_step = max($reissuanceRequest->last_completed_step, $step);
        $reissuanceRequest->save();

        if ($step === 4) {
            return $this->submit($request, $reissuanceRequest);
        }

        return redirect()->route('citizen.requests.step', [
            'reissuanceRequest' => $reissuanceRequest,
            'step' => $step + 1,
        ]);
    }

    private function submit(Request $request, ReissuanceRequest $draft): RedirectResponse
    {
        $this->authorize('submit', $draft);

        // Derniere barriere avant l'envoi : les pieces sont-elles la ?
        // Le prototype permettait d'aller jusqu'au bout sans aucune photo.
        $kinds = $draft->attachments()->pluck('kind')->all();

        foreach (['selfie' => 'un selfie', 'id_document' => "une photo de votre pièce d'identité"] as $kind => $label) {
            if (! in_array($kind, $kinds, true)) {
                return redirect()
                    ->route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3])
                    ->withErrors(['attachments' => "Votre demande ne peut pas être envoyée sans {$label}."]);
            }
        }

        $draft->forceFill([
            'submitted_at' => now(),
            'consent_given_at' => now(),
        ])->save();

        app(RequestTransitionService::class)->transition(
            $draft,
            RequestStatus::Pending,
            $request->user(),
            null,
            $request->ip(),
        );

        return redirect()
            ->route('citizen.requests.show', $draft)
            ->with('status', "Votre demande {$draft->reference} a été transmise au centre d'état civil.");
    }

    /** @return array<string, mixed> */
    private function rulesFor(int $step): array
    {
        return match ($step) {
            1 => [
                'reason' => ['required', Rule::in(['lost', 'damaged'])],
                'copies_requested' => ['required', 'integer', 'min:1', 'max:10'],
            ],
            2 => [
                'full_name_at_birth' => ['required', 'string', 'max:200'],
                'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
                'place_of_birth' => ['required', 'string', 'max:200'],
                'registration_year' => ['required', 'integer', 'min:1900', 'max:'.date('Y')],
                'original_certificate_number' => ['nullable', 'string', 'max:100'],
                'father_name' => ['required', 'string', 'max:200'],
                'father_nationality' => ['required', 'string', 'max:100'],
                'mother_name' => ['required', 'string', 'max:200'],
                'mother_nationality' => ['required', 'string', 'max:100'],
                'parents_address' => ['required', 'string', 'max:255'],
            ],
            3 => [
                'civil_status_center_id' => ['required', 'integer', 'exists:civil_status_centers,id'],
            ],
            4 => [],
            default => abort(404),
        };
    }

    /** @return array<string, string> */
    private function messagesFor(int $step): array
    {
        return [
            'registration_year.min' => "L'année d'enregistrement doit comporter quatre chiffres, par exemple 1990.",
            'registration_year.max' => "L'année d'enregistrement ne peut pas être dans le futur.",
            'date_of_birth.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'civil_status_center_id.required' => "Choisissez le centre d'état civil où l'acte a été enregistré.",
        ];
    }

    private function currentDraft(Request $request): ?ReissuanceRequest
    {
        return ReissuanceRequest::where('status', RequestStatus::Draft->value)
            ->latest('id')
            ->first();
    }
}
