@props(['status'])

{{--
    Always the translated label, never the technical value. The prototype
    printed a raw "escalated" (docs/AUDIT_FRONTEND.md 8.3). Colour carries no
    information on its own here; the text is enough.
--}}
<span {{ $attributes->merge(['class' => 'badge badge--'.$status->tone()]) }}>
    {{ $status->label() }}
</span>
