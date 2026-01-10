<?php

/**
 * 汎用関数をまとめたファイル
 * @since 2026-01-10
 * @author space
 */

/**
 * htmlspecialchars関数のラッパー関数
 * @param string|null $str エスケープ対象文字列
 * @return string エスケープ後の文字列
 */
function h(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
