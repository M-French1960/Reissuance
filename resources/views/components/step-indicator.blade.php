@props(['steps', 'current', 'done' => null])

{{-- Indicateur de progression : l'utilisateur doit toujours savoir où il en
     est et ce qui reste (§8.1).

     `done` — LA DIFFERENCE ENTRE « PASSE PAR LA » ET « FAIT ».

     Sans lui, l'indicateur marque « ✓ terminée » toute étape situee AVANT
     l'etape courante. C'est juste dans l'assistant du citoyen, ou l'on ne
     PEUT PAS atteindre l'etape n+1 sans avoir complete la n : la position y
     vaut preuve.

     Ce n'etait pas juste dans la verification de l'officier, ou les cinq
     etapes se parcourent librement. Un officier arrive a l'etape 5 affichait
     « ✓ ✓ ✓ ✓ » — et, dix centimetres plus bas, le recapitulatif de la meme
     page disait « Non renseignée » pour ces quatre etapes, avec un bandeau
     rouge les enumerant comme manquantes. Un lecteur d'ecran, lui, entendait
     « Vérification de la pièce d'identité — terminée ».

     Sur l'ecran ou l'on decide de delivrer un acte d'etat civil, un controle
     annonce comme fait alors qu'il n'a pas eu lieu n'est pas un defaut
     d'affichage. Les vues qui connaissent ce qui a REELLEMENT ete enregistre
     passent donc la liste ; les autres gardent l'ordre pour preuve. --}}
{{-- L'INTITULE ETAIT EN DUR, EN FRANCAIS (D-080). Il n'est pas visible : il
     n'est lu que par les lecteurs d'ecran, ce qui explique qu'il ait survecu
     au passage bilingue. Une personne aveugle naviguant en anglais entendait
     « Progression de la demande » avant une liste anglaise. --}}
<nav class="stepper" aria-label="{{ __('common.progress_nav') }}">
    <ol class="stepper__list">
        @foreach ($steps as $number => $label)
            @php
                $fait = $done === null
                    ? $number < $current
                    : in_array($number, $done, true);
                // « Ou vous etes » l'emporte sur « c'est fait » : l'etape
                // courante reste annoncee comme telle, et le recapitulatif
                // de la page dit son resultat.
                $state = $number === $current ? 'current' : ($fait ? 'done' : 'todo');
            @endphp
            <li class="stepper__item stepper__item--{{ $state }}"
                @if ($number === $current) aria-current="step" @endif>
                <span class="stepper__marker" aria-hidden="true">
                    {{ $state === 'done' ? '✓' : $number }}
                </span>
                <span class="stepper__label">
                    {{ $label }}
                    <span class="visually-hidden">
                        @if ($state === 'done'){{ __('common.step_done') }}
                        @elseif ($state === 'current'){{ __('common.step_current') }}
                        @else{{ __('common.step_upcoming') }}
                        @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ol>
</nav>
