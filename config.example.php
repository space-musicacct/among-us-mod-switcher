<?php
// TODO: config.example.phpをconfig.phpにリネームして使用してください。
// TODO: 必要に応じてreturnの中身を書きかえてください。

declare(strict_types=1);

return [
    // Steam の common フォルダ（Among Us が入ってる場所）
    // 例: C:\Program Files (x86)\Steam\steamapps\common
    'steam_common_dir' => 'C:\\Program Files (x86)\\Steam\\steamapps\\common',

    // ゲームフォルダ名
    'game_dir_name' => 'Among Us',

    // id.yaml のファイル名
    'yaml_name' => 'id.yaml',

    // Steam AppID（Among Us）
    'steam_app_id' => '945360',

    // ログ（Among Us フォルダ配下に作る）
    'log_relpath' => 'log\\switch.log',

    // appトークン
    // 任意の長い文字列を入れてください（ランダム生成推奨）
    'app_token' => 'VfYJ2Qq8zzc8b1QAFVfcD858ardEhbrQZtuG9JmQfnLOH0zY6ZNBoTiH1br001Xo',
];
