@php
    $look = \App\Support\AvatarCatalog::normalize($avatar ?? null);
    $sprite = asset('images/avatar-sprite.svg');
@endphp
<svg class="cute-avatar" viewBox="0 0 100 100" aria-hidden="true" focusable="false" data-avatar-preview>
    <circle cx="50" cy="50" r="49" fill="{{ \App\Support\AvatarCatalog::COLORS[$look['color']] }}" data-avatar-bg/>
    <use href="{{ $sprite }}#{{ $look['character'] }}" data-avatar-character/>
    <use href="{{ $sprite }}#{{ $look['face'] }}" data-avatar-face/>
    <use href="{{ $sprite }}#{{ $look['accessory'] }}" data-avatar-accessory/>
</svg>
