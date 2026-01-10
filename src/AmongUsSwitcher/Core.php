<?php

declare(strict_types=1);

/**
 * config.php の設定を読み込むクラス
 * 
 * ただし、config.php は、設定データが格納された配列を返す前提とする。
 *
 * @author space
 * @since 2026-01-10
 */
final class Config
{
    /** @var array<string, mixed> 設定データ */
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * config.php を1回だけ読み込んで Config を生成する
     *
     * @param string $configPath config.php のパス
     * @return self
     */
    public static function fromFile(string $configPath): self
    {
        if (!is_file($configPath)) {
            throw new RuntimeException("config.php not found: $configPath");
        }
        /** @var array<string, mixed> $data */
        $data = require $configPath;
        if (!is_array($data)) {
            throw new RuntimeException("config.php must return array: $configPath");
        }
        return new self($data);
    }

    /**
     * 全設定データを取得する
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 指定キーの設定値を取得する
     * @param string $key
     * @return mixed
     * @throws RuntimeException キーが存在しない場合
     */
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw new RuntimeException("config key missing: $key");
        }
        return $this->data[$key];
    }


    /**
     * Steamの common フォルダ (Among Us が入ってる場所) を取得する
     * @return string
     */
    public function steamCommonDir(): string
    {
        return (string)$this->get('steam_common_dir');
    }

    /**
     * ゲームフォルダ名を取得する
     * @return string
     */
    public function gameDirName(): string
    {
        return (string)$this->get('game_dir_name');
    }


    /**
     * id.yaml のファイル名を取得する
     * @return string
     */
    public function yamlName(): string
    {
        return (string)$this->get('yaml_name');
    }

    /**
     * Steam AppID (Among Us) を取得する
     * @return string
     */
    public function steamAppId(): string
    {
        return (string)$this->get('steam_app_id');
    }

    /**
     * (Among Us フォルダ配下に作る) ログの相対パスを取得する
     * @return string
     */
    public function logRelPath(): string
    {
        return (string)$this->get('log_relpath');
    }

    /**
     * appトークンを取得する
     * @return string
     */
    public function appToken(): string
    {
        return (string)$this->get('app_token');
    }
}


/**
 * パス結合を行うユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class Path
{

    /**
     * OS依存のディレクトリセパレータでパスを結合する
     * @param string ...$parts 結合するパスの各部分
     * @return string 結合されたパス
     */
    public static function join(string ...$parts): string
    {
        $p = array_shift($parts) ?? '';
        foreach ($parts as $part) {
            $p = rtrim($p, '\\/') . DIRECTORY_SEPARATOR . ltrim($part, '\\/');
        }
        return $p;
    }
}


/**
 * ローカルホスト限定チェック等のセキュリティガードを行うためのユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class SecurityGuards
{

    /**
     * 127.0.0.1以外からのアクセスを拒否する
     * @param array $server $_SERVER相当
     * @return void
     */
    public static function requireLocalhost(array $server): void
    {
        $ip = (string)($server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            http_response_code(403);
            exit("Forbidden: localhost only");
        }
    }
}



/**
 * ID (フォルダ名に入る識別子) の検証・正規化を行うユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class ProfileId
{
    /**
     * IDをトリムし、Windowsで禁止される文字を排除する
     *
     * @param string $id 入力ID
     * @return string 検証済みID
     */
    public static function sanitize(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('ID is empty');
        }
        if (preg_match('/[\/:*?"<>|]/', $id)) {
            throw new RuntimeException('ID contains invalid filename characters');
        }
        return $id;
    }
}

/**
 * ファイル読み込み・ディレクトリ作成を行うユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class FileIO
{
    /**
     * テキストファイルを読み込む
     * @param string $path ファイルパス
     * @return string ファイル内容
     * @throws RuntimeException 読み込み失敗時
     */
    public static function readText(string $path): string
    {
        $s = @file_get_contents($path);
        if ($s === false) {
            throw new RuntimeException("Failed to read: $path");
        }
        return $s;
    }

    /**
     * 指定されたディレクトリが無ければそのディレクトリを作成する
     * なお、失敗しても例外にしない。
     *
     * @param string $dir ディレクトリパス
     * @return void
     */
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

/**
 * YAMLから profile.id を抽出するためのクラス
 * @since 2026-01-10
 * @author space
 */
final class YamlProfileIdExtractor
{
    /**
     * YAMLから profile.id を抽出する
     * @param string $yamlPath id.yaml のパス
     * @return string 検証済み profile.id
     */
    public function extract(string $yamlPath): string
    {
        $txt = FileIO::readText($yamlPath);

        // profile: の後にある id: を探す
        $posProfile = strpos($txt, 'profile:');
        if ($posProfile !== false) {
            $sub = substr($txt, $posProfile);
            if (preg_match('/^\s*id\s*:\s*("?)([^\r\n"#]+)\1\s*$/m', $sub, $m)) {
                return ProfileId::sanitize(trim($m[2]));
            }
        }

        // フォールバック
        if (preg_match('/^\s*id\s*:\s*("?)([^\r\n"#]+)\1\s*$/m', $txt, $m)) {
            return ProfileId::sanitize(trim($m[2]));
        }

        throw new RuntimeException("profile.id not found in $yamlPath");
    }
}

/**
 * Steam appmanifest_*.acf から buildid を抽出するユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class SteamManifest
{
    /**
     * appmanifest_*.acf から buildid を抽出する
     * @param string $acfPath appmanifest_*.acf のパス
     * @return string|null buildid（無ければnull）
     */
    public function buildId(string $acfPath): ?string
    {
        if (!is_file($acfPath)) {
            return null;
        }
        $txt = @file_get_contents($acfPath);
        if ($txt === false) {
            return null;
        }
        if (preg_match('/"buildid"\s*"(\d+)"/', $txt, $m)) {
            return $m[1];
        }
        return null;
    }
}

