<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="room-code" content="{{ $game['room_code'] }}">
    <meta name="player-name"
        content="{{ collect($game['players'])->first(fn ($player) => ($player['player_uuid'] ?? null) === session('player_uuid'))['name'] ?? '' }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite(['resources/js/app.js'])

    <title>Werewolf Online - {{ $game['room_code'] }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Kanit:wght@400;500;600;700;900&display=swap"
        rel="stylesheet">

    <style>
    @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&display=swap');

    * {
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    html {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    html::-webkit-scrollbar {
        display: none;
    }

    body {
        min-height: 100vh;
        margin: 0;
        color: #f8f7ff;
        font-family: 'Kanit', sans-serif;
        background:
            radial-gradient(circle at 82% 5%,
                rgba(255, 171, 0, .18) 0,
                rgba(255, 171, 0, .05) 12%,
                transparent 30%),
            radial-gradient(circle at 15% 30%,
                rgba(76, 29, 149, .18),
                transparent 35%),
            linear-gradient(135deg,
                #050816 0%,
                #0b0d1b 45%,
                #160b29 100%);

        overflow-x: hidden;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        background:
            linear-gradient(90deg,
                rgba(76, 29, 149, .08),
                transparent 35%,
                transparent 65%,
                rgba(88, 28, 135, .08));
        z-index: 0;
    }

    body::after {
        content: '';
        position: fixed;
        right: 6%;
        top: 45px;
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #ffd66b;
        box-shadow:
            0 0 20px rgba(255, 214, 107, .65),
            0 0 60px rgba(255, 166, 0, .35);
        opacity: .95;
        pointer-events: none;
        z-index: 0;
    }

    .smoke-wrapper {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100vw;
        height: 45vh;
        overflow: hidden;
        pointer-events: none;
        z-index: 1;
    }

    .smoke-layer-1 {
        position: absolute;
        bottom: -15%;
        left: -10%;
        width: 120%;
        height: 100%;
        background:
            radial-gradient(ellipse at 30% 100%,
                rgba(148, 163, 184, 0.4) 0%,
                rgba(71, 85, 105, 0.15) 50%,
                transparent 80%);
        filter: blur(20px);
        animation: fogPulseVisible1 8s ease-in-out infinite alternate;
    }

    .smoke-layer-2 {
        position: absolute;
        bottom: -20%;
        right: -10%;
        width: 130%;
        height: 90%;
        background:
            radial-gradient(ellipse at 70% 100%,
                rgba(226, 232, 240, 0.35) 0%,
                rgba(148, 163, 184, 0.1) 45%,
                transparent 75%);
        filter: blur(25px);
        animation: fogPulseVisible2 11s ease-in-out infinite alternate;
    }

    @keyframes fogPulseVisible1 {
        0% {
            opacity: 0.3;
            transform: translate3d(0, 0, 0) scale(0.9);
        }

        50% {
            opacity: 0.8;
            transform: translate3d(-4%, -15px, 0) scale(1.15);
        }

        100% {
            opacity: 0.4;
            transform: translate3d(-8%, -5px, 0) scale(1.02);
        }
    }

    @keyframes fogPulseVisible2 {
        0% {
            opacity: 0.4;
            transform: translate3d(0, 0, 0) scale(1.1);
        }

        50% {
            opacity: 0.3;
            transform: translate3d(5%, -20px, 0) scale(0.95);
        }

        100% {
            opacity: 0.6;
            transform: translate3d(10%, -10px, 0) scale(1.18);
        }
    }


    @keyframes floatSmoke {

        0%,
        100% {
            transform: translate(0, 0) scale(1);
        }

        50% {
            transform: translate(40px, -30px) scale(1.15);
        }
    }

    .topbar {
        position: relative;
        z-index: 5;
        min-height: 58px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 28px;
        border-top: 3px solid #5b21b6;
        border-bottom: 1px solid rgba(139, 92, 246, .16);
        background: rgba(4, 6, 15, .82);
        backdrop-filter: blur(18px);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .brand-icon {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        background: linear-gradient(135deg,
                #5b21b6,
                #9333ea);
        box-shadow: 0 0 20px rgba(147, 51, 234, .35);
        font-size: 17px;
    }

    .brand-title {
        font-size: 14px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: .3px;
        font-family: 'Cinzel', serif;
    }

    .brand-subtitle {
        margin-top: 3px;
        color: #777c99;
        font-size: 9px;
    }

    .top-pill {
        padding: 6px 12px;
        border: 1px solid rgba(139, 92, 246, .25);
        border-radius: 999px;
        background: rgba(20, 16, 45, .75);
        color: #aaa4d7;
        font-size: 10px;
        font-weight: 600;
    }

    .top-pill.highlight {
        color: #d8b4fe;
        border-color: rgba(168, 85, 247, .4);
    }

    .game-page {
        position: relative;
        z-index: 1;
        width: min(1180px, calc(100% - 32px));
        margin: 0 auto;
        padding: 30px 0 60px;
    }

    .game-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 290px;
        gap: 14px;
        align-items: start;
    }

    .main-panel,
    .side-panel {
        border: 1px solid rgba(100, 116, 139, .18);
        border-radius: 14px;
        background: rgba(8, 12, 27, .78);
        box-shadow:
            0 20px 70px rgba(0, 0, 0, .35),
            inset 0 1px 0 rgba(255, 255, 255, .025);
        backdrop-filter: blur(18px);
    }

    .main-panel {
        overflow: hidden;
    }

    .side-panel {
        overflow: hidden;
    }

    .room-header {
        padding: 20px 22px;
        border-bottom: 1px solid rgba(139, 92, 246, .14);
        background:
            linear-gradient(90deg,
                rgba(76, 29, 149, .12),
                rgba(8, 12, 27, .2));
    }

    .room-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .room-title {
        margin: 0;
        font-family: 'Cinzel', serif;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: 1px;
    }

    .room-code {
        margin-top: 3px;
        color: #9b8fbd;
        font-size: 13px;
    }

    .difficulty-badge {
        padding: 6px 12px;
        border: 1px solid rgba(168, 85, 247, .35);
        border-radius: 999px;
        background: rgba(88, 28, 135, .18);
        color: #d8b4fe;
        font-size: 11px;
        font-weight: 700;
    }

    .content {
        padding: 18px;
    }

    .phase-banner {
        margin-bottom: 14px;
        padding: 16px;
        border: 1px solid rgba(168, 85, 247, .18);
        border-radius: 12px;
        background:
            linear-gradient(135deg,
                rgba(76, 29, 149, .18),
                rgba(15, 23, 42, .55));
    }

    .phase-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .phase-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .phase-icon {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: rgba(124, 58, 237, .2);
        border: 1px solid rgba(168, 85, 247, .2);
        color: #c084fc;
        font-size: 18px;
    }

    .phase-label {
        color: #8e91a9;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
    }

    .phase-title {
        margin-top: 2px;
        font-size: 20px;
        font-weight: 700;
    }

    .phase-round {
        color: #8589a3;
        font-size: 11px;
    }

    .timer-box {
        min-width: 85px;
        padding: 7px 12px;
        border: 1px solid rgba(168, 85, 247, .25);
        border-radius: 9px;
        background: rgba(3, 7, 18, .55);
        text-align: center;
    }

    .timer-label {
        color: #737891;
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .timer {
        margin-top: 1px;
        color: #d8b4fe;
        font-family: 'Kanit', sans-serif;
        font-size: 19px;
        font-weight: 800;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 14px;
    }

    .info-box {
        padding: 12px;
        border: 1px solid rgba(100, 116, 139, .14);
        border-radius: 10px;
        background: rgba(15, 23, 42, .5);
    }

    .info-label {
        color: #6f748d;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .info-value {
        margin-top: 3px;
        font-size: 14px;
        font-weight: 700;
    }

    .role-banner {
        margin-bottom: 16px;
        padding: 18px;
        border: 1px solid rgba(168, 85, 247, .28);
        border-radius: 12px;
        background:
            radial-gradient(circle at 50% 0,
                rgba(147, 51, 234, .2),
                transparent 55%),
            rgba(15, 23, 42, .7);
        text-align: center;
    }

    .role-label {
        color: #8589a3;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .role-name {
        margin-top: 2px;
        color: #d8b4fe;
        font-family: 'Kanit', sans-serif;
        font-size: 25px;
        font-weight: 700;
    }

    .section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .section-title {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
    }

    .section-count {
        color: #777c99;
        font-size: 10px;
    }

    .players-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .player-card {
        position: relative;
        min-height: 72px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border: 1px solid rgba(71, 85, 105, .2);
        border-radius: 10px;
        background:
            linear-gradient(135deg,
                rgba(15, 23, 42, .92),
                rgba(10, 14, 29, .72));
    }

    .player-card.you {
        border-color: rgba(168, 85, 247, .4);
        box-shadow: inset 2px 0 0 #a855f7;
    }

    .player-avatar {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background:
            linear-gradient(135deg,
                #4c1d95,
                #9333ea);
        color: white;
        font-size: 14px;
        font-weight: 800;
    }

    .player-card:nth-child(even) .player-avatar {
        background:
            linear-gradient(135deg,
                #172554,
                #2563eb);
    }

    .player-details {
        min-width: 0;
    }

    .player-name {
        overflow: hidden;
        color: #e8e8f2;
        font-size: 12px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .player-status {
        margin-top: 2px;
        color: #6f748d;
        font-size: 9px;
    }

    .you-badge {
        color: #c084fc;
        font-size: 9px;
    }

    .dead {
        opacity: .45;
    }

    .left-player {
        opacity: .35;
    }

    .action-area {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 16px;
    }

    .btn-game {
        min-height: 38px;
        padding: 8px 17px;
        border: 0;
        border-radius: 9px;
        background:
            linear-gradient(135deg,
                #6d28d9,
                #a855f7);
        box-shadow: 0 8px 25px rgba(126, 34, 206, .22);
        color: white;
        font-family: 'Kanit', sans-serif;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .btn-game:hover {
        color: white;
        transform: translateY(-1px);
        background:
            linear-gradient(135deg,
                #7c3aed,
                #c084fc);
    }

    .btn-gamee {
        min-height: 38px;
        padding: 8px 17px;
        border: 1px solid rgba(168, 85, 247, 0.35);
        border-radius: 10px;
        background: rgba(76, 29, 149, 0.18);
        color: #c084fc;
        font-family: 'Kanit', sans-serif;
        font-size: 12px;
        font-weight: 700;
    }

    .btn-gamee:hover {
        background: rgba(76, 29, 149, 0.3);
        border-color: rgba(192, 132, 252, 0.5);
        color: #d8b4fe;
    }

    .btn-secondary-game {
        min-height: 38px;
        padding: 8px 17px;
        border-radius: 9px;
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(248, 113, 113, 0.4);
        color: #fca5a5;
        font-family: 'Kanit', sans-serif;
        font-size: 12px;
        font-weight: 600;
    }

    .btn-secondary-game:hover {
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(248, 113, 113, 0.6);
    }

    .action-panel {
        margin-top: 14px;
        padding: 15px;
        border: 1px solid rgba(168, 85, 247, .16);
        border-radius: 11px;
        background: rgba(15, 23, 42, .45);
    }

    .action-title {
        margin-bottom: 10px;
        font-size: 14px;
        font-weight: 700;
    }

    .game-select {
        width: 100%;
        min-height: 40px;
        padding: 8px 12px;
        border: 1px solid rgba(100, 116, 139, .25);
        border-radius: 8px;
        outline: none;
        background: #0b1020;
        color: #e7e7ef;
        font-family: 'Kanit', sans-serif;
        font-size: 12px;
    }

    .game-select:focus {
        border-color: rgba(168, 85, 247, .55);
        box-shadow: 0 0 0 3px rgba(168, 85, 247, .08);
    }

    .game-select option {
        color: #111827;
    }

    .result-panel {
        margin-top: 14px;
        padding: 15px;
        border: 1px solid rgba(45, 212, 191, .14);
        border-radius: 11px;
        background: rgba(13, 148, 136, .06);
    }

    .result-title {
        margin-bottom: 7px;
        color: #99f6e4;
        font-size: 14px;
        font-weight: 700;
    }

    .winner-panel {
        margin-bottom: 15px;
        padding: 25px;
        border: 1px solid rgba(250, 204, 21, .22);
        border-radius: 12px;
        background:
            radial-gradient(circle at 50% 0,
                rgba(250, 204, 21, .14),
                transparent 60%),
            rgba(15, 23, 42, .7);
        text-align: center;
    }

    .winner-label {
        color: #99939f;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .winner-title {
        margin-top: 5px;
        color: #fde68a;
        font-family: 'Kanit', sans-serif;
        font-size: 28px;
        font-weight: 700;
    }

    .event-panel {
        margin-top: 14px;
        padding: 15px;
        border: 1px solid rgba(245, 158, 11, .18);
        border-radius: 11px;
        background: rgba(120, 53, 15, .08);
    }

    .event-title {
        color: #fbbf24;
        font-size: 14px;
        font-weight: 700;
    }

    .event-description {
        margin-top: 5px;
        color: #a7a8b8;
        font-size: 11px;
        line-height: 1.6;
    }

    .alert-game {
        margin-top: 12px;
        margin-bottom: 12px;
        padding: 10px 12px;
        border: 1px solid rgba(168, 85, 247, .18);
        border-radius: 8px;
        background: rgba(88, 28, 135, .1);
        color: #b7accd;
        font-size: 11px;
    }

    .error-message {
        margin-bottom: 8px;
        padding: 10px 12px;
        border: 1px solid rgba(248, 113, 113, .2);
        border-radius: 8px;
        background: rgba(127, 29, 29, .12);
        color: #fca5a5;
        font-size: 11px;
    }

    .side-header {
        padding: 13px 14px;
        border-bottom: 1px solid rgba(100, 116, 139, .16);
    }

    .side-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .side-title {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 800;
    }

    .online-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 10px rgba(34, 197, 94, .7);
    }

    .side-count {
        color: #777c99;
        font-size: 9px;
    }

    .side-content {
        padding: 13px;
    }

    .chat-message {
        margin-bottom: 9px;
        padding: 9px 10px;
        border: 1px solid rgba(100, 116, 139, .12);
        border-radius: 9px;
        background: rgba(15, 23, 42, .55);
        color: #aeb1c4;
        font-size: 10px;
        line-height: 1.5;
    }

    .chat-message.system {
        border-color: rgba(168, 85, 247, .13);
        background: rgba(76, 29, 149, .08);
    }

    .chat-toolbar {
        padding: 10px 13px;
        border-bottom: 1px solid rgba(100, 116, 139, .12);
        background: rgba(5, 8, 20, .35);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chat-channels {
        display: flex;
        align-items: center;
        gap: 5px;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .chat-channels::-webkit-scrollbar {
        display: none;
    }

    .chat-channel,
    .chat-refresh {
        flex: 0 0 auto;
        padding: 5px 9px;
        border: 1px solid rgba(100, 116, 139, .18);
        border-radius: 999px;
        background: rgba(15, 23, 42, .55);
        color: #7f849b;
        font-family: 'Kanit', sans-serif;
        font-size: 9px;
        font-weight: 700;
        cursor: pointer;
    }

    .chat-channel:hover,
    .chat-channel.active,
    .chat-refresh:hover,
    .chat-refresh.active {
        border-color: rgba(168, 85, 247, .35);
        background: rgba(76, 29, 149, .2);
        color: #d8b4fe;
    }

    .chat-channel.werewolf.active {
        border-color: rgba(239, 68, 68, .3);
        background: rgba(127, 29, 29, .15);
        color: #fca5a5;
    }

    .chat-channel.dead.active {
        border-color: rgba(148, 163, 184, .25);
        background: rgba(71, 85, 105, .16);
        color: #cbd5e1;
    }

    .chat-messages {
        height: 330px;
        overflow-y: auto;
        padding: 13px;
        scrollbar-width: thin;
        scrollbar-color: rgba(139, 92, 246, .25) transparent;
    }

    .chat-messages::-webkit-scrollbar {
        width: 4px;

    }

    .chat-messages::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(139, 92, 246, .25);
    }

    .chat-empty {
        min-height: 170px;
        display: grid;
        place-items: center;
        padding: 25px;
        color: #5e637b;
        font-size: 10px;
        text-align: center;
        line-height: 1.7;
    }

    .chat-message-row {
        display: flex;
        flex-direction: column;
        width: 100%;
        margin-bottom: 10px;
    }

    .chat-message-row.mine {
        align-items: flex-end;
    }

    .chat-message-row:not(.mine) {
        align-items: flex-start;
    }

    .chat-message-meta {
        display: flex;
        align-items: center;
        margin-bottom: 3px;
        padding: 0 3px;
    }

    .chat-message-name {
        max-width: 155px;
        overflow: hidden;
        color: #c4b5fd;
        font-size: 9px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .chat-message-bubble {
        max-width: 82%;
        padding: 8px 11px;
        border: 1px solid rgba(100, 116, 139, .12);
        border-radius: 12px;
        background: rgba(15, 23, 42, .65);
        color: #c4c7d5;
        font-size: 10px;
        line-height: 1.55;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }

    .chat-message-row:not(.mine) .chat-message-bubble {
        border-bottom-left-radius: 4px;
    }

    .chat-message-row.mine .chat-message-bubble {
        border-color: rgba(168, 85, 247, .25);
        border-bottom-right-radius: 4px;
        background: linear-gradient(135deg,
                rgba(109, 40, 217, .75),
                rgba(168, 85, 247, .65));
        color: #ffffff;
    }

    .chat-message-row:last-child {
        margin-bottom: 0;
    }

    .chat-messages {
        display: flex;
        flex-direction: column;
    }

    .chat-message-meta {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 3px;
        padding: 0 3px;
    }

    .chat-message-name {
        max-width: 155px;
        overflow: hidden;
        color: #c4b5fd;
        font-size: 9px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .chat-message-time {
        color: #5e637b;
        font-size: 8px;
    }

    .chat-message-bubble {
        padding: 8px 10px;
        border: 1px solid rgba(100, 116, 139, .12);
        border-radius: 9px;
        background: rgba(15, 23, 42, .55);
        color: #c4c7d5;
        font-size: 10px;
        line-height: 1.55;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }

    .chat-message-row.mine .chat-message-meta {
        justify-content: flex-end;
    }

    .chat-message-row.mine .chat-message-name {
        color: #d8b4fe;
    }

    .chat-message-row.mine .chat-message-bubble {
        border-color: rgba(168, 85, 247, .18);
        background: rgba(76, 29, 149, .14);
    }

    .chat-status {
        min-height: 22px;
        padding: 5px 13px 0;
        color: #686d85;
        font-size: 8px;
    }

    .chat-status.error {
        color: #fca5a5;
    }

    .chat-compose {
        padding: 10px 13px 13px;
        border-top: 1px solid rgba(100, 116, 139, .12);
        background: rgba(5, 8, 20, .25);
    }

    .chat-form {
        display: flex;
        gap: 7px;
    }

    .chat-input {
        min-width: 0;
        flex: 1;
        min-height: 36px;
        padding: 7px 10px;
        border: 1px solid rgba(100, 116, 139, .22);
        border-radius: 9px;
        outline: none;
        background: rgba(15, 23, 42, .72);
        color: #e7e7ef;
        font-family: 'Kanit', sans-serif;
        font-size: 10px;
    }

    .chat-input::placeholder {
        color: #5e637b;
    }

    .chat-input:focus {
        border-color: rgba(168, 85, 247, .45);
        box-shadow: 0 0 0 3px rgba(168, 85, 247, .07);
    }

    .chat-send {
        min-width: 52px;
        min-height: 36px;
        padding: 6px 10px;
        border: 0;
        border-radius: 9px;
        background: linear-gradient(135deg, #6d28d9, #a855f7);
        color: white;
        font-family: 'Kanit', sans-serif;
        font-size: 10px;
        font-weight: 700;
    }

    .chat-send:disabled {
        opacity: .45;
        cursor: not-allowed;
    }

    .realtime-status {
        font-size: 0.8rem;
        opacity: 0.75;
        margin-bottom: 0.5rem;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .brand {
        display: flex;
        align-items: center;
        min-width: 0;
        flex: 1;
    }

    .brand-text {
        min-width: 0;
    }

    .brand-title {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .brand-subtitle {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-shrink: 0;
    }

    #realtime-status {
        white-space: nowrap;
        flex-shrink: 0;
    }

    .realtime-status::before {
        content: "";
        display: inline-block;

        width: 8px;
        height: 8px;

        margin-right: 8px;

        border-radius: 50%;
        background: #facc15;

        transition: background-color 0.2s ease;
    }

    .realtime-status.status-connected::before {
        background: #22c55e;
    }

    .realtime-status.status-connecting::before {
        background: #facc15;
    }

    .realtime-status.status-disconnected::before {
        background: #ef4444;
    }


    /* มือถือ */
    @media (max-width: 600px) {

        .topbar {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand {
            flex: 1;
            min-width: 0;
        }

        #realtime-status {
            /* ยกเลิกตำแหน่งกลางของ desktop */
            position: static;
            transform: none;

            /* ทำเป็นจุด */
            flex: 0 0 10px;
            width: 10px;
            height: 10px;
            min-width: 10px;

            padding: 0;
            margin: 0;

            border-radius: 50%;
            font-size: 0;
            background: transparent;
            border: none;
        }

        #realtime-status::before {
            content: "";
            display: block;

            width: 10px;
            height: 10px;

            border-radius: 50%;
            background: #facc15;

            transition: background-color 0.2s ease;
        }

        /* connected */
        #realtime-status.status-connected::before {
            background: #22c55e;
        }

        /* connecting */
        #realtime-status.status-connecting::before {
            background: #facc15;
        }

        /* disconnected / unavailable / failed */
        #realtime-status.status-disconnected::before {
            background: #ef4444;
        }

        /* ปุ่มรีเฟรช */
        .topbar>div:last-child {
            flex-shrink: 0;
        }
    }

    .side-placeholder {
        min-height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        color: #5e637b;
        font-size: 10px;
        text-align: center;
    }


    .footer-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 14px;
    }

    .footer-link {
        color: #8b5cf6;
        font-size: 11px;
        text-decoration: none;
    }

    .footer-link:hover {
        color: #c084fc;
    }


    .section-card {
        margin-top: 14px;
        margin-bottom: 14px;
    }

    .action-card {
        padding: 16px;
        border: 1px solid rgba(168, 85, 247, .16);
        border-radius: 11px;
        background: rgba(15, 23, 42, .45);
    }

    .action-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
    }

    .action-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        flex: 0 0 42px;
        border: 1px solid rgba(168, 85, 247, .2);
        border-radius: 11px;
        background: rgba(124, 58, 237, .14);
        font-size: 18px;
    }

    .action-description {
        margin-top: 14px;
        margin-bottom: 14px;
        color: #8589a3;
        font-size: 14px;
        line-height: 1.6;
    }

    .form-group {
        margin-bottom: 14px;
    }

    .vote-button {
        width: 100%;
    }

    .game-alert.warning {
        border-color: rgba(245, 158, 11, .2);
        background: rgba(120, 53, 15, .1);
        color: #fcd34d;
    }

    .result-card {
        padding: 16px;
        border: 1px solid rgba(45, 212, 191, .14);
        border-radius: 11px;
        background: rgba(13, 148, 136, .06);
    }

    .result-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
    }

    .result-subtitle {
        margin-top: 3px;
        color: #6f748d;
        font-size: 10px;
    }

    .result-icon {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        flex: 0 0 40px;
        border: 1px solid rgba(45, 212, 191, .14);
        border-radius: 10px;
        background: rgba(13, 148, 136, .08);
        font-size: 17px;
    }

    .result-player {
        padding: 12px;
        border: 1px solid rgba(100, 116, 139, .12);
        border-radius: 9px;
        background: rgba(15, 23, 42, .5);
    }

    .result-name {
        color: #e8e8f2;
        font-size: 14px;
        font-weight: 800;
    }

    .result-role {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 9px;
        padding: 10px 12px;
        border-radius: 9px;
        background: rgba(45, 212, 191, .05);
    }

    .result-role-value {
        color: #99f6e4;
        font-size: 11px;
        font-weight: 700;
    }

    .footer-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

    .game-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;

        display: none;
        align-items: center;
        justify-content: center;

        padding: 20px;

        background: rgba(3, 3, 15, 0.78);
        backdrop-filter: blur(8px);
    }

    .game-modal.show {
        display: flex;
    }

    .game-modal-card {
        width: min(420px, 100%);

        padding: 30px;

        text-align: center;

        background: linear-gradient(145deg,
                rgba(30, 27, 75, 0.98),
                rgba(8, 7, 20, 0.98));

        border: 1px solid rgba(168, 85, 247, 0.35);
        border-radius: 20px;

        box-shadow:
            0 25px 80px rgba(0, 0, 0, 0.6),
            0 0 40px rgba(124, 58, 237, 0.2);

        animation: modal-pop 0.2s ease-out;
    }

    .game-modal-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.12);
        font-size: 30px;
    }

    .game-modal-title {
        margin: 0;
        color: #ffffff;
        font-size: 24px;
        font-weight: 700;
    }

    .game-modal-text {
        margin: 12px 0 24px;
        color: #a7a8b8;
        font-size: 14px;
        line-height: 1.7;
    }

    .game-modal-actions {
        display: flex;
        gap: 10px;
    }

    .btn-leave {
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(248, 113, 113, 0.6);
    }

    .btn-leave {
        background-color: rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.7rem 1.2rem;
        font-weight: 700;
    }

    .btn-leave:hover {
        background-color: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.3);
        color: #fca5a5;
    }


    .game-modal-btn {
        flex: 1;
        padding: 11px 18px;
        border: 0;
        border-radius: 10px;
        font-family: 'Kanit', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .game-modal-btn-cancel {
        border: 1px solid rgba(100, 116, 139, 0.3);
        background: rgba(15, 23, 42, 0.7);
        color: #94a3b8;
    }

    .game-modal-btn-cancel:hover {
        border-color: rgba(148, 163, 184, 0.5);
        background: rgba(30, 41, 59, 0.8);
        color: #e2e8f0;
    }

    .game-modal-btn-confirm {
        border: 1px solid rgba(239, 68, 68, 0.4);
        background: rgba(127, 29, 29, 0.7);
        color: #fecaca;
    }

    .game-modal-btn-confirm:hover {
        border-color: rgba(239, 68, 68, 0.7);
        background: rgba(153, 27, 27, 0.85);
        color: #ffffff;
    }

    .even {
        font-size: 14px;
    }

    .player-you {
        font-size: 0.85rem;
    }

    .player-you.purple {
        color: #c084fc;
    }

    .player-you.blue {
        color: #60a5fa;
    }

    @keyframes modal-pop {
        from {
            opacity: 0;
            transform: scale(0.94) translateY(8px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    @media (max-width: 500px) {
        .game-modal-card {
            padding: 24px;
        }

        .game-modal-actions {
            flex-direction: column;
        }
    }

    @media (max-width: 900px) {
        .game-layout {
            grid-template-columns: 1fr;
        }

        .side-panel {
            display: block;
        }

        .chat-messages {
            height: 280px;
        }
    }

    .game-select {
        width: 100%;
        background-color: #0b1020 !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        color: #ffffff !important;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        outline: none;
        color-scheme: dark;
    }

    .game-select:focus {
        border-color: #9333ea !important;
        box-shadow: 0 0 0 0.2rem rgba(147, 51, 234, 0.2) !important;
    }

    .game-select option {
        background-color: #111126 !important;
        color: #ffffff !important;
    }

    @media (max-width: 640px) {
        .game-page {
            width: min(100% - 18px, 1180px);
            padding-top: 15px;
        }

        .topbar {
            padding: 9px 12px;
        }

        .topbar-center {
            display: none;
        }

        .room-title {
            font-size: 21px;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .players-grid {
            grid-template-columns: 1fr;
        }

        .phase-row {
            align-items: flex-start;
            flex-direction: column;
        }

        .timer-box {
            width: 100%;
        }

        body::after {
            width: 48px;
            height: 48px;
            right: 4%;
        }
    }

    .seer-target-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 10px;
    }

    .seer-target-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 14px;
        border: 1px solid #242844;
        border-radius: 14px;
        background: #0d1022;
        color: #fff;
        cursor: pointer;
        text-align: left;
        transition: 0.2s ease;
    }

    .seer-target-card:hover {
        border-color: #8b3dff;
        transform: translateY(-1px);
    }

    .seer-target-card.selected {
        border-color: #a855f7;
        background: #21133d;
        box-shadow: 0 0 0 1px #a855f7;
    }

    .seer-target-card:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .seer-target-avatar {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: 50%;
        background: #702de0;
        color: #fff;
        font-weight: 700;
    }

    .seer-target-name {
        font-weight: 600;
    }

    .seer-target-check {
        display: none;
        margin-left: auto;
        color: #c084fc;
        font-size: 20px;
        font-weight: 700;
    }

    .seer-target-card.selected .seer-target-check {
        display: block;
    }

    @media (max-width: 600px) {
        .seer-target-list {
            grid-template-columns: 1fr;
        }
    }

    .vote-target-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .vote-target-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 14px;
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 14px;
        background: rgba(15, 23, 42, 0.75);
        color: #fff;
        text-align: left;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .vote-target-card:hover {
        border-color: #a855f7;
        background: rgba(30, 27, 75, 0.9);
        transform: translateY(-2px);
    }

    .vote-target-card.selected {
        border-color: #a855f7;
        background: rgba(88, 28, 135, 0.3);
        box-shadow: 0 0 0 1px rgba(168, 85, 247, 0.25);
    }

    .vote-target-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        height: 46px;
        flex-shrink: 0;
        border-radius: 50%;
        background: linear-gradient(135deg,
                #7c3aed,
                #a855f7);

        color: white;
        font-size: 18px;
        font-weight: 700;
    }

    .vote-target-info {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .vote-target-name {
        color: #f8fafc;
        font-size: 15px;
        font-weight: 700;
    }

    .vote-target-name small {
        color: #c084fc;
        font-size: 12px;
    }

    .vote-target-status {
        color: #94a3b8;
        font-size: 12px;
    }

    .vote-target-check {
        margin-left: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 50%;
        color: transparent;
        font-size: 13px;
    }

    .vote-target-card.selected .vote-target-check {
        border-color: #a855f7;
        background: #a855f7;
        color: white;
    }

    .vote-target-card:nth-child(odd) .vote-target-avatar {
        background:
            linear-gradient(135deg,
                #4c1d95,
                #9333ea);
    }

    .vote-target-card:nth-child(even) .vote-target-avatar {
        background: linear-gradient(135deg,
                #172554,
                #2563eb);
    }

    .vote-target-card.selected:nth-child(odd) .vote-target-avatar {
        background: linear-gradient(135deg,
                #4c1d95,
                #9333ea);
    }

    .vote-target-card.selected:nth-child(even) .vote-target-avatar {
        background: linear-gradient(135deg,
                #172554,
                #2563eb);
    }

    @media (max-width: 700px) {
        .vote-target-list {
            grid-template-columns: 1fr;
        }
    }
    .role-werewolf { color: #fca5a5; }
    .role-seer { color: #c4b5fd; }
    .role-guardian { color: #67e8f9; }
    .role-banner.role-guardian { border-color: #06b6d4; }
    .role-villager { color: #86efac; }
    .role-banner.role-werewolf { border-color: #ef4444; }
    .role-banner.role-seer { border-color: #8b5cf6; }
    .role-banner.role-villager { border-color: #22c55e; }
    .result-role-value small { color: #cbd5e1; }
    </style>


    @include('games.dashboard-style')
</head>

@php
    $viewerState = collect($game['players'])->firstWhere('player_uuid', session('player_uuid'));
@endphp
<body data-player-state="{{ ($viewerState['has_left'] ?? false) ? 'left' : (($viewerState['is_alive'] ?? true) ? 'alive' : 'dead') }}" data-game-phase="{{ $game['current_phase'] }}" data-game-stage="{{ $game['voting_stage'] }}" data-game-id="{{ $game['game_uuid'] }}" data-game-round="{{ $game['current_round'] }}">

    <div class="smoke-wrapper">
        <div class="smoke-layer-1"></div>
        <div class="smoke-layer-2"></div>
    </div>

    {{-- top navigation --}}
    <header class="topbar">

        <div class="brand">
            <div class="brand-icon">🐺</div>

            <div>
                <div class="brand-title">
                    WEREWOLF ONLINE
                </div>

                <div class="brand-subtitle">
                    มนุษย์หมาป่าออนไลน์
                </div>
            </div>
        </div>

        <div class="header-actions">

            <div id="realtime-status" class="realtime-status top-pill text-decoration-none" aria-live="polite">
                กำลังเชื่อมต่อ...
            </div>

            <div>
                <a href="{{ route('games.show', ['code' => $game['room_code']]) }}"
                    class="top-pill text-decoration-none">
                    รีเฟรช
                </a>
            </div>

                    {{-- leave game --}}
                    <div class="header-leave">

                        <form method="POST" action="{{ route('games.leave', [
                            'code' => $game['room_code'],
                                ]) }}" id="leave-game-form">
                            @csrf

                            <button type="button" id="leave-game-button" class="top-pill header-leave-button">
                                ออกจากเกม
                            </button>
                        </form>

                    </div>
        </div>

    </header>


    <main class="game-page">

        <div class="game-layout">

            {{-- main game area --}}
            <section class="main-panel">

                <div class="room-header">

                    <div class="room-header-row">

                        <div>
                            <h1 class="room-title">
                                WAREWOLF
                            </h1>

                            <div class="room-code">
                                ห้อง {{ $game['room_code'] }}
                            </div>
                        </div>

                        <div class="difficulty-badge">
                            {{ $game['difficulty'] === 'easy' ? 'EASY' : 'HARD' }}
                        </div>

                    </div>

                </div>


                <div class="content">
                    @php
                    $roleLabels = [
                    'werewolf' => 'หมาป่า',
                    'seer' => 'ผู้หยั่งรู้',
                    'guardian' => 'ผู้คุ้มกัน',
                    'villager' => 'ชาวบ้าน',
                    ];
                    @endphp
                    @include('games.match-summary')


                    {{-- errors --}}
                    @foreach ($errors->all() as $error)
                    <div class="error-message">
                        {{ $error }}
                    </div>
                    @endforeach


                    {{-- success --}}
                    @if (session('success'))
                    <div class="alert-game">
                        {{ session('success') }}
                    </div>
                    @endif


                    {{-- phase --}}
                    <div class="phase-banner">

                        <div class="phase-row">

                            <div class="phase-info">

                                <div class="phase-icon">
                                    @if ($game['current_phase'] === 'night')
                                    🌙
                                    @elseif ($game['current_phase'] === 'day_voting')
                                    🎯
                                    @else
                                    ☀️
                                    @endif
                                </div>

                                <div>

                                    <div class="phase-label">
                                        @if ($game['current_phase'] === 'night')
                                        NIGHT PHASE
                                        @elseif ($game['current_phase'] === 'day_voting')
                                        VOTING PHASE
                                        @elseif ($game['current_phase'] === 'day_discussion')
                                        DISCUSSION PHASE
                                        @else
                                        GAME STATUS
                                        @endif
                                    </div>

                                    <div class="phase-title">

                                        @if ($game['current_phase'] === 'night')
                                        ช่วงกลางคืน
                                        @elseif ($game['current_phase'] === 'day_voting')
                                        ช่วงโหวต
                                        @elseif ($game['current_phase'] === 'day_discussion')
                                        ช่วงพูดคุย
                                        @elseif ($game['status'] === 'roles_assigned')
                                        รอเริ่มเกม
                                        @elseif ($game['status'] === 'initializing')
                                        กำลังเตรียมเกม
                                        @else
                                        {{ $game['status'] }}
                                        @endif

                                    </div>

                                    @if (
                                    in_array(
                                    $game['current_phase'],
                                    ['day_discussion', 'day_voting', 'night'],
                                    true
                                    )
                                    )
                                    <div class="phase-round">
                                        รอบ {{ $game['current_round'] }}
                                    </div>
                                    @endif

                                </div>

                            </div>


                            @if ($game['phase_end_time'] !== null)
                            <div class="timer-box">

                                <div class="timer-label">
                                    TIME
                                </div>

                                <div id="phase-timer" class="timer">
                                    --:--
                                </div>

                            </div>
                            @endif

                        </div>

                        @if (count($game['event_history']) > 0)
                        <div class="latest-game-event" aria-live="polite">
                            <strong>เหตุการณ์ล่าสุด</strong>
                            <span>{{ $game['event_history'][array_key_last($game['event_history'])]['message'] }}</span>
                        </div>
                        @endif
                    @include('games.role-status')
                    </div>


<div data-dash-zone="rules">
                    {{-- game information --}}
                    <div class="info-grid">

                        <div class="info-box">
                            <div class="info-label">
                                Status
                            </div>

                            <div class="info-value">
                                @if ($game['status'] === 'roles_assigned')
                                แจกบทบาทแล้ว
                                @elseif ($game['status'] === 'initializing')
                                กำลังเตรียมเกม
                                @else
                                <!-- {{ $game['status'] }} -->
                                กำลังดำเนินเกม
                                @endif
                            </div>
                        </div>


                        <div class="info-box">
                            <div class="info-label">
                                Players
                            </div>

                            <div class="info-value">
                                {{ count($game['players']) }}
                            </div>
                        </div>


                        <div class="info-box">
                            <div class="info-label">
                                Difficulty
                            </div>

                            <div class="info-value">
                                {{ $game['difficulty'] === 'easy' ? 'ง่าย' : 'ยาก' }}
                            </div>
                        </div>

                    </div>





                    @include('games.expansion')

</div>
<div data-dash-zone="role">
                    {{-- player role --}}
                    @if ($game['my_role'] !== null)

                    <div class="role-banner role-{{ $game['my_role'] }}">

                        <div class="role-label">
                            YOUR ROLE
                        </div>

                        <div class="role-name role-{{ $game['my_role'] }}">
                            {{ $roleLabels[$game['my_role']] ?? 'ไม่ทราบบทบาท' }}
                        </div>

                    </div>

                    @endif


                    @include('games.role-guide')

                    {{-- werewolf teammates --}}
                    @if ($game['my_role'] === 'werewolf')

                    <div class="action-panel mb-3">

                        <div class="action-title">
                            หมาป่าร่วมทีม
                        </div>

                        @forelse ($game['werewolf_teammates'] as $teammate)

                        <div class="player-card mb-2">

                            <div class="player-avatar">
                                {{ mb_substr($teammate['name'], 0, 1) }}
                            </div>

                            <div class="player-details">

                                <div class="player-name">
                                    {{ $teammate['name'] }}
                                </div>

                                <div class="player-status">

                                    @if ($teammate['has_left'])
                                    ออกจากเกมแล้ว
                                    @elseif (!$teammate['is_alive'])
                                    <span style="color: red;">เสียชีวิต</span>
                                    @else
                                    มีชีวิต
                                    @endif

                                </div>

                            </div>

                        </div>

                        @empty

                        <div class="alert-game">
                            คุณเป็นหมาป่าเพียงคนเดียว
                        </div>

                        @endforelse

                    </div>

                    @endif


</div>
<div data-dash-zone="players">
                    @include('games.player-roster')

</div>
<div data-dash-zone="actions">
                    {{-- start discussion --}}
                    @if ($game['can_begin_discussion'])

                    <div class="action-area">

                        <form method="POST" action="{{ route('games.begin-discussion', [
                                    'code' => $game['room_code'],
                                ]) }}">
                            @csrf

                            <button type="submit" class="btn-game">
                                เริ่มเกม-ช่วงพูดคุย
                            </button>

                        </form>

                    </div>

                    @endif


                    @include('games.trial')

</div>
<div data-dash-zone="results">
                    {{-- vote result --}}
                    @if ($game['vote_result'] !== null)

                    <div class="section-card">

                        <div class="result-card">

                            <div class="result-header">
                                <div>
                                    <div class="result-title">
                                        ผลโหวตรอบ {{ $game['vote_result']['round'] }}
                                    </div>
                                    <div class="result-subtitle">
                                        ผลการลงคะแนนของผู้เล่นในรอบนี้
                                    </div>
                                </div>

                                <div class="result-icon">⚖️</div>
                            </div>

                            @if ($game['vote_result']['name'] !== null)

                            <div class="result-player">
                                <div class="info-label">ผู้ถูกโหวตออก</div>
                                <div class="result-name">
                                    {{ $game['vote_result']['name'] }}
                                </div>
                            </div>

                            @if (isset($game['vote_result']['role']))
                            <div class="result-role">
                                <span class="info-label mb-0">บทบาท</span>
                                <span class="result-role-value role-{{ $game['vote_result']['role'] }}">
                                    {{ $roleLabels[$game['vote_result']['role']] }}
                                </span>
                            </div>
                            @endif

                            @else

                            <div class="alert-game">
                                ไม่มีผู้ถูกโหวตออก
                            </div>

                            @endif

                        </div>

                    </div>

                    @endif



</div>
<div data-dash-zone="actions">
                    {{-- finish discussion --}}
                    @if (
                    $game['current_phase'] === 'day_discussion'
                    && $game['phase_end_time'] !== null
                    )

                    <div class="action-area">

                        <form id="finish-discussion-form" method="POST" action="{{ route('games.finish-discussion', [
                                    'code' => $game['room_code'],
                                ]) }}">

                            @csrf

                            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">

                            <button type="submit" class="btn-gamee">
                                ตรวจเวลาจบช่วงพูดคุย
                            </button>

                        </form>

                    </div>

                    @endif


                    {{-- finish voting --}}
                    @if (
                    $game['current_phase'] === 'day_voting'
                    && $game['phase_end_time'] !== null
                    )

                    <div class="action-area">

                        <form id="finish-voting-form" method="POST" action="{{ route('games.finish-voting', [
                                    'code' => $game['room_code'],
                                ]) }}">

                            @csrf

                            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">

                            <button type="submit" class="btn-gamee">
                                ตรวจเวลาจบช่วงโหวต
                            </button>

                        </form>

                    </div>

                    @endif


</div>
<div data-dash-zone="actions">
                    {{-- night --}}
                    @if ($game['current_phase'] === 'night')

                    <div class="section-card">

                        <div class="action-card">

                            <div class="action-header">
                                <div>
                                    <div class="action-title">
                                        🌙 ช่วงกลางคืน
                                    </div>
                                    <div class="phase-round">
                                        ใช้ความสามารถของบทบาทคุณในช่วงกลางคืน
                                    </div>
                                </div>

                                <div class="action-icon">🌙</div>
                            </div>

                            @if ($game['can_werewolf_act'])

                            <form method="POST" action="{{ route('games.werewolf-action', [
                                        'code' => $game['room_code'],
                                    ]) }}">

                                @csrf

                                <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">

                                <div class="mb-2">

                                    <label class="info-label">
                                        เลือกเป้าหมายคืนนี้
                                    </label>

                                    <div class="vote-target-list" id="werewolf-target-list">

                                        @foreach ($game['werewolf_targets'] as $target)

                                        <button type="button"
                                            class="vote-target-card {{ $game['my_night_target'] === $target['player_uuid'] ? 'selected' : '' }}"
                                            data-player-uuid="{{ $target['player_uuid'] }}">

                                            <span class="vote-target-avatar">
                                                {{ mb_substr($target['name'], 0, 1) }}
                                            </span>

                                            <span class="vote-target-info">

                                                <span class="vote-target-name">
                                                    {{ $target['name'] }}
                                                </span>

                                                <span class="vote-target-status">
                                                    มีชีวิต
                                                </span>

                                            </span>

                                            <span class="vote-target-check">
                                                ✓
                                            </span>

                                        </button>

                                        @endforeach

                                    </div>

                                    <button type="submit" class="btn-game">
                                        ยืนยันเป้าหมาย
                                    </button>

                                    <input type="hidden" name="target_uuid" id="werewolf-target"
                                        value="{{ $game['my_night_target'] }}" required>

                                </div>

                                @endif

                                @if ($game['my_role'] === 'seer')

                                @if ($game['can_seer_act'])

                                <form method="POST" action="{{ route('games.seer-action', [
                'code' => $game['room_code'],
            ]) }}">

                                    @csrf

                                    <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">

                                    <div class="mb-2">

                                        <label class="info-label">
                                            เลือกผู้เล่นที่คุณจะตรวจ
                                        </label>

                                        <div class="vote-target-list" id="seer-target-list">

                                            @foreach ($game['seer_targets'] as $target)

                                            @if ($target['player_uuid'] === session('player_uuid'))
                                            @continue
                                            @endif

                                            <button type="button"
                                                class="vote-target-card {{ $game['my_night_target'] === $target['player_uuid'] ? 'selected' : '' }}"
                                                data-player-uuid="{{ $target['player_uuid'] }}">

                                                <span class="vote-target-avatar">
                                                    {{ mb_substr($target['name'], 0, 1) }}
                                                </span>

                                                <span class="vote-target-info">

                                                    <span class="vote-target-name">
                                                        {{ $target['name'] }}
                                                    </span>

                                                    <span class="vote-target-status">
                                                        มีชีวิต
                                                    </span>

                                                </span>

                                                <span class="vote-target-check">
                                                    ✓
                                                </span>

                                            </button>

                                            @endforeach

                                        </div>

                                        <button type="submit" class="btn-game">
                                            ยืนยันการตรวจ
                                        </button>

                                        <input type="hidden" name="target_uuid" id="seer-target"
                                            value="{{ $game['my_night_target'] }}" required>

                                    </div>

                                </form>

                                @else

                                <div class="phase-round mt-2">
                                    🔮 คุณได้ตรวจสอบในคืนนี้แล้ว
                                </div>

                                @endif

                                @endif

                        </div>

                    </div>

                    @endif



