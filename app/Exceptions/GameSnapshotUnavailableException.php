<?php

namespace App\Exceptions;

use RuntimeException;

class GameSnapshotUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $roomCode
    ) {
        parent::__construct('ข้อมูลเกมของห้องนี้ไม่พร้อมใช้งาน');
    }
}