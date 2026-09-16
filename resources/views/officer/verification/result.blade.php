{{-- Partial: the result of one step. Included with @include, not a component. --}}
@if ($etape?->result)
    <p>
        <span class="badge badge--{{ $etape->result->tone() }}">{{ $etape->result->label() }}</span>
        <span class="u-note">{{ __('officer.result_recorded', [
            'date' => $etape->completed_at?->translatedFormat('d/m/Y H:i'),
            'name' => $etape->officer?->name,
        ]) }}</span>
    </p>
@else
    <p><span class="badge badge--neutral">{{ __('verification.not_recorded') }}</span></p>
@endif