</div>
<div data-dash-zone="results">
                    {{-- night result --}}
                    @if ($game['night_result'] !== null)

                    <div class="result-panel">

                        <div class="result-title">
                            ผลกลางคืนรอบ {{ $game['night_result']['round'] }}
                        </div>

                        @if ($game['night_result']['name'] !== null)

                        <div>
                            ผู้เสียชีวิต:
                            <strong>
                                {{ $game['night_result']['name'] }}
                            </strong>
                        </div>

                        @if (isset($game['night_result']['role']))

                        <div class="phase-round mt-1">
                            บทบาท:
                            <span class="role-{{ $game['night_result']['role'] }}">{{ $roleLabels[$game['night_result']['role']] }}</span>
                        </div>

                        @endif

                        @else

                        <div class="even">
                            คืนนี้ไม่มีผู้เสียชีวิตจากหมาป่า
                        </div>

                        @endif

                    </div>

                    @endif



                    {{-- seer results --}}
                    @if ($game['my_role'] === 'seer')

                    <div class="action-panel">

                        <div class="action-title">
                            ผลตรวจของคุณ
                        </div>

                        @forelse ($game['my_seer_results'] as $result)

                        <div class="player-card mb-2">

                            <div class="player-avatar">
                                🔎
                            </div>

                            <div class="player-details">

                                <div class="player-name">
                                    รอบ {{ $result['round'] }}
                                </div>

                                <div class="player-status">
                                    {{ $result['target_name'] }}
                                    —
                                    {{ $result['is_werewolf']
                                                ? 'เป็นหมาป่า'
                                                : 'ไม่ใช่หมาป่า' }}
                                </div>

                            </div>

                        </div>

                        @empty

                        <div class="phase-round">
                            ยังไม่มีผลตรวจ
                        </div>

                        @endforelse

                    </div>

                    @endif



