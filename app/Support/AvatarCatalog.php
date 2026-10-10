<?php

namespace App\Support;

class AvatarCatalog
{
    public const CHARACTERS = ['bunny' => 'กระต่าย', 'cat' => 'แมว', 'panda' => 'แพนด้า', 'bear' => 'หมี', 'frog' => 'กบ', 'chick' => 'ลูกเจี๊ยบ'];

    public const FACES = ['smile' => 'ยิ้ม', 'wink' => 'ขยิบตา', 'calm' => 'หน้านิ่ง'];

    public const ACCESSORIES = ['none' => 'ไม่ใส่', 'bow' => 'โบ', 'crown' => 'มงกุฎ', 'glasses' => 'แว่น', 'flower' => 'ดอกไม้'];

    public const COLORS = ['lavender' => '#ddd6fe', 'mint' => '#a7f3d0', 'peach' => '#fed7aa', 'sky' => '#bae6fd'];

    public static function normalize(?array $avatar): array
    {
        $defaults = ['character' => 'bunny', 'face' => 'smile', 'accessory' => 'none', 'color' => 'lavender'];
        foreach (['character' => self::CHARACTERS, 'face' => self::FACES, 'accessory' => self::ACCESSORIES, 'color' => self::COLORS] as $key => $choices) {
            if (is_string($avatar[$key] ?? null) && isset($choices[$avatar[$key]])) {
                $defaults[$key] = $avatar[$key];
            }
        }

        return $defaults;
    }
}
