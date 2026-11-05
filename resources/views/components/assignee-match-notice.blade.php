{{-- Status line for AI suggestions. Expects `loading`, `source` and `matches` in the Alpine scope. --}}
<div {{ $attributes->merge(['class' => 'text-xs text-gray-500']) }}>
    <p x-show="loading">Finding the best matches...</p>
    <p x-show="!loading && source === 'exact'" class="text-amber-700">AI matching isn't available right now, so these only match on identical field names.</p>
    <p x-show="!loading && source === 'error'" class="text-amber-700">Suggestions couldn't load. You can still choose someone yourself.</p>
    <p x-show="!loading && source === 'none'">No one has Fields of Focus yet. Add them to get suggestions.</p>
    <p x-show="!loading && source === 'ai' && matches.length === 0">No close matches. Try adding Fields of Focus to more people.</p>
</div>
