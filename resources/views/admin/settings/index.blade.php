@extends('layouts.app')
@section('title', 'Réglages')

@section('content')
    <h1>Réglages</h1>

    <p class="u-note">
        Ces réglages sont <strong>consultables ici, modifiables au déploiement</strong>.
        Les rendre modifiables depuis un navigateur permettrait de basculer un
        prestataire sur un adaptateur factice — donc de faire délivrer des actes
        sans vérification réelle.
    </p>

    @if ($factices !== [])
        <x-alert variant="danger" title="Ce système ne vérifie rien de réel">
            <p>
                {{ count($factices) }} intégration(s) tournent sur un
                <strong>adaptateur factice</strong> :
            </p>
            <ul class="alert__list">
                @foreach ($factices as $nom)<li>{{ $nom }}</li>@endforeach
            </ul>
            <p>
                Les décisions rendues ici n'ont <strong>aucune valeur</strong>.
                Cette installation est une démonstration, pas un service.
            </p>
        </x-alert>
    @endif

    @foreach ($sections as $titre => $reglages)
        <x-card>
            <h2 class="card__title">{{ $titre }}</h2>

            <div class="table-wrap" tabindex="0" role="group" aria-label="Réglages — {{ $titre }}">
                <table>
                    <caption class="visually-hidden">Réglages — {{ $titre }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Réglage</th>
                            <th scope="col">Valeur</th>
                            <th scope="col">Où le changer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reglages as $reglage)
                            <tr>
                                <td>
                                    {{ $reglage->label }}
                                    @if ($reglage->alerte)
                                        <br><span class="field__hint">{{ $reglage->alerte }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($reglage->sensible)
                                        <span class="badge badge--{{ $reglage->valeur === 'configuré' ? 'success' : 'attention' }}">
                                            {{ $reglage->valeur }}
                                        </span>
                                    @else
                                        {{ $reglage->valeur }}
                                    @endif
                                </td>
                                <td><code>{{ $reglage->variable }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endforeach

    <x-card>
        <h2 class="card__title">Ce qui n'est jamais affiché ici</h2>
        <p>
            Aucune valeur de secret — clés, mots de passe, secrets de signature.
            L'écran indique seulement si un secret <strong>est configuré</strong> :
            sa valeur n'entre pas dans les objets que cette page manipule.
        </p>
    </x-card>
@endsection
