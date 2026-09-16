<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Services\PaymentGate;
use App\Services\RequestTransitionService;
use App\Support\ActLanguage;
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
    /**
     * The four steps, translated on read.
     *
     * A constant cannot hold a translated string: it is resolved once, at
     * compile time, before any language is chosen. The names of the steps
     * therefore live in the language files and are fetched per request.
     */
    public const STEP_COUNT = 4;

    /** @return array<int, string> */
    public static function steps(): array
    {
        return [
            1 => __('wizard.steps.1'),
            2 => __('wizard.steps.2'),
            3 => __('wizard.steps.3'),
            4 => __('wizard.steps.4'),
        ];
    }

    /** Cree ou reprend le brouillon en cours, et renvoie a la bonne etape. */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile?->completed_at === null) {
            return redirect()->route('citizen.profile.edit')->with(
                'status',
                __('flash.citizen.complete_profile_first')
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
            ])
                // Un renvoi en arriere n'est pas un succes : le vert felicitait
                // le citoyen de s'etre fait refuser l'acces a l'etape suivante.
                ->with('status', __('wizard.finish_step_first'))
                ->with('statusTone', 'attention');
        }

        return view("citizen.wizard.step-{$step}", [
            'draft' => $reissuanceRequest->load('attachments', 'center'),
            'step' => $step,
            'steps' => self::steps(),
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

        foreach (['selfie', 'id_document'] as $kind) {
            if (! in_array($kind, $kinds, true)) {
                return redirect()
                    ->route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3])
                    ->withErrors(['attachments' => __('flash.citizen.missing_attachment', [
                        'label' => $kind === 'selfie'
                            ? __('flash.citizen.selfie_label')
                            : __('flash.citizen.id_label'),
                    ])]);
            }
        }

        // Barriere de paiement, si le service l'a placee ici (D-041). Quand le
        // placement est « none » ou « before_signature », allows() rend true
        // et ce controleur n'a rien a savoir du paiement.
        $gate = app(PaymentGate::class);

        if (! $gate->allows($draft, PaymentGate::BEFORE_SUBMISSION)) {
            return redirect()
                ->route('citizen.requests.payment', $draft)
                // L'envoi N'A PAS eu lieu : il reste une condition a remplir.
                ->with('status', __('wizard.settle_fee_first'))
                ->with('statusTone', 'attention');
        }

        $draft->forceFill([
            'submitted_at' => now(),
            'consent_given_at' => now(),
            /*
             * THE LANGUAGE OF THE CERTIFICATE, FROZEN HERE (D-076).
             *
             * Not read at download time: the signature binds a content
             * fingerprint to the exact text, so a certificate that came back
             * in French for one reader and English for another would no longer
             * match what the mayor signed.
             */
            'act_language' => ActLanguage::forNewRequest(),
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
            ->with('status', __('wizard.submitted', ['reference' => $draft->reference]));
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
            'registration_year.min' => __('flash.citizen.registration_year_min'),
            'registration_year.max' => __('flash.citizen.registration_year_max'),
            'date_of_birth.before' => __('flash.citizen.birth_date_before'),
            'civil_status_center_id.required' => __('flash.citizen.centre_required'),
        ];
    }

    private function currentDraft(Request $request): ?ReissuanceRequest
    {
        return ReissuanceRequest::where('status', RequestStatus::Draft->value)
            ->latest('id')
            ->first();
    }
}
