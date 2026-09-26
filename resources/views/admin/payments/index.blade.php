@extends('layouts.app')
@section('title', __('admin.payments.title'))

@section('content')
    <h1>{{ __('admin.payments.title') }}</h1>

    {{--
        CE QUE CET ECRAN NE MONTRE PAS, dit a celui qui le lit (D-095). Un
        administrateur qui cherche un numero de telephone ou une reference de
        dossier doit comprendre qu'il ne les trouvera pas ici parce que c'est
        VOULU, et non parce que l'ecran est inachevé.
    --}}
    <x-alert variant="attention" :title="__('admin.payments.scope_title')">
        {{ __('admin.payments.scope_body') }}
    </x-alert>

    <x-card :title="__('admin.payments.totals_title')">
        @if (empty($totaux))
            <p class="u-flush">{{ __('admin.payments.totals_empty') }}</p>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.payments.totals_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.payments.totals_aria') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.payments.status') }}</th>
                            <th scope="col">{{ __('admin.payments.count') }}</th>
                            <th scope="col">{{ __('admin.payments.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($totaux as $ligne)
                            <tr>
                                <td data-label="{{ __('admin.payments.status') }}">{{ $ligne['statut']->label() }}</td>
                                <td data-label="{{ __('admin.payments.count') }}">{{ $ligne['nombre'] }}</td>
                                {{-- Plusieurs devises melangees : aucun total n'est juste,
                                     donc on n'en affiche aucun plutot qu'un nombre faux. --}}
                                <td data-label="{{ __('admin.payments.amount') }}">
                                    {{ $ligne['montant']?->format() ?? __('admin.payments.mixed_currencies') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card>
        <form method="GET" action="{{ route('admin.payments.index') }}" class="toolbar">
            <x-field name="reference" :label="__('admin.payments.reference')" :value="$filtres['reference']"
                     :hint="__('admin.payments.reference_hint')" />
            <div class="field">
                <label class="field__label" for="operateur">{{ __('admin.payments.operator') }}</label>
                <select class="field__control" id="operateur" name="operateur">
                    <option value="">{{ __('admin.payments.all') }}</option>
                    @foreach ($operateurs as $operateur)
                        <option value="{{ $operateur->value }}" @selected($filtres['operateur'] === $operateur->value)>{{ __('enums.payment_operator.'.$operateur->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="statut">{{ __('admin.payments.status') }}</label>
                <select class="field__control" id="statut" name="statut">
                    <option value="">{{ __('admin.payments.all') }}</option>
                    @foreach ($statuts as $statut)
                        <option value="{{ $statut->value }}" @selected($filtres['statut'] === $statut->value)>{{ $statut->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('common.filter') }}</x-button>
            <x-button href="{{ route('admin.payments.export', request()->query()) }}" variant="secondary">{{ __('admin.payments.export') }}</x-button>
        </form>
        <p class="u-note">{{ __('admin.payments.export_note') }}</p>
    </x-card>

    <x-card>
        @if ($paiements->isEmpty())
            <x-empty-state :title="__('admin.payments.empty_title')">{{ __('admin.payments.empty_body') }}</x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.payments.entries_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.payments.entries_aria') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.payments.reference') }}</th>
                            <th scope="col">{{ __('admin.payments.operator') }}</th>
                            <th scope="col">{{ __('admin.payments.amount') }}</th>
                            <th scope="col">{{ __('admin.payments.status') }}</th>
                            <th scope="col">{{ __('admin.payments.created') }}</th>
                            <th scope="col">{{ __('admin.payments.settled') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paiements as $paiement)
                            <tr>
                                <td data-label="{{ __('admin.payments.reference') }}">
                                    {{ $paiement->provider_reference ?? __('admin.payments.no_reference') }}
                                </td>
                                <td data-label="{{ __('admin.payments.operator') }}">
                                    {{ $paiement->operator ? __('enums.payment_operator.'.$paiement->operator->value) : __('common.none') }}
                                </td>
                                <td data-label="{{ __('admin.payments.amount') }}">{{ $paiement->money()->format() }}</td>
                                <td data-label="{{ __('admin.payments.status') }}">
                                    <x-status-badge :status="$paiement->status" />
                                </td>
                                <td data-label="{{ __('admin.payments.created') }}">{{ $paiement->created_at?->format('d/m/Y H:i') }}</td>
                                <td data-label="{{ __('admin.payments.settled') }}">{{ $paiement->settled_at?->format('d/m/Y H:i') ?? __('admin.payments.not_yet') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $paiements->links('pagination') }}
        @endif
    </x-card>

    {{--
        LES REMBOURSEMENTS. La maquette portait un bouton et un
        « TODO : POST /api/admin/refunds ». Le prestataire est factice : ce
        bouton annoncerait qu'un remboursement est parti alors que rien ne
        serait parti. On dit donc ce qui manque, au lieu de le simuler.
    --}}
    <x-card :title="__('admin.payments.refunds_title')">
        <p>{{ __('admin.payments.refunds_body') }}</p>
        <ul>
            <li>{{ __('admin.payments.refunds_missing_rule') }}</li>
            <li>{{ __('admin.payments.refunds_missing_provider') }}</li>
        </ul>
        <p class="u-note u-flush">{{ __('admin.payments.refunds_listed') }}</p>
    </x-card>
@endsection
