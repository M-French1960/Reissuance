@props(['steps', 'current'])

{{-- Indicateur de progression : l'utilisateur doit toujours savoir où il en
     est et ce qui reste (§8.1). --}}
<nav class="stepper" aria-label="Progression de la demande">
    <ol class="stepper__list">
        @foreach ($steps as $number => $label)
            @php
                $state = $number < $current ? 'done' : ($number === $current ? 'current' : 'todo');
            @endphp
            <li class="stepper__item stepper__item--{{ $state }}"
                @if ($state === 'current') aria-current="step" @endif>
                <span class="stepper__marker" aria-hidden="true">
                    {{ $state === 'done' ? '✓' : $number }}
                </span>
                <span class="stepper__label">
                    {{ $label }}
                    <span class="visually-hidden">
                        @if ($state === 'done') — terminée
                        @elseif ($state === 'current') — étape en cours
                        @else — à venir
                        @endif
                    </span>
                </span>
            </li>
        @endforeach
    </ol>
</nav>
