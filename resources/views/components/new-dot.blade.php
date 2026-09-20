{{--
    Red "new" dot — flags an entry that arrived since the admin last opened its
    module (see User::moduleSeenAt()/markModuleSeen()). Same red as the sidebar
    badge in components/layouts/admin.blade.php, so the dot on the item and the
    dot on the nav read as one system.

    Props:
      - size: 'sm' (8px, inline next to text — table rows) or 'md' (12px,
        for sitting on a card banner)

    Positioning is left to the caller via normal class attributes, e.g.
    <x-new-dot size="md" class="absolute left-3 top-3 ring-2 ring-white" />
--}}
@props(['size' => 'sm'])

<span {{ $attributes->merge(['class' => 'flex-shrink-0 rounded-full bg-red-500 ' . ($size === 'md' ? 'h-3 w-3' : 'h-2 w-2')]) }}
    role="img" title="New since your last visit" aria-label="New since your last visit"></span>
