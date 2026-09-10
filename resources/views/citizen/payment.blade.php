@extends('layouts.app')
@section('title', 'Règlement des frais')

@section('content')
    <h1>Règlement des frais</h1>
    <p class="u-note">Demande {{ $demande->reference }}</p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert variant="danger" title="Le règlement n'a pas abouti">
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </x-alert>
    @endif

    <x-card title="Montant à régler">
        <dl class="review">
            <div class="review__row">
                <dt>Montant</dt>
                <dd><strong>{{ $montant?->format() ?? '—' }}</strong></dd>
            </div>
            @if ($baseLegale)
                <div class="review__row">
                    <dt>Base réglementaire</dt>
                    <dd>{{ $baseLegale }}</dd>
                </div>
            @endif
        </dl>

        @unless ($baseLegale)
            {{-- On n'invente pas un fondement juridique (§10 du brief). Tant
                 qu'il n'est pas configuré, on dit qu'on ne l'affiche pas. --}}
            <p class="u-note">
                La référence du texte fixant ce tarif n'est pas encore
                renseignée dans le service.
            </p>
        @endunless

        <p>
            @if ($avantEnvoi)
                Votre demande sera transmise au centre d'état civil dès que le
                règlement sera confirmé.
            @else
                Votre acte sera signé par le maire dès que le règlement sera
                confirmé.
            @endif
        </p>
    </x-card>

    @if ($paiement && $paiement->isPaid())
        <x-card title="Règlement confirmé">
            <p>
                <span class="badge badge--{{ $paiement->status->tone() }}">{{ $paiement->status->label() }}</span>
            </p>
            <dl class="review">
                <div class="review__row"><dt>Montant</dt><dd>{{ $paiement->money()->format() }}</dd></div>
                <div class="review__row"><dt>Date</dt><dd>{{ $paiement->settled_at?->translatedFormat('d/m/Y à H:i') }}</dd></div>
                <div class="review__row"><dt>Référence</dt><dd>{{ $paiement->provider_reference ?? '—' }}</dd></div>
            </dl>
            <x-button :href="route('citizen.requests.payment.receipt', $demande)" variant="secondary">
                Télécharger le reçu
            </x-button>
        </x-card>
    @elseif ($paiement)
        <x-card title="Règlement en cours">
            <p>
                <span class="badge badge--{{ $paiement->status->tone() }}">{{ $paiement->status->label() }}</span>
            </p>

            @if ($paiement->status === \App\Enums\PaymentStatus::Authorised)
                <x-alert variant="attention" title="Les fonds ne sont pas encore acquis">
                    L'opérateur a bien pris votre ordre, mais le règlement n'est
                    pas terminé. Validez-le sur votre téléphone si ce n'est pas
                    fait, puis actualisez ci-dessous.
                </x-alert>
            @endif

            <form method="POST" action="{{ route('citizen.requests.payment.reconcile', $demande) }}">
                @csrf
                <x-button type="submit" variant="secondary">Actualiser l'état du règlement</x-button>
            </form>
        </x-card>
    @endif

    @if (! $paiement || ! $paiement->isPaid())
        <x-card title="Payer">
            <form method="POST" action="{{ route('citizen.requests.payment.store', $demande) }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="payer_reference">
                        Numéro de règlement <span aria-hidden="true">*</span>
                        <span class="field__hint">
                            Le numéro de téléphone depuis lequel vous payez, par
                            exemple +237 6 XX XX XX XX.
                        </span>
                    </label>
                    <input class="field__control" id="payer_reference" name="payer_reference"
                           type="tel" inputmode="tel" autocomplete="tel" required
                           value="{{ old('payer_reference', $paiement?->payer_reference) }}"
                           @if ($errors->has('payer_reference')) aria-invalid="true" @endif>
                    @if ($errors->has('payer_reference'))
                        <p class="field__error">{{ $errors->first('payer_reference') }}</p>
                    @endif
                </div>

                <x-button type="submit" variant="primary">
                    Régler {{ $montant?->format() }}
                </x-button>
            </form>
        </x-card>
    @endif

    <p class="u-note">
        <a href="{{ route('citizen.requests.show', $demande) }}">Revenir à ma demande</a>
    </p>
@endsection
