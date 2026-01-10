<?php

use Random\RandomException;

/**
 * CSRFトークンの生成と検証を行うクラス
 *
 * セッション($_SESSION)を利用するため、このクラスを使用する前に
 * 必ず session_start() が実行されている必要がある。
 *
 * 検証が成功したトークンは破棄（ワンタイム）する。
 *
 * @since 2025-11-25
 * @author space
 */
final class CsrfToken
{
    /** @var string セッションキー名 */
    private const string SESSION_KEY = 'csrf_token';

    /**
     * CSRFトークンを生成・取得する。
     *
     * セッションにトークンがなければ生成し、あればそれを返す。
     *
     * @return string トークン文字列
     * @throws RuntimeException|RandomException セッションが開始していない場合
     */
    public static function generate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            throw new RuntimeException(
                'Session not started. Call session_start() before using CsrfToken.'
            );
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            // 32バイトのランダム値 → 64文字hex
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION[self::SESSION_KEY];
    }

    /**
     * トークンを検証する。
     *
     * 検証が成功した場合はトークンは破棄される (ワンタイムトークン)。
     *
     * @param string|null $token 送信されたトークン
     * @return bool 検証成功ならtrue
     */
    public static function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($stored) || $stored === '' || $token === null || $token === '') {
            return false;
        }

        if (!hash_equals($stored, $token)) {
            return false;
        }

        unset($_SESSION[self::SESSION_KEY]);
        return true;
    }
}
