<?php
if (!defined('ENCRYPTION_KEY')) {
    $hexKey = $_ENV['ENCRYPTION_KEY_HEX'] ?? null;

    if (!$hexKey || strlen($hexKey) !== 64) {
        throw new RuntimeException('ENCRYPTION_KEY_HEX missing or invalid in .env (must be 64 hex chars = 32 bytes)');
    }

    define('ENCRYPTION_KEY', hex2bin($hexKey));
}