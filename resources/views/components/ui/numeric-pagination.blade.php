@props([
    'paginator',
    'onEachSide' => 1,
    'summaryClass' => 'text-sm font-normal text-[var(--color-prime-muted)]',
    'containerClass' => 'flex flex-col gap-3 border-t border-[var(--color-prime-border)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between',
    'navClass' => 'flex items-center gap-3',
])

@php
    $window = \Illuminate\Pagination\UrlWindow::make($paginator->onEachSide((int) $onEachSide));
    $paginationElements = array_filter([
        $window['first'],
        is_array($window['slider']) ? '...' : null,
        $window['slider'],
        is_array($window['last']) ? '...' : null,
        $window['last'],
    ]);
@endphp

@if ($paginator->total() > 0)
    <div {{ $attributes->merge(['class' => $containerClass]) }}>
        <p class="{{ $summaryClass }}">
            Menampilkan {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} dari total {{ $paginator->total() }} data
        </p>

        @if ($paginator->hasPages())
            <nav role="navigation" aria-label="Pagination Navigation" class="{{ $navClass }}">
                @if ($paginator->onFirstPage())
                    <span class="text-[16px] text-[var(--color-prime-muted)]">
                        &lt;<span class="sr-only">Prev</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="text-[16px] text-[var(--color-prime-muted)]">
                        &lt;<span class="sr-only">Prev</span>
                    </a>
                @endif

                @foreach ($paginationElements as $element)
                    @if (is_string($element))
                        <span class="text-[16px] text-[var(--color-prime-muted)]">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page" class="inline-flex h-9 min-w-9 items-center justify-center rounded-[var(--radius-micro)] bg-[var(--color-prime-active-badge-bg)] px-3 text-[14px] font-semibold text-[var(--color-prime-active-badge-text)]">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-[var(--radius-micro)] border border-[var(--color-prime-border)] bg-white px-3 text-[14px] font-medium text-[var(--color-prime-ink)]">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="text-[16px] text-[var(--color-prime-muted)]">
                        &gt;<span class="sr-only">Next</span>
                    </a>
                @else
                    <span class="text-[16px] text-[var(--color-prime-muted)]">
                        &gt;<span class="sr-only">Next</span>
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif
