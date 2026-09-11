@extends('layouts.app')
@section('title', 'État du service')

@section('content')
    <h1>État du service</h1>

    <x-card>
        <p>
            <span class="badge badge--{{ $healthy ? 'success' : 'danger' }}">
                {{ $healthy ? 'Service opérationnel' : 'Service dégradé' }}
            </span>
        </p>

        {{--
            Le detail n'est pas public : il renseignait un visiteur anonyme sur
            la version du serveur de base, le compte applicatif et l'etat des
            droits du journal d'audit (D-058).
        --}}
        @unless ($detaille)
            <p class="u-note">
                Le détail des vérifications est réservé à l'administration.
            </p>
        @endunless
    </x-card>

    @if ($detaille)
        <x-card>
            <div class="table-wrap" tabindex="0" role="group" aria-label="Vérifications de santé">
                <table>
                    <caption class="visually-hidden">Vérifications de santé</caption>
                    <thead><tr><th scope="col">Vérification</th><th scope="col">État</th><th scope="col">Détail</th></tr></thead>
                    <tbody>
                        @foreach ($checks as $check)
                            <tr>
                                <td>{{ $check['label'] }}</td>
                                <td>
                                    <span class="badge badge--{{ $check['ok'] ? 'success' : 'danger' }}">
                                        {{ $check['ok'] ? 'OK' : 'En échec' }}
                                    </span>
                                </td>
                                <td>{{ $check['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
