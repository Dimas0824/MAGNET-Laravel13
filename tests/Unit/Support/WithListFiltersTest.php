<?php

use App\Support\Concerns\WithListFilters;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithPagination;

/*
|--------------------------------------------------------------------------
| Test doubles for the WithListFilters trait
|--------------------------------------------------------------------------
|
| The trait is a plain Livewire trait, so it can be exercised either through
| the Livewire test harness (which fires the `updated*` lifecycle hooks) or by
| instantiating a bare double and calling its public helpers directly.
|
| `resolvedSortColumn()` / `defaultSort()` / `sortableColumns()` are
| deliberately `protected` (that is part of the trait's contract), and
| Livewire's `__call` only dispatches to PUBLIC methods. The doubles therefore
| expose a thin public `resolveSort()` proxy that forwards to the protected
| helper under test.
|
*/

/**
 * A component that uses the trait with its DEFAULT, un-overridden config so
 * the fallback behaviour (default sort + empty whitelist) can be asserted.
 */
class ListFiltersDouble extends Component
{
    use WithPagination, WithListFilters;

    public function resolveSort(): string
    {
        return $this->resolvedSortColumn();
    }

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/**
 * A component that mimics how a real page OVERRIDES the trait's extension
 * points (an allow-list of sortable columns + a custom default sort).
 */
class ListFiltersWhitelistedDouble extends Component
{
    use WithPagination, WithListFilters;

    protected function defaultSort(): string
    {
        return 'nama';
    }

    protected function sortableColumns(): array
    {
        return ['nama', 'created_at'];
    }

    public function resolveSort(): string
    {
        return $this->resolvedSortColumn();
    }

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/*
|--------------------------------------------------------------------------
| Defaults
|--------------------------------------------------------------------------
*/

it('defaults perPage to 10', function () {
    $component = new ListFiltersDouble;

    expect($component->perPage)->toBe(10);
});

it('returns perPage options 10/25/50/100', function () {
    $component = new ListFiltersDouble;

    expect($component->perPageOptions())->toBe([10, 25, 50, 100]);
});

it('defaults search/sort state to safe empty values', function () {
    $component = new ListFiltersDouble;

    expect($component->search)->toBe('')
        ->and($component->sortBy)->toBe('')
        ->and($component->sortDirection)->toBe('asc');
});

/*
|--------------------------------------------------------------------------
| Sort whitelist + fallback
|--------------------------------------------------------------------------
*/

it('whitelists sortBy and falls back to default', function () {
    $component = new ListFiltersWhitelistedDouble;

    // A whitelisted column is honoured as-is...
    $component->sortBy = 'created_at';
    expect($component->resolveSort())->toBe('created_at');

    // ...anything else (empty, unknown, or malicious) falls back to default.
    $component->sortBy = 'passwords.hash; drop table x';
    expect($component->resolveSort())->toBe('nama');
});

it('falls back to the id default sort when nothing is overridden', function () {
    $component = new ListFiltersDouble;

    $component->sortBy = 'anything';
    expect($component->resolveSort())->toBe('id');

    $component->sortBy = '';
    expect($component->resolveSort())->toBe('id');
});

/*
|--------------------------------------------------------------------------
| Page reset lifecycle
|--------------------------------------------------------------------------
|
| Livewire's pagination trait keeps page position in the public
| `$paginators` array (NOT a `$page` property), so reset assertions read
| `paginators.page`. `setPage()` is the public entry point to move pages.
|
*/

it('resets page when search changes', function () {
    Livewire::test(ListFiltersDouble::class)
        ->call('setPage', 3)
        ->assertSet('paginators.page', 3)
        ->set('search', 'magang')
        ->assertSet('paginators.page', 1);
});

it('resets page when sortBy changes', function () {
    Livewire::test(ListFiltersDouble::class)
        ->call('setPage', 4)
        ->assertSet('paginators.page', 4)
        ->set('sortBy', 'created_at')
        ->assertSet('paginators.page', 1);
});

it('resets page when perPage changes', function () {
    Livewire::test(ListFiltersDouble::class)
        ->call('setPage', 5)
        ->assertSet('paginators.page', 5)
        ->set('perPage', 25)
        ->assertSet('paginators.page', 1);
});

it('resets page when sortDirection changes', function () {
    Livewire::test(ListFiltersDouble::class)
        ->call('setPage', 6)
        ->assertSet('paginators.page', 6)
        ->set('sortDirection', 'desc')
        ->assertSet('paginators.page', 1);
});
