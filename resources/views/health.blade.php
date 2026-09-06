@extends('layouts.app')
@section('title', 'État du service')

@section('content')
    <h1>État du service</h1>
    <x-card>
        <div class="table-wrap">
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
@endsection
