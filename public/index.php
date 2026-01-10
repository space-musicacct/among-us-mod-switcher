<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/AmongUsSwitcher/Core.php';
require_once __DIR__ . '/../src/Security/CsrfToken.php';
require_once __DIR__ . '/../functions.php';

// localhost (127.0.0.1) 以外は拒否
SecurityGuards::requireLocalhost($_SERVER);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

$switcher = AmongUsSwitcher::create(__DIR__ . '/../config.php');
$c = $switcher->config;

$activeDir  = Path::join($c->steamCommonDir(), $c->gameDirName());
$activeYaml = Path::join($activeDir, $c->yamlName());

$currentId = 'UNKNOWN';
$error = null;

try {
    if (!is_dir($activeDir)) {
        throw new RuntimeException("Active dir not found: $activeDir");
    }
    if (!is_file($activeYaml)) {
        throw new RuntimeException("Active id.yaml not found: $activeYaml");
    }
    $currentId = $switcher->yaml->extract($activeYaml);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// buildid
$steamappsDir = dirname($c->steamCommonDir()); // .../steamapps/common -> .../steamapps
$appmanifest  = Path::join($steamappsDir, "appmanifest_{$c->steamAppId()}.acf");
$buildid      = $switcher->steam->buildId($appmanifest);

// 非アクティブ
$profiles = [];
try {
    $profiles = $switcher->lister->list($c, $switcher->yaml);
} catch (Throwable $e) {
    $error = $error ? ($error . " / " . $e->getMessage()) : $e->getMessage();
}

// ログ
$logPath = $switcher->log->logPath();
$lastResult = null;

$lines = [];
if (is_file($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
    if ($lines) {
        $lastLine = trim((string)end($lines));
        $json = json_decode($lastLine, true);
        if (is_array($json)) {
            $lastResult = $json;
        }
    }
}

try {
    $csrfToken = CsrfToken::generate();
} catch (Throwable) {
    die("Can not generate csrf token.");
}

$appToken = $c->appToken();
$running = $switcher->proc->isAmongUsRunning();
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>Among Us Mod/Vanilla切替ツール</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
    <h1 class="header-logo"><a href="">Among Us Mod/Vanilla切替ツール</a></h1>
</header>
<main class="main-content">
    <div class="container">
        <section id="amomg-us-switcher" class="card">
            <?php if ($error): ?>
                <div class="error">ERROR: <?= h($error) ?></div>
            <?php endif; ?>

            <h3>現在のAmong Usの状態</h3>
            <div class="meta">現在使用しているAmong Us：<b><?= h($currentId) ?></b></div>
            <div class="meta">Steamのbuildid：<b class="<?= isset($buildid) ? 'success' : 'error'?>"><?= h($buildid ?? '(not found)') ?></b></div>
            <div class="meta">Among Usは起動中か？：<b class="<?= $running ? 'error' : 'success'?>"><?= $running ? 'YES' : 'NO' ?></b></div>

            <?php if (is_array($lastResult) && !empty($lastResult['result'])): ?>
                <div class="meta">
                    最後の切替：
                    <?php if (($lastResult['result'] ?? '') === 'ok'): ?>
                        <span class="success"><b>成功</b></span>
                    <?php else: ?>
                        <span class="error"><b>失敗</b></span>
                    <?php endif; ?>
                    —
                    <?= h(($lastResult['from'] ?? '?') . " -> " . ($lastResult['to'] ?? '?')) ?>
                    @ <?= h((string)($lastResult['ts'] ?? '')) ?>
                </div>
            <?php endif; ?>

            <h3>Among Usの切替</h3>

            <?php if (!$profiles): ?>
                <div class="error">
                    inactive候補が見つかりません。<br>
                    <code><?= h($c->steamCommonDir()) ?></code> 配下に
                    <code><?= h($c->gameDirName()) ?> - &lt;ID&gt;</code> フォルダと
                    <code><?= h($c->yamlName()) ?></code> を用意してください。
                </div>
            <?php elseif ($running): ?>
                <div class="error">Among Usを終了してからこのページを再読み込みしてください。</div>
            <?php else: ?>
                <form class="row" method="post" action="switch/index.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="app_token" value="<?= h($appToken) ?>">

                    <label>
                        <select name="target" required>
                            <?php foreach ($profiles as $id => $info): ?>
                                <option value="<?= h($id) ?>"
                                    <?= (strcasecmp($id, $currentId) === 0) ? 'disabled' : '' ?>>
                                    <?= h($id) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit">切替</button>
                </form>

                <div class="meta" style="margin-top:10px;">
                    ※ 現在のAmong Usは選べないようになっています。
                </div>
            <?php endif; ?>

            <h3>非アクティブ状態のAmong Us</h3>
            <pre><?php
                if (!$profiles) {
                    echo "非アクティブ状態のAmong Usが見つかりませんでした。";
                } else {
                    foreach ($profiles as $id => $info) {
                        echo h("$id ({$info['folder']})") . "\n";
                    }
                }
                ?></pre>

            <h3>ログ (最新10件)</h3>
            <pre><?php
                if ($lines) {
                    $tail = array_slice($lines, -10);
                    $tail = array_reverse($tail);

                    foreach ($tail as $line) {
                        $json = json_decode(trim($line), true);
                        if (!is_array($json) || !isset($json['ts'])) continue;

                        $status = ($json['result'] ?? '') === 'ok' ? '成功' : '失敗';
                        $class  = ($json['result'] ?? '') === 'ok' ? 'success' : 'error';

                        echo '<span class="meta">' . h((string)$json['ts']) . '</span> ';
                        echo '<span class="' . h($class) . '">[' . h($status) . ']</span> ';
                        echo h(($json['from'] ?? '?') . " → " . ($json['to'] ?? '?'));

                        if (isset($json['error']))   echo ': ' . h((string)$json['error']);
                        if (isset($json['message'])) echo ': ' . h((string)$json['message']);
                        echo "\n";
                    }
                } else {
                    echo "ログはまだありません。";
                }
                ?></pre>

            <p class="meta">Logの保存先：<?= h($logPath) ?></p>
        </section>
    </div>
</main>

<footer class="site-footer">
    <p class="footer-content">
        <small>
            &copy; <?= date('Y') ?> space
        </small>
    </p>
</footer>

</body>
</html>
