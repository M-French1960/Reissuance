@if ($paginator->hasPages())
    <nav aria-label="Pagination">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li><span aria-disabled="true">Précédent</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a></li>
            @endif

            <li><span aria-current="page">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span></li>

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a></li>
            @else
                <li><span aria-disabled="true">Suivant</span></li>
            @endif
        </ul>
    </nav>
@endif
