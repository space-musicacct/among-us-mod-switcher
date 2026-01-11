# Among Us Mod Switcher (v1.0)

Windows Steam版 Among Us のバニラ ⇔ 各種 MOD 環境をフォルダ丸ごと安全に切り替えるローカルGUIツールです。

MODを頻繁に切り替える人で、手動で切り替えるのが面倒な人にオススメです。

## 特徴
- フォルダごとでバニラと各種 MOD を完全分離
- Among Us 起動中は切り替え不可
- 切替失敗時は自動ロールバック (変更前の状態に戻す)
- 切替ログは `Among Us/log/switch.log` に JSONL で保存
- localhost (127.0.0.1) 以外でのアクセスを拒否

## 必要環境
- Windows
- PHP 8.4以上 (XAMPP推奨)
- Steam版Among Us

## インストール & 使い方
1. このリポジトリをダウンロード or clone
2. `config.example.php` → `config.php` にリネーム
3. `config.php` 内の `return` の中身を必要に応じて自分の環境に修正
4. XAMPPのhtdocsなどにフォルダごと配置
5. ブラウザで `http://localhost/among-us-mod-switcher/` を開く
6. Among Usを終了させた状態で切り替え実行

## フォルダ構成の作り方の例
```
.../Steam/steamapps/common/
├── Among Us/                    ← アクティブ
├── Among Us - Vanilla/          ← 非アクティブ
├── Among Us - TOH-K/            ← 非アクティブ
└── Among Us - SNR/              ← 非アクティブ
```

## 注意
1. 正常にツールが動作しない場合は、管理者権限でXAMPPを起動しているかどうかを確認してください。
2. `id.mod-example.yaml`, `id.vanilla-example.yaml`を参考に、各フォルダ (`Among Us/`, `Among Us - Vanilla/`, `Among Us - SNR/` など) 内に `id.yaml` を置いてください。
3. Among Usが起動中の場合は切り替えできません。
4. 初回使用時はバックアップ推奨です。
5. このツールはローカル専用です。公開サーバーには置かないでください。

## ライセンス
MIT Licenseです。  

詳細は [LICENSE](LICENSE) ファイルを参照してください。

本ソフトウェアは「現状有姿（AS IS）」で提供されており、いかなる明示的・黙示的な保証もありません。  

本ソフトウェアの使用または使用不能によって生じたいかなる損害（直接的・間接的・特別・結果的損害を含む）についても、著作権者 (space) は一切の責任を負いません。  

使用はご自身の責任でお願いします。