</div>
<div data-dash-zone="actions">
                    {{-- finish night --}}
                    @if (
                    $game['status'] === 'in_progress'
                    && $game['current_phase'] === 'night'
                    && $game['phase_end_time'] !== null
                    )

                    <div class="action-area">

                        <form id="finish-night-form" method="POST" action="{{ route('games.finish-night', [
                                    'code' => $game['room_code'],
                                ]) }}">

                            @csrf

                            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">

                            <button type="submit" class="btn-gamee">
                                ตรวจเวลาจบกลางคืน
                            </button>

                        </form>

                    </div>

                    @endif


</div>
<div data-dash-zone="rules">
                    {{-- night event --}}
                    @if (
                    $game['current_phase'] === 'night'
                    && $game['night_event'] !== null
                    )

                    <div class="event-panel">

                        <div class="event-title">
                            Event จากคืนรอบ
                            {{ $game['night_event']['night_round'] }}
                        </div>

                        <div class="event-description">

                            <strong>
                                {{ $game['night_event']['name'] }}
                            </strong>

                            @if ($game['night_event']['id'] !== 'none')

                            <br>

                            {{ $game['night_event']['description'] }}

                            <br><br>

                            สำหรับกลางวันรอบ
                            {{ $game['night_event']['applies_to_round'] }}

                            <br><br>

                            กติกานี้จะมีผลในกลางวันถัดไป

                            @endif

                        </div>

                    </div>

                    @endif


                    {{-- day event --}}
                    @if (
                    in_array(
                    $game['current_phase'],
                    ['day_discussion', 'day_voting'],
                    true
                    )
                    && $game['day_event'] !== null
                    && $game['day_event']['id'] !== 'none'
                    )

                    <div class="event-panel">

                        <div class="event-title">
                            Event กลางวันรอบ
                            {{ $game['day_event']['round'] }}
                        </div>

                        <div class="event-description">

                            <strong>
                                {{ $game['day_event']['name'] }}
                            </strong>

                            <br>

                            @if ($game['day_event']['applied'])

                            {{ $game['day_event']['description'] }}

                            <br><br>

                            มีผลในกลางวันรอบนี้

                            @else

                            Event นี้ยังไม่เปิดใช้ —
                            รอบนี้ใช้กติกาพื้นฐาน

                            @endif

                        </div>

                    </div>

                    @endif


