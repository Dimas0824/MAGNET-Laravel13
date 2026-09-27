<?php

namespace App\Support\Concerns;

/**
 * Reusable list-page filter state for Volt / Livewire pages.
 *
 * Consolidates the search / sort / pagination-size state that used to be
 * copy-pasted across every list page. A consuming page composes this trait
 * alongside Livewire's own pagination trait:
 *
 *     use WithPagination, WithListFilters;
 *
 * The trait never assumes the pagination trait is present. `resetPage()`
 * belongs to `Livewire\WithPagination`, so every mutation hook guards the
 * call — a page that forgets to add `WithPagination` degrades to "no page
 * reset" instead of fatalling.
 *
 * Override points for per-page customisation:
 *   - `defaultSort()`      — the column used when `$sortBy` is not whitelisted
 *   - `sortableColumns()`  — the allow-list of columns a user may sort by
 */
trait WithListFilters
{
    /**
     * Free-text search term bound to the page's search input.
     */
    public string $search = '';

    /**
     * The user-requested sort column. Validated against the whitelist via
     * `resolvedSortColumn()` before ever reaching a query builder.
     */
    public string $sortBy = '';

    /**
     * Sort direction. Kept as a plain string so the page owns how (or whether)
     * it maps to ascending/descending.
     */
    public string $sortDirection = 'asc';

    /**
     * Number of records per page.
     */
    public int $perPage = 10;

    /**
     * Returning to page 1 whenever the result set changes shape prevents the
     * user from landing on an out-of-range page.
     */
    public function updatedSearch(): void
    {
        $this->resetListPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetListPage();
    }

    public function updatedSortDirection(): void
    {
        $this->resetListPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetListPage();
    }

    /**
     * The column to sort by when `$sortBy` is not in `sortableColumns()`.
     * Override in the consuming page to change the fallback.
     */
    protected function defaultSort(): string
    {
        return 'id';
    }

    /**
     * The allow-list of columns a user is permitted to sort by. Empty by
     * default, so an un-overridden page can never sort by arbitrary input.
     */
    protected function sortableColumns(): array
    {
        return [];
    }

    /**
     * Resolve the user-requested sort column against the whitelist, falling
     * back to `defaultSort()` for anything unknown — including malicious
     * input that must not reach the query builder.
     */
    protected function resolvedSortColumn(): string
    {
        return in_array($this->sortBy, $this->sortableColumns(), true)
            ? $this->sortBy
            : $this->defaultSort();
    }

    /**
     * The page-size choices offered by the per-page selector.
     */
    public function perPageOptions(): array
    {
        return [10, 25, 50, 100];
    }

    /**
     * `resetPage()` is provided by `Livewire\WithPagination`. Guard the call
     * so the trait stays usable (as a no-op) without that companion trait.
     */
    protected function resetListPage(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }
}
