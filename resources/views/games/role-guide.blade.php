@php
    $roleGuides = [
        'werewolf' => ['หน้าที่' => 'ซ่อนตัวในหมู่ชาวบ้านและร่วมโหวต เลือกโจมตีผู้เล่นที่ไม่ใช่หมาป่าในตอนกลางคืน เป้าหมายที่ได้เสียงมากที่สุดจะถูกโจมตี หากคะแนนเสมอจะไม่มีการโจมตี', 'เป้าหมาย' => 'ทำให้จำนวนหมาป่าที่ยังมีชีวิตมากกว่าหรือเท่ากับฝ่ายชาวบ้าน คืนสงบจะโจมตีไม่ได้ และผู้คุ้มกันอาจป้องกันเป้าหมายได้'],
        'seer' => ['หน้าที่' => 'เลือกตรวจผู้เล่นตอนกลางคืนเพื่อรู้ว่าเป็นหมาป่าหรือไม่ ใช้ข้อมูลช่วยตัดสินใจโหวตและชี้นำทีม โดยระวังการเปิดเผยตัว', 'เป้าหมาย' => 'ร่วมกับฝ่ายชาวบ้านกำจัดหมาป่าทั้งหมด'],
        'guardian' => ['หน้าที่' => 'เลือกปกป้องผู้เล่นที่ยังมีชีวิต 1 คนต่อคืน เพื่อป้องกันการโจมตีของหมาป่า ห้ามปกป้องคนเดิมติดกันสองคืน', 'เป้าหมาย' => 'รักษาชีวิตคนสำคัญและช่วยฝ่ายชาวบ้านกำจัดหมาป่าทั้งหมด'],
        'villager' => ['หน้าที่' => 'ไม่มีพลังตอนกลางคืน สังเกตคำพูดและหลักฐาน พูดคุยกับผู้เล่น และโหวตคนที่สงสัยว่าเป็นหมาป่าในตอนกลางวัน', 'เป้าหมาย' => 'ร่วมกับผู้หยั่งรู้และผู้คุ้มกันถ้ามี กำจัดหมาป่าทั้งหมด'],
    ];
    $guide = $roleGuides[$game['my_role']] ?? null;
@endphp
@if ($guide)
<article class="werewolf-role-card role-card-{{ $game['my_role'] }}" aria-label="การ์ดบทบาท{{ $roleLabels[$game['my_role']] }}">
    <div class="role-card-topline"><span>WEREWOLF</span><span>{{ $game['my_role'] === 'werewolf' ? 'ฝ่ายหมาป่า' : 'ฝ่ายชาวบ้าน' }}</span></div>
    <div class="role-card-art">
        <img src="{{ asset('images/roles/'.$game['my_role'].'.png') }}" alt="ภาพบทบาท{{ $roleLabels[$game['my_role']] }}" width="320" height="260">
        <span class="role-card-seal" aria-hidden="true">{{ ['werewolf' => '☾', 'seer' => '✧', 'guardian' => '◇', 'villager' => '☀'][$game['my_role']] ?? '✧' }}</span>
    </div>
    <div class="role-card-body">
        <span class="role-card-caption">คุณคือ</span>
        <h3 class="role-card-title">{{ $roleLabels[$game['my_role']] }}</h3>
        <div class="role-card-rule" aria-hidden="true">✦</div>
        <section class="role-card-description"><h4>หน้าที่</h4><p>{{ $guide['หน้าที่'] }}</p></section>
        @if ($game['my_role'] === 'seer')
        <p class="role-card-limit">ตรวจได้ {{ $game['my_seer_checks_limit'] ?? 'ไม่จำกัด' }} ครั้งต่อคืน</p>
        @endif
        <section class="role-card-win"><h4>เป้าหมายเพื่อชนะ</h4><p>{{ $guide['เป้าหมาย'] }}</p></section>
        @if ($game['game_mode'] === 'short')
        <p class="role-card-short">โหมดสั้น: หากยังไม่มีผู้ชนะเมื่อครบจำนวนรอบหรือหมดเวลารวม ฝ่ายชาวบ้านชนะ</p>
        @endif
        <div class="role-card-bottomline" aria-hidden="true">✦ &nbsp; WEREWOLF ONLINE &nbsp; ✦</div>
    </div>
</article>
@else
<p class="dashboard-empty">กำลังแจกบทบาท กรุณารอสักครู่</p>
@endif