</div>


            </section>


            {{-- side panel --}}
            @php
            $currentPlayer = collect($game['players'])->first(
            fn ($player) => ($player['player_uuid'] ?? null) === session('player_uuid')
            );

            $canUseDeadChat =
            $currentPlayer !== null
            && !$currentPlayer['is_alive']
            && !$currentPlayer['has_left'];

            $canUseWerewolfChat =
            $game['my_role'] === 'werewolf'
            && $currentPlayer !== null
            && $currentPlayer['is_alive']
            && !$currentPlayer['has_left'];
            @endphp

            <aside class="side-panel" id="chat-panel">

                <div class="side-header">

                    <div class="side-header-row">

                        <div class="side-title">
                            <span class="online-dot"></span>
                            LIVE CHAT
                        </div>

                        <div class="side-count">
                            {{ count($game['players']) }} คน
                        </div>

                    </div>

                </div>


                <div class="chat-toolbar">
                    <div class="chat-channels">

                        <button type="button" class="chat-channel active" data-chat-channel="all">
                            แชตรวม
                        </button>

                        @if ($canUseDeadChat)
                        <button type="button" class="chat-channel dead" data-chat-channel="dead">ผู้เสียชีวิต</button>
                        @endif

                        @if ($canUseWerewolfChat)
                        <button type="button" class="chat-channel werewolf" data-chat-channel="werewolf">
                            หมาป่า
                        </button>
                        @endif

                    </div>

                    <button type="button" id="chat-refresh" class="chat-refresh">
                        ↻
                    </button>
                </div>

                <div id="chat-messages" class="chat-messages" aria-live="polite">

                    <div class="chat-empty">
                        กำลังโหลดข้อความ...
                    </div>

                </div>


                <div id="chat-feedback" class="chat-status" aria-live="polite"></div>


                <div class="chat-compose">

                    <form id="chat-form" class="chat-form">

                        <input id="chat-message" class="chat-input" type="text" name="message" maxlength="500"
                            autocomplete="off" placeholder="พิมพ์ข้อความ...">

                        <button id="chat-submit" class="chat-send" type="submit">
                            ส่ง
                        </button>

                    </form>

                </div>

            </aside>

        </div>

    </main>

    <div id="leave-game-modal" class="game-modal">
        <div class="game-modal-card">

            <div class="game-modal-icon">
                ⚠️
            </div>

            <h3 class="game-modal-title">
                ออกจากเกม?
            </h3>

            <p class="game-modal-text">
                หากออกจากเกมนี้ คุณจะไม่สามารถกลับเข้าห้องเดิมได้
            </p>

            <div class="game-modal-actions">
                <button type="button" id="leave-game-cancel" class="game-modal-btn game-modal-btn-cancel">
                    ยกเลิก
                </button>

                <button type="button" id="leave-game-confirm" class="game-modal-btn game-modal-btn-confirm">
                    ออกจากเกม
                </button>
            </div>

        </div>
    </div>

    <button type="button" id="evidence-toggle" class="evidence-floating-toggle" aria-controls="evidence-panel" aria-expanded="false">🔎 หลักฐาน ({{ count($game['evidence']) }})</button>
    <button type="button" id="chat-toggle" class="chat-floating-toggle" aria-controls="chat-panel" aria-expanded="false">แชท <span id="chat-unread"></span></button>
    <button type="button" id="sound-toggle" class="sound-floating-toggle" aria-pressed="false">เปิดเสียงแจ้งเตือน</button>
    <button type="button" id="game-notice" class="game-toast" hidden aria-live="polite"></button>
    <style>
    body[data-game-phase="day_discussion"] .phase-banner {border:2px solid #fbbf24;background:linear-gradient(120deg,#493414,#1b2138)}
    body[data-game-phase="day_voting"] .phase-banner {border:2px solid #f97316;background:linear-gradient(120deg,#522712,#1b2138)}
    body[data-game-phase="night"] .phase-banner {border:2px solid #818cf8;background:linear-gradient(120deg,#222052,#101828)}
    body[data-game-stage="defense"] .phase-banner {border-color:#f43f5e}
    body[data-game-stage="verdict"] .phase-banner {border-color:#06b6d4}
    #chat-panel {position:fixed;right:18px;bottom:80px;width:min(390px,calc(100vw - 36px));max-height:75vh;overflow:auto;z-index:1000;box-shadow:0 12px 50px #0009;background:#111827}
    #chat-panel[hidden] {display:none!important}
    .chat-floating-toggle,.sound-floating-toggle {position:fixed;bottom:20px;right:18px;z-index:1001;border:1px solid #8b5cf6;border-radius:24px;background:#312e81;color:white;padding:12px 18px;cursor:pointer}
    .sound-floating-toggle {right:120px;font-size:12px}
    .chat-floating-toggle.unread,[data-chat-channel].unread {background:#b91c1c;color:white;border-color:#f87171}
    .game-toast {position:fixed;top:24px;left:50%;transform:translateX(-50%);z-index:1100;max-width:min(500px,90vw);padding:16px 24px;background:#1e293b;color:white;border:2px solid #fbbf24;border-radius:14px;box-shadow:0 8px 40px #0008;cursor:pointer}
    .game-toast[hidden] {display:none}
    </style>

    {{-- popup --}}
    <script>
    const leaveGameForm = document.getElementById('leave-game-form');
    const leaveGameButton = document.getElementById('leave-game-button');

    const leaveGameModal = document.getElementById('leave-game-modal');
    const leaveGameCancel = document.getElementById('leave-game-cancel');
    const leaveGameConfirm = document.getElementById('leave-game-confirm');

    leaveGameButton.addEventListener('click', () => {
        leaveGameModal.classList.add('show');
    });

    leaveGameCancel.addEventListener('click', () => {
        leaveGameModal.classList.remove('show');
    });

    leaveGameConfirm.addEventListener('click', () => {
        leaveGameForm.submit();
    });

    leaveGameModal.addEventListener('click', (event) => {
        if (event.target === leaveGameModal) {
            leaveGameModal.classList.remove('show');
        }
    });
    </script>

    {{-- timer --}}
    @if ($game['phase_end_time'] !== null)

    <script>
    const timerElement =
        document.getElementById('phase-timer');

    if (timerElement) {

        const endTime = Date.parse(
            @json($game['phase_end_time'])
        );

        const serverTime = Date.parse(
            @json($game['server_time'])
        );

        const loadedAt = performance.now();

        let timerInterval;


        function updateTimer() {

            const elapsed =
                performance.now() - loadedAt;

            const estimatedServerTime =
                serverTime + elapsed;

            const secondsLeft =
                Math.max(
                    0,
                    Math.ceil(
                        (endTime - estimatedServerTime) / 1000
                    )
                );


            if (secondsLeft === 0) {

                clearInterval(timerInterval);

                const form =
                    document.getElementById(
                        'finish-discussion-form'
                    ) ??
                    document.getElementById(
                        'finish-voting-form'
                    ) ??
                    document.getElementById(
                        'finish-night-form'
                    );


                if (form) {

                    timerElement.textContent =
                        'หมดเวลา';

                    form.requestSubmit();

                } else {

                    timerElement.textContent =
                        'หมดเวลา';

                }

                return;
            }


            const minutes =
                Math.floor(secondsLeft / 60);

            const seconds =
                String(
                    secondsLeft % 60
                ).padStart(2, '0');


            timerElement.textContent =
                `${minutes}:${seconds}`;
        }


        timerInterval =
            setInterval(
                updateTimer,
                250
            );

        updateTimer();
    }
    </script>

    @endif

    {{-- seer action --}}
    <script>
    document.addEventListener('DOMContentLoaded', () => {

        const targetInput = document.getElementById('seer-target');
        const targetList = document.getElementById('seer-target-list');

        if (!targetInput || !targetList) {
            return;
        }

        const cards = targetList.querySelectorAll('.vote-target-card');

        cards.forEach((card) => {

            card.addEventListener('click', () => {

                // เก็บคนที่เลือก
                targetInput.value = card.dataset.playerUuid;

                // เอา selected ออกจากทุกการ์ด
                cards.forEach((item) => {
                    item.classList.remove('selected');
                });

                // เพิ่ม selected ให้การ์ดที่กด
                card.classList.add('selected');
            });

        });

    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        const voteTargetList = document.getElementById('vote-target-list');
        const voteTargetInput = document.getElementById('vote-target');

        if (!voteTargetList || !voteTargetInput) {
            return;
        }

        const cards = voteTargetList.querySelectorAll('.vote-target-card');

        cards.forEach(card => {

            card.addEventListener('click', function() {

                // ยกเลิกคนที่เลือกก่อนหน้า
                cards.forEach(item => {
                    item.classList.remove('selected');
                });

                // เลือกคนนี้
                this.classList.add('selected');

                // ส่ง UUID เข้า hidden input
                voteTargetInput.value = this.dataset.playerUuid;

            });

        });

    });
    </script>


    <script>
    document.addEventListener('DOMContentLoaded', function() {

        const voteTargetList = document.getElementById('vote-target-list');
        const voteTargetInput = document.getElementById('vote-target');

        if (!voteTargetList || !voteTargetInput) {
            return;
        }

        const cards = voteTargetList.querySelectorAll('.vote-target-card');

        cards.forEach(card => {

            card.addEventListener('click', function() {

                cards.forEach(item => {
                    item.classList.remove('selected');
                });

                this.classList.add('selected');

                voteTargetInput.value = this.dataset.playerUuid;

            });

        });

    });
    </script>


    <script>
    const werewolfTargetList = document.getElementById('werewolf-target-list');
    const werewolfTargetInput = document.getElementById('werewolf-target');

    if (werewolfTargetList && werewolfTargetInput) {

        const cards = werewolfTargetList.querySelectorAll('.vote-target-card');

        cards.forEach(card => {

            card.addEventListener('click', function() {

                cards.forEach(item => {
                    item.classList.remove('selected');
                });

                this.classList.add('selected');

                werewolfTargetInput.value = this.dataset.playerUuid;

            });

        });
    }
    </script>

    @include('games.death-effect')
</body>

</html>
