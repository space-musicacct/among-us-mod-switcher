<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/AmongUsSwitcher/Core.php';
require_once __DIR__ . '/../../src/Security/CsrfToken.php';
require_once __DIR__ . '/../../functions.php';

// localhost (127.0.0.1) 以外は拒否
SecurityGuards::requireLocalhost($_SERVER);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ./../");
    exit;
}

// ====== トークン認証 ======
if (!CsrfToken::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Bad Request: CSRF token invalid');
}

$switcher = AmongUsSwitcher::create(__DIR__ . '/../../config.php');
$c = $switcher->config;

if (!hash_equals($c->appToken(), (string)($_POST['app_token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden: token mismatch');
}
// ====== トークン認証おわり ======

$target = ProfileId::sanitize((string)($_POST['target'] ?? ''));

if ($switcher->proc->isAmongUsRunning()) {
    http_response_code(409);
    exit('Among Us is running. Close the game and retry.');
}

$activeDir  = Path::join($c->steamCommonDir(), $c->gameDirName());
$activeYaml = Path::join($activeDir, $c->yamlName());

if (!is_dir($activeDir) || !is_file($activeYaml)) {
    http_response_code(500);
    exit('Active folder or id.yaml not found.');
}

$curId = $switcher->yaml->extract($activeYaml);
if (strcasecmp($curId, $target) === 0) {
    header('Location: ../index.php');
    exit;
}

// ターゲット(非アクティブ側)
$targetInactive = Path::join($c->steamCommonDir(), $c->gameDirName() . ' - ' . $target);
$targetYaml     = Path::join($targetInactive, $c->yamlName());

if (!is_dir($targetInactive) || !is_file($targetYaml)) {
    http_response_code(404);
    exit('Target inactive folder not found.');
}

$targetYamlId = $switcher->yaml->extract($targetYaml);
if (strcasecmp($targetYamlId, $target) !== 0) {
    http_response_code(500);
    exit('Target id.yaml profile.id mismatch.');
}

// 現在の非アクティブ化先
$curInactive = Path::join($c->steamCommonDir(), $c->gameDirName() . ' - ' . $curId);
if (is_dir($curInactive)) {
    http_response_code(500);
    exit('Current inactive destination already exists. Cleanup needed.');
}

// 一時ディレクトリ
$tmpDir = Path::join($c->steamCommonDir(), $c->gameDirName() . '.__SWITCHING__');
if (is_dir($tmpDir)) {
    http_response_code(500);
    exit('Temp folder exists (previous failed switch?)');
}

// Steam buildid
$steamappsDir = dirname($c->steamCommonDir());
$appmanifest  = Path::join($steamappsDir, "appmanifest_{$c->steamAppId()}.acf");
$buildid      = $switcher->steam->buildId($appmanifest);

try {
    $record = [
        'ts' => $switcher->clock->nowIsoJst(),
        'action' => 'switch',
        'from' => $curId,
        'to' => $target,
        'steam_app_id' => $c->steamAppId(),
        'steam_buildid' => $buildid,
        'result' => 'pending',
    ];
} catch (DateMalformedStringException $e) {
    exit('Can not recognize date malformed.');
}

$stage = 0;

try {
    // ====== 段階別切替 ======
    $switcher->fs->rename($activeDir, $tmpDir);
    $stage = 1;

    $switcher->fs->rename($targetInactive, $activeDir);
    $stage = 2;

    $switcher->fs->rename($tmpDir, $curInactive);
    $stage = 3;

    // 最終確認
    $afterId = $switcher->yaml->extract(Path::join($activeDir, $c->yamlName()));
    if (strcasecmp($afterId, $target) !== 0) {
        throw new RuntimeException("After switch active id mismatch: $afterId");
    }

    $record['result'] = 'ok';
    $switcher->log->append($record);

    header('Location: ../index.php?switch=success');
    exit;

} catch (Throwable $e) {
    $record['result'] = 'error';
    $record['error'] = $e->getMessage();
    try {
        $switcher->log->append($record);
    } catch (DateMalformedStringException) {
        error_log($e->getMessage());
    }

    // 段階別ロールバック
    if ($stage >= 1 && is_dir($tmpDir)) {
        if ($stage === 1) {
            // まだactiveが消えてるだけ → tmpを戻す
            @rename($tmpDir, $activeDir);
        } elseif ($stage === 2) {
            // activeがtargetの状態 → targetを戻して、tmpをactiveに
            @rename($activeDir, $targetInactive);
            @rename($tmpDir, $activeDir);
        }
    }

    http_response_code(500);
    echo "Switch failed: " . h($e->getMessage());
}