/**
 * プロセス実行状態の検出を行うユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class WindowsProcess
{
    /**
     * Among Us が起動中かを判定する (tasklist で検出)。
     * @return bool Among Us が起動中であれば true, そうでなければ false
     */
    public function isAmongUsRunning(): bool
    {
        $out = @shell_exec('tasklist');
        if (!is_string($out)) {
            return false;
        }
        return stripos($out, 'Among Us.exe') !== false;
    }
}

/**
 * 時刻に関するユーティリティクラス
 * @since 2026-01-10
 * @author space
 */
final class Clock
{
    /**
     * JSTのISO8601文字列を返す。
     *
     * @return string
     * @throws DateMalformedStringException 日付の生成に失敗した場合
     */
    public function nowIsoJst(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
        return $dt->format('c');
    }
}

/**
 * JSONL形式で log/switch.log に追記するロガー。
 * @since 2026-01-10
 * @author space
 */
final class SwitchLogger
{
    private Config $config;
    private Clock $clock;

    public function __construct(Config $config, Clock $clock)
    {
        $this->config = $config;
        $this->clock = $clock;
    }

    /**
     * アクティブな Among Us フォルダ配下のログパスを返す。
     *
     * @return string
     */
    public function logPath(): string
    {
        $active = Path::join($this->config->steamCommonDir(), $this->config->gameDirName());
        return Path::join($active, $this->config->logRelPath());
    }

    /**
     * レコードをJSONLで追記する (失敗しても投げない)。
     *
     * @param array<string, mixed> $record ログレコード
     * @return void
     * @throws DateMalformedStringException 日付の生成に失敗した場合
     */
    public function append(array $record): void
    {
        $record['ts'] = $record['ts'] ?? $this->clock->nowIsoJst();

        $logPath = $this->logPath();
        FileIO::ensureDir(dirname($logPath));

        $line = json_encode($record, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logPath, $line, FILE_APPEND);
    }
}

/**
 * ファイル/フォルダ名の変更を行うクラス
 * @since 2026-01-10
 * @author space
 */
final class Fs
{
    /**
     * ファイル/フォルダ名を変更する
     * @param string $from 変更前パス
     * @param string $to 変更後パス
     * @return void
     */
    public function rename(string $from, string $to): void
    {
        if (!@rename($from, $to)) {
            throw new RuntimeException("rename failed: $from -> $to");
        }
    }
}


/**
 * 非アクティブ状態の Among Us（Among Us - <ID>）を列挙するクラス
 *
 * @since 2026-01-10
 * @author space
 */
final class InactiveProfileLister
{
    /**
     * 非アクティブ状態の Among Us 一覧を取得する。
     *
     * - <steam_common_dir> 直下の「<game_dir_name> - <ID>」フォルダを走査
     * - id.yaml の profile.id を読み取り、フォルダ名末尾と一致するものだけ採用
     *
     * @param Config $config 設定オブジェクト
     * @param YamlProfileIdExtractor $yaml YamlProfileIdExtractor オブジェクト
     * @return array<string, array{dir:string,yaml:string,folder:string}> - profile.id をキーとした連想配列
     */
    public function list(Config $config, YamlProfileIdExtractor $yaml): array
    {
        $base = $config->steamCommonDir();
        $game = $config->gameDirName();
        $yamlName = $config->yamlName();
        $prefix = $game . ' - ';

        if (!is_dir($base)) {
            throw new RuntimeException("steam_common_dir not found: $base");
        }

        /** @var array<string, array{dir:string,yaml:string,folder:string}> $profiles */
        $profiles = [];

        $it = new DirectoryIterator($base);
        foreach ($it as $f) {
            if ($f->isDot() || !$f->isDir()) {
                continue;
            }

            $name = $f->getFilename();
            if (!str_starts_with($name, $prefix)) {
                continue;
            }

            $dirPath = $f->getPathname();
            $yamlPath = Path::join($dirPath, $yamlName);
            if (!is_file($yamlPath)) {
                continue;
            }

            try {
                $id = $yaml->extract($yamlPath);

                // フォルダ名と一致しているものだけ採用
                $expectedName = $prefix . $id;
                if (strcasecmp($name, $expectedName) !== 0) {
                    continue;
                }

                $profiles[$id] = [
                    'dir' => $dirPath,
                    'yaml' => $yamlPath,
                    'folder' => $name,
                ];
            } catch (Throwable) {
                // 壊れてるものは無視
                continue;
            }
        }

        ksort($profiles, SORT_NATURAL | SORT_FLAG_CASE);
        return $profiles;
    }
}

/**
 * Among Us Mod Switcher のコアコンポーネント群をまとめたクラス
 * @since 2026-01-10
 * @author space
 */
final class AmongUsSwitcher
{
    public Config $config;
    public YamlProfileIdExtractor $yaml;
    public SteamManifest $steam;
    public WindowsProcess $proc;
    public SwitchLogger $log;
    public Fs $fs;
    public Clock $clock;
    public InactiveProfileLister $lister;

    private function __construct() {}

    /**
     * ファクトリーメソッド
     * @param string $configPath config.php のパス
     * @return self
     */
    public static function create(string $configPath): self
    {
        $self = new self();
        $self->config = Config::fromFile($configPath);
        $self->clock  = new Clock();
        $self->yaml   = new YamlProfileIdExtractor();
        $self->steam  = new SteamManifest();
        $self->proc   = new WindowsProcess();
        $self->fs     = new Fs();
        $self->log    = new SwitchLogger($self->config, $self->clock);
        $self->lister = new InactiveProfileLister();
        return $self;
    }
}
