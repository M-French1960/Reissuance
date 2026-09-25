@extends('layouts.app')
@section('title', __('admin.settings.title'))

@section('content')
    <h1>{{ __('admin.settings.title') }}</h1>

    <p class="u-note">
        {!! __('admin.settings.intro', ['strong' => '<strong>'.e(__('admin.settings.intro_strong')).'</strong>']) !!}
    </p>

    @if ($factices !== [])
        <x-alert variant="danger" :title="__('admin.settings.fake_title')">
            <p>{!! __('admin.settings.fake_body', [
                'count' => count($factices),
                'strong' => '<strong>'.e(__('admin.settings.fake_strong')).'</strong>',
            ]) !!}</p>
            <ul class="alert__list">
                @foreach ($factices as $nom)<li>{{ $nom }}</li>@endforeach
            </ul>
            <p>{!! __('admin.settings.fake_note', ['strong' => '<strong>'.e(__('admin.settings.fake_note_strong')).'</strong>']) !!}</p>
        </x-alert>
    @endif

    @foreach ($sections as $titre => $reglages)
        <x-card>
            <h2 class="card__title">{{ $titre }}</h2>

            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.settings.title') }}: {{ $titre }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.settings.title') }}: {{ $titre }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.settings.setting') }}</th>
                            <th scope="col">{{ __('admin.settings.value') }}</th>
                            <th scope="col">{{ __('admin.settings.where_to_change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reglages as $reglage)
                            <tr>
                                <td data-label="{{ __('admin.settings.setting') }}">
                                    {{ $reglage->label }}
                                    @if ($reglage->alerte)
                                        <br><span class="field__hint">{{ $reglage->alerte }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('admin.settings.value') }}">
                                    @if ($reglage->sensible)
                                        <span class="badge badge--{{ $reglage->configure ? 'success' : 'attention' }}">
                                            {{ $reglage->valeur }}
                                        </span>
                                    @else
                                        {{ $reglage->valeur }}
                                    @endif
                                </td>
                                <td data-label="{{ __('admin.settings.where_to_change') }}"><code>{{ $reglage->variable }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endforeach

    <x-card>
        <h2 class="card__title">{{ __('admin.settings.never_shown_title') }}</h2>
        <p>{!! __('admin.settings.never_shown_body', ['strong' => '<strong>'.e(__('admin.settings.never_shown_strong')).'</strong>']) !!}</p>
    </x-card>
@endsection
