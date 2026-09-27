@props(['title' => null, 'description' => null])

<div class="text-center">
    @if ($title)
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
    @endif

    @if ($description)
        <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>
    @endif
</div>
