{{--
    Pagination du projet.

    ELLE PARLAIT FRANCAIS DANS LES DEUX LANGUES (D-079). « Précédent »,
    « Suivant » et « Page 1 sur 3 » etaient ecrits en dur. Cette vue est
    utilisee par toutes les listes du service — mes demandes, les comptes, le
    journal d'audit — donc un lecteur anglophone lisait du francais sur chacune
    d'elles. Trouve en relisant les vues apres le passage bilingue, pas par un
    test : aucune assertion ne regardait ce fragment.
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('common.pagination') }}">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li><span aria-disabled="true">{{ __('common.previous') }}</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('common.previous') }}</a></li>
            @endif

            <li>
                <span aria-current="page">
                    {{ __('common.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
                </span>
            </li>

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('common.next') }}</a></li>
            @else
                <li><span aria-disabled="true">{{ __('common.next') }}</span></li>
            @endif
        </ul>
    </nav>
@endif
