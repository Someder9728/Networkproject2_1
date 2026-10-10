<div data-dash-zone="actions">
    <section class="play-guide" aria-labelledby="play-guide-title">
        <span class="guide-eyebrow">ตอนนี้ทำอะไร?</span>
        <h2 id="play-guide-title">
            @if ($game['status'] === 'finished') เกมจบแล้ว 🎉
            @elseif ($viewerState['has_left'] ?? false) คุณออกจากเกมแล้ว
            @elseif (! ($viewerState['is_alive'] ?? true)) คุณเป็นผู้ชมแล้ว 👻
            @elseif ($game['current_phase'] === 'day_voting' && $game['voting_stage'] === 'defense') {{ $game['trial']['is_defendant'] ? 'ถึงตาคุณแก้ต่าง' : 'ฟังคำแก้ต่างก่อนตัดสิน' }}
            @elseif ($game['current_phase'] === 'day_voting' && $game['voting_stage'] === 'verdict') โหวตให้ออกหรือรอด
            @elseif ($game['current_phase'] === 'day_voting') เลือกคนที่คุณสงสัย 👀
            @elseif ($game['current_phase'] === 'night') {{ in_array($game['my_role'], ['werewolf', 'seer', 'guardian']) ? 'ใช้ความสามารถของคุณ 🌙' : 'พักผ่อน รอเช้าวันใหม่ 🌙' }}
            @else คุยกัน ใครน่าสงสัย? 💬 @endif
        </h2>
        <p>
            @if ($game['status'] === 'finished') ดูฝ่ายที่ชนะและบทบาททุกคนด้านบน หรือย้อนดูผลที่ผ่านมา
            @elseif (! ($viewerState['is_alive'] ?? true)) ติดตามเกมต่อได้ พูดคุยหรือกระซิบกับผู้เสียชีวิตด้วยกัน
            @elseif ($game['current_phase'] === 'day_voting' && $game['voting_stage'] === 'defense') {{ $game['trial']['is_defendant'] ? 'พิมพ์คำแก้ต่างแล้วกดส่งก่อนหมดเวลา หากไม่ส่งจะออกอัตโนมัติ' : 'เมื่อหมดเวลา จะให้โหวตยืนยันอีกครั้ง' }}
            @elseif ($game['current_phase'] === 'day_voting' && $game['voting_stage'] === 'verdict') อ่านคำแก้ต่าง แล้วเลือกคำตอบด้านล่างก่อนหมดเวลา
            @elseif ($game['current_phase'] === 'day_voting') แตะตัวละครบนโต๊ะ แล้วกดโหวต เปลี่ยนคนได้ก่อนหมดเวลา
            @elseif ($game['current_phase'] === 'night') {{ $game['my_role'] === 'villager' ? 'ชาวบ้านไม่มีการกระทำตอนกลางคืน ระบบจะเปลี่ยนช่วงให้เอง' : 'เลือกเป้าหมายแล้วกดยืนยันด้านล่างก่อนหมดเวลา' }}
            @else เปิดแชทเพื่อคุยกับทุกคน หรือเลือกชื่อเพื่อกระซิบ อ่านบอร์ดหลักฐานก่อนโหวต @endif
        </p>
        @if ($game['current_phase'] !== 'night' || ! ($viewerState['is_alive'] ?? true)) <button type="button" data-open-chat class="look-button">💬 เปิดแชท</button> @endif
        @if ($game['can_vote']) <button type="button" data-show-table class="look-button">เลือกคนบนโต๊ะ →</button> @endif
    </section>
</div>
