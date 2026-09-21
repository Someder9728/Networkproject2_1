<?php

namespace App\GameLogic;

class PlayerSession
{
    // จัดการเมื่อผู้เล่นหลุดการเชื่อมต่อ
    public static function handleDisconnect(array &$playerData, int $timeoutSeconds = 60): bool
    {
        $playerData['is_connected'] = false;
        $playerData['disconnected_at'] = time();
        
        return true;
    }

    // ตรวจสอบว่าหมดเวลา Reconnect หรือยัง (ถ้าเกิน 60 วิ ให้ปรับเป็นตาย)
    public static function checkDisconnectTimeout(array &$playerData, int $timeoutSeconds = 60): bool
    {
        if (! $playerData['is_connected'] && $playerData['is_alive']) {
            $timePassed = time() - ($playerData['disconnected_at'] ?? time());
            if ($timePassed >= $timeoutSeconds) {
                $playerData['is_alive'] = false; // นับว่าตายทันที
                return true; // หมดเวลา
            }
        }
        return false;
            }
}