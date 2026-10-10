<button type="button" class="look-button" data-open-avatar @disabled($me['is_ready'])>🎨 แต่งตัวละคร</button>
@if ($me['is_ready'])<small class="look-hint">ยกเลิกพร้อมเพื่อเปลี่ยนหน้าตา</small>@endif
<dialog id="avatar-picker" class="avatar-picker" aria-labelledby="avatar-picker-title">
    <form method="POST" action="{{ route('rooms.avatar', ['code' => $room['code']]) }}" id="avatar-form">
        @csrf
        <div class="picker-heading"><div><h2 id="avatar-picker-title">หน้าตาของคุณ</h2><p>เลือกได้ตามใจ หน้าตาไม่เกี่ยวกับบทบาทในเกม</p></div><button type="button" data-close-avatar aria-label="ปิด">✕</button></div>
        <div class="avatar-preview">@include('games.avatar', ['avatar' => $me['avatar'] ?? null])</div>
        @foreach (['character' => ['ตัวละคร', \App\Support\AvatarCatalog::CHARACTERS], 'face' => ['สีหน้า', \App\Support\AvatarCatalog::FACES], 'accessory' => ['ของตกแต่ง', \App\Support\AvatarCatalog::ACCESSORIES], 'color' => ['สีพื้นหลัง', \App\Support\AvatarCatalog::COLORS]] as $field => [$label, $choices])
        <fieldset class="look-options"><legend>{{ $label }}</legend><div>
            @foreach ($choices as $value => $title)
            <label class="look-option">
                <input type="radio" name="{{ $field }}" value="{{ $value }}" @checked(($me['avatar'][$field] ?? \App\Support\AvatarCatalog::normalize(null)[$field]) === $value) required>
                <span>
                    @if ($field === 'character') @include('games.avatar', ['avatar' => ['character' => $value]]) @endif
                    @if ($field === 'color') <i style="background:{{ $title }}"></i><span class="visually-hidden">{{ ['lavender' => 'ม่วง', 'mint' => 'เขียว', 'peach' => 'ส้ม', 'sky' => 'ฟ้า'][$value] }}</span> @else {{ $title }} @endif
                </span>
            </label>
            @endforeach
        </div></fieldset>
        @endforeach
        <div class="picker-footer"><button type="button" data-close-avatar>ยกเลิก</button><button type="submit" class="look-button">บันทึกหน้าตา</button></div>
    </form>
</dialog>
