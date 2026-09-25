<section id="room-chat">
    <h2>แชต</h2>

    <label>
        ช่องสนทนา
        <select id="chat-channel"></select>
    </label>

    <button type="button" id="chat-refresh">
        โหลดข้อความใหม่
    </button>

    <ul
        id="chat-messages"
        aria-live="polite"
        style="max-height: 260px; overflow-y: auto;"
    ></ul>

    <form id="chat-form">
        <label>
            ข้อความ
            <input
                id="chat-message"
                maxlength="1000"
                autocomplete="off"
                required
                disabled
            >
        </label>

        <button id="chat-submit" type="submit" disabled>
            ส่ง
        </button>
    </form>

    <p id="chat-feedback" role="status"></p>
</section>