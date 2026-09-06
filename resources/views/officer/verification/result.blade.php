{{-- Partiel : resultat d'une etape. Inclus par @include, pas un composant. --}}
@if ($etape?->result)
    <p>
        <span class="badge badge--{{ $etape->result->tone() }}">{{ $etape->result->label() }}</span>
        <span class="u-note">enregistré le {{ $etape->completed_at?->translatedFormat('d/m/Y à H:i') }}
        par {{ $etape->officer?->name }}</span>
    </p>
@else
    <p><span class="badge badge--neutral">Non renseignée</span></p>
@endif
