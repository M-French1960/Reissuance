@extends('layouts.app')
@section('title', 'Galerie de composants')

@section('content')
    <x-alert variant="attention" title="Page de développement">
        Cette galerie n'est accessible que hors production. Elle présente chaque
        composant dans tous ses états et sert de référence visuelle et de test.
    </x-alert>

    <h1>Galerie de composants</h1>

    <x-card title="Jetons de couleur">
        <p>Chaque valeur vient de <code>tokens.css</code>. Aucune vue ne contient
        de couleur en dur. Les rapports de contraste indiqués sont mesurés.</p>
        <div class="grid grid--3">
            @foreach ([
                ['neutral', '8,32:1'], ['waiting', '7,65:1'], ['progress', '7,78:1'],
                ['attention', '6,71:1'], ['success', '8,08:1'], ['danger', '6,91:1'],
            ] as [$tone, $ratio])
                <div>
                    <span class="badge badge--{{ $tone }}">{{ $tone }}</span>
                    <p style="font-size:var(--text-sm);color:var(--color-ink-500);margin:var(--space-1) 0 0">
                        {{ $ratio }} — conforme AA
                    </p>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card title="Badges de statut">
        <p>Les sept états d'une demande, avec le libellé réellement affiché.</p>
        <div style="display:flex;flex-wrap:wrap;gap:var(--space-2)">
            @foreach (\App\Enums\RequestStatus::cases() as $status)
                <x-status-badge :status="$status" />
            @endforeach
        </div>
    </x-card>

    <x-card title="Boutons">
        <div style="display:flex;flex-wrap:wrap;gap:var(--space-3);align-items:center">
            <x-button variant="primary" type="button">Action principale</x-button>
            <x-button variant="secondary" type="button">Action secondaire</x-button>
            <x-button variant="danger" type="button">Action destructrice</x-button>
            <x-button variant="primary" type="button" disabled>Désactivé</x-button>
        </div>
        <p style="margin-top:var(--space-3);font-size:var(--text-sm);color:var(--color-ink-500)">
            Hauteur minimale 44 px (cible tactile WCAG 2.5.5). Naviguez au clavier
            pour vérifier le contour de focus, absent de tout le prototype.
        </p>
    </x-card>

    <x-card title="Champs de formulaire">
        <x-field name="demo_name" label="Nom complet" hint="Tel qu'il figure sur votre pièce d'identité." required />
        <x-field name="demo_email" label="Adresse électronique" type="email" value="citoyen@example.test" />
        <x-field name="demo_bad" label="Année d'enregistrement" value="19"
                 error="L'année doit comporter quatre chiffres, par exemple 1990." />
    </x-card>

    <x-card title="Alertes">
        <x-alert variant="success" title="Demande envoyée">Votre demande a bien été transmise au centre d'état civil.</x-alert>
        <x-alert variant="attention" title="Service externe indisponible"
                 action="#" action-label="Réessayer la vérification">
            La base de la police n'a pas répondu. Vous pouvez poursuivre les autres
            étapes et relancer cette vérification plus tard.
        </x-alert>
        <x-alert variant="danger" title="Pièce non lisible"
                 action="#" action-label="Reprendre la photo">
            La photographie de la pièce d'identité est trop floue pour être vérifiée.
        </x-alert>
    </x-card>

    <x-card title="État vide">
        <x-empty-state title="Aucune demande en cours" action="#" action-label="Faire une demande">
            Vous n'avez pas encore déposé de demande de réédition.
        </x-empty-state>
    </x-card>

    <x-card title="Squelettes de chargement">
        <p style="font-size:var(--text-sm);color:var(--color-ink-500)">Préférés à une page blanche (§8.5).</p>
        <div class="skeleton" style="width:60%;height:1.2rem;margin-bottom:var(--space-2)"></div>
        <div class="skeleton" style="width:90%;height:1.2rem;margin-bottom:var(--space-2)"></div>
        <div class="skeleton" style="width:75%;height:1.2rem"></div>
    </x-card>

    <x-card title="Frise chronologique">
        <ol class="timeline">
            <li class="timeline__item timeline__item--done">
                <span class="timeline__marker" aria-hidden="true">&check;</span>
                <div><strong>Demande envoyée</strong><br>
                <span style="color:var(--color-ink-500)">Transmise au centre d'état civil de Yaoundé I</span></div>
            </li>
            <li class="timeline__item">
                <span class="timeline__marker" aria-hidden="true">2</span>
                <div><strong>Vérification par l'officier</strong><br>
                <span style="color:var(--color-ink-500)">En cours</span></div>
            </li>
            <li class="timeline__item">
                <span class="timeline__marker" aria-hidden="true">3</span>
                <div><strong>Signature du maire</strong><br>
                <span style="color:var(--color-ink-500)">À venir</span></div>
            </li>
        </ol>
    </x-card>

    <x-card title="Tableau">
        <p style="font-size:var(--text-sm);color:var(--color-ink-500)">
            Défile dans son propre conteneur : le corps de page ne défile jamais horizontalement.
        </p>
        <div class="table-wrap">
            <table>
                <caption class="visually-hidden">Exemple de file de demandes</caption>
                <thead>
                    <tr><th scope="col">Référence</th><th scope="col">Centre</th><th scope="col">Statut</th><th scope="col">Déposée le</th></tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr>
                            <td>{{ $request->reference }}</td>
                            <td>{{ $request->center?->name ?? '—' }}</td>
                            <td><x-status-badge :status="$request->status" /></td>
                            <td>{{ $request->submitted_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
