# 本番（Dreamhost）への反映手順

この手順書は **まだ実行していません**。実行するときは、各段階の前に「何を・どこへ・バックアップの場所」を確認してから進めます。

## 0. 決まっていないこと（実行前に決める）

| 項目 | 状態 |
|---|---|
| 置き場所 | 未定。日記専用の新しいサイトにするか、既存のサイトに入れるか |
| ドメイン | 未定 |
| SSH の接続先（Host / User / 鍵） | 未定 |
| 本番の WordPress のパス | 未定 |

決まったら、プロジェクトの記録に書き、下の変数を埋めてから始めます。以降のコマンドはこの変数を使います。

```bash
PROD_SSH=""          # 例の形: ユーザー名@サーバー名（~/.ssh/config の Host 名でもよい）
PROD_WP=""           # 本番の WordPress のディレクトリ（wp-config.php がある場所）
PROD_URL=""          # https:// から始まる本番のURL
```

**Hibi の日記データは VM から持っていきません。** VM の DB にあるのはテスト用のデータだけです。本番では空の日記から始め、VM で書いた分があれば、書き出した ZIP を本番で取り込みます。

## 1. 本番の前提を確かめる（読むだけ）

```bash
ssh "$PROD_SSH" 'which wp rsync php mysql; php -v | head -1'
ssh "$PROD_SSH" 'php -m' | grep -iE '^(openssl|gmp|bcmath|imagick|zip|mbstring|exif)$'
ssh "$PROD_SSH" "wp --path='$PROD_WP' core version; wp --path='$PROD_WP' option get timezone_string; wp --path='$PROD_WP' option get permalink_structure"
ssh "$PROD_SSH" "wp --path='$PROD_WP' plugin list --fields=name,status,version; wp --path='$PROD_WP' theme list --fields=name,status,version"
# 戻すときのために、今のテーマ名を控えておく
PROD_PREV_THEME=$(ssh "$PROD_SSH" "wp --path='$PROD_WP' theme list --status=active --field=name"); echo "$PROD_PREV_THEME"
```

確認すること：

- PHP 8.1 以上、WordPress 6.6 以上
- `openssl` と、`gmp` か `bcmath` のどちらか（通知に必要。無ければ通知以外は動く）
- `imagick`（HEIC の写真に必要。無ければ GD で JPEG / PNG / WebP は扱える）
- `zip`（書き出し・取り込みに必要）
- タイムゾーンが `America/Toronto`、パーマリンクが「基本」以外（`/%postname%/` など）
- Takumi Private Gate が有効（サイト全体のログイン必須は Gate に任せている）
- Takumi PWA が**無効**（Hibi と Service Worker がぶつかる）
- サイトが HTTPS で開ける（Service Worker と通知に必須）

稼働中のサイトに入れる場合は、ここで止まって、テーマを Hibi に切り替えてよいかを決めます（切り替えると、サイトの表側は日記アプリになります）。

## 2. バックアップ（本番）

```bash
STAMP=$(TZ=America/Toronto date +%Y%m%d-%H%M)
ssh "$PROD_SSH" "mkdir -p ~/backups && wp --path='$PROD_WP' db export ~/backups/before-hibi-$STAMP.sql && chmod 600 ~/backups/before-hibi-$STAMP.sql"
ssh "$PROD_SSH" "tar -czf ~/backups/before-hibi-$STAMP-wp-content.tgz -C '$PROD_WP' wp-content/plugins wp-content/themes"
```

`~/backups` はドキュメントルートの外にあり、Web から見えません。

## 3. ファイルを送る

VM で zip を作ります。

```bash
cd ~/projects/hibi && bin/build.sh
```

`dist-zip/hibi-journal-<版>.zip` と `dist-zip/hibi-<版>.zip` ができます。送る方法は2つあります。

- **管理画面からアップロード**（プラグイン → 新規追加 → アップロード、外観 → テーマ → 新規追加 → アップロード）。手元のブラウザで、画面を確かめながら操作します
- **rsync**。必ず `--dry-run` で一覧を見てから本実行します。`--delete` は使いません

```bash
cd ~/projects/hibi && rm -rf /tmp/hibi-deploy && mkdir /tmp/hibi-deploy && unzip -q dist-zip/hibi-journal-0.1.0.zip -d /tmp/hibi-deploy && unzip -q dist-zip/hibi-0.1.0.zip -d /tmp/hibi-deploy
rsync -avz --dry-run /tmp/hibi-deploy/hibi-journal/ "$PROD_SSH:$PROD_WP/wp-content/plugins/hibi-journal/"
rsync -avz --dry-run /tmp/hibi-deploy/hibi/ "$PROD_SSH:$PROD_WP/wp-content/themes/hibi/"
```

## 4. 有効化と初期設定（本番）

```bash
ssh "$PROD_SSH" "wp --path='$PROD_WP' plugin activate hibi-journal"
ssh "$PROD_SSH" "wp --path='$PROD_WP' theme activate hibi"
ssh "$PROD_SSH" "wp --path='$PROD_WP' rewrite flush"
```

- プラグインを有効にすると、通知用の鍵（`hibi_vapid_keys`）が作られます
- 日記を書くユーザーの言語設定（プロフィール → 言語）を「日本語」にします。画面はこの設定に従います

## 5. cron（リマインダー）

Dreamhost のパネルの Cron Jobs で、5分ごとに次のコマンドを実行する設定を作ります。パネルは手元のブラウザで、画面を確かめながら操作します。

```
wp --path=<PROD_WP の値> hibi send-reminders --quiet
```

WP-Cron にも5分ごとの予備が登録されていますが、ログイン必須のサイトはアクセスが少なく、WP-Cron だけでは時刻どおりに動きません。

## 6. 反映後の確認

VM のシェルから行う確認：

```bash
# 写真フォルダは直接開けない（403）
curl -s -o /dev/null -w '%{http_code}\n' "$PROD_URL/wp-content/uploads/hibi-private/index.php"
# Service Worker と manifest は返る（200）
curl -s -o /dev/null -w '%{http_code}\n' "$PROD_URL/hibi-sw.js"
curl -s -o /dev/null -w '%{http_code}\n' "$PROD_URL/manifest.webmanifest"
# アプリ本体は未ログインならログイン画面へ（302）
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$PROD_URL/"
# cron が動いた記録
ssh "$PROD_SSH" "wp --path='$PROD_WP' cron event list --fields=hook,next_run_relative | grep hibi"
```

`hibi-private` の確認では、`uploads/hibi-private/` が最初の写真のアップロードで作られるので、写真を1枚上げてから確かめます。

実機での確認（VM では HTTPS が無く、確かめていないもの）：

- iPhone：Safari で開き、共有ボタンから「ホーム画面に追加」→ ホーム画面から起動 → 設定 → リマインダーをオン → テスト通知が届くか
- Android：Chrome で開き、「アプリをインストール」→ 同じくテスト通知
- 機内モードで書いて、戻したときに送られるか
- キーボードを出したとき、ツールバーがキーボードのすぐ上に来るか
- 写真（特に iPhone の HEIC）が正しい向きで上がるか

## 7. 元に戻す

```bash
ssh "$PROD_SSH" "wp --path='$PROD_WP' theme activate '$PROD_PREV_THEME'"
ssh "$PROD_SSH" "wp --path='$PROD_WP' plugin deactivate hibi-journal"
```

プラグインを無効にしても、日記と写真は残ります。それでも戻らない場合は、2. のバックアップから DB とファイルを戻します（戻す前に、その時点の状態をもう一度バックアップします）。
