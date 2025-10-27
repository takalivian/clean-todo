# デプロイコマンド集

デプロイを実行するための各種コマンドをまとめています。

## 方法1: 空コミットでデプロイ（最も簡単）

変更がなくても、空コミットをプッシュすることでデプロイをトリガーできます。

```bash
cd /Users/takamura/products/private/clean-todo

# 空コミットを作成してプッシュ
git commit --allow-empty -m "Trigger deployment"
git push origin main
```

このコマンドは：
- ✅ 変更がなくても実行できる
- ✅ コミット履歴に残る
- ✅ デプロイのタイミングが明確

## 方法2: GitHub UIから手動でトリガー（推奨）

変更をコミットせずに、GitHub上でボタンをクリックしてデプロイできます。

### 手順

1. GitHubリポジトリのページを開く
2. **Actions** タブをクリック
3. 左サイドバーの **Deploy to AWS** をクリック
4. 右側の **Run workflow** ボタンをクリック
5. **Branch: main** を選択
6. **Run workflow** をクリック

これで即座にデプロイが開始されます！

## 方法3: 通常のコミット＆プッシュ

コードを変更した場合の通常の方法：

```bash
cd /Users/takamura/products/private/clean-todo

# 変更をステージング
git add .

# コミット
git commit -m "Update feature"

# プッシュ（自動でデプロイが開始）
git push origin main
```

## 方法4: 失敗したデプロイを再実行

デプロイが失敗した場合、簡単に再実行できます：

### GitHub UIで再実行

1. **Actions** タブを開く
2. 失敗したワークフロー実行をクリック
3. 右上の **Re-run jobs** をクリック
4. **Re-run all jobs** を選択

### 特定のジョブだけ再実行

1. 失敗したジョブをクリック
2. 右上の **Re-run job** をクリック

## デプロイ状況の確認

### GitHub Actionsで確認

```
Actions タブ → 最新のワークフロー実行
```

各ステップの状態が表示されます：
- 🔵 実行中
- ✅ 成功
- ❌ 失敗

### AWS CodeDeployで確認

```bash
# 最新のデプロイメント一覧を取得
aws deploy list-deployments \
  --application-name clean-todo-app \
  --deployment-group-name clean-todo-deployment-group \
  --max-items 5

# 特定のデプロイメント詳細を確認
aws deploy get-deployment --deployment-id d-XXXXXXXXX
```

### EC2でログ確認

```bash
# EC2に接続
ssh -i clean-todo-keypair.pem ec2-user@<EC2のパブリックIP>

# CodeDeployエージェントのログ
sudo tail -f /var/log/aws/codedeploy-agent/codedeploy-agent.log

# アプリケーションコンテナのログ
docker logs -f clean-todo-app

# データベースコンテナのログ
docker logs -f clean-todo-db
```

## デプロイのワークフロー

```
1. コード変更 / 空コミット / 手動トリガー
   ↓
2. GitHub Actions開始
   ├─ ECRへのイメージビルド＆プッシュ
   └─ S3へのデプロイパッケージアップロード
   ↓
3. CodeDeployデプロイメント作成
   ↓
4. EC2でデプロイスクリプト実行
   ├─ ApplicationStop (既存コンテナ停止)
   ├─ BeforeInstall (クリーンアップ)
   ├─ AfterInstall (新イメージpull)
   ├─ ApplicationStart (コンテナ起動)
   └─ ValidateService (ヘルスチェック)
   ↓
5. デプロイ完了 🎉
```

## トラブルシューティング

### エラー: "Credentials could not be loaded"

**原因**: GitHub Secretsが正しく設定されていない

**解決策**:
```bash
# Settings → Secrets and variables → Actions で確認
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
S3_BUCKET
```

### エラー: "No Deployment Group found"

**原因**: CodeDeployのリソースが作成されていない

**解決策**:
```bash
./deployment/create-aws-resources.sh
```

### エラー: "The specified bucket does not exist"

**原因**: S3バケット名がGitHub Secretsと一致していない

**解決策**:
```bash
# 実際のバケット名を確認
aws s3 ls | grep clean-todo

# GitHub SecretsのS3_BUCKETを更新
```

### デプロイは成功したが、アプリが起動しない

**確認ポイント**:

1. EC2でコンテナの状態を確認
   ```bash
   docker ps -a
   docker logs clean-todo-app
   ```

2. 環境変数ファイルを確認
   ```bash
   cat /home/ec2-user/.env.production
   ```

3. データベースの状態を確認
   ```bash
   docker logs clean-todo-db
   docker exec clean-todo-db mysql -u root -p -e "SHOW DATABASES;"
   ```

## クイックコマンドリファレンス

```bash
# 空コミットでデプロイ
git commit --allow-empty -m "Deploy" && git push origin main

# 最新のコミットハッシュを確認
git log -1 --pretty=format:"%h"

# デプロイ履歴を確認（AWS CLI）
aws deploy list-deployments --application-name clean-todo-app

# EC2のステータスを確認
aws ec2 describe-instances \
  --filters "Name=tag:Name,Values=clean-todo-production" \
  --query 'Reservations[0].Instances[0].[InstanceId,State.Name,PublicIpAddress]' \
  --output table

# CodeDeployエージェントの状態を確認（EC2内で実行）
sudo systemctl status codedeploy-agent
```

## ベストプラクティス

### 1. デプロイ前の確認

```bash
# ローカルでテストを実行
docker compose exec app php artisan test

# 全てのテストが通過することを確認
```

### 2. デプロイ後の確認

```bash
# ヘルスチェック
curl http://<EC2のパブリックIP>/api/health

# レスポンス例:
# {"status":"ok","timestamp":"2025-01-27T12:34:56+00:00"}
```

### 3. 段階的なデプロイ

大きな変更の場合：
1. まず `develop` ブランチで変更をテスト
2. プルリクエストを作成してレビュー
3. `main` にマージしてデプロイ

### 4. ロールバック計画

問題が発生した場合：
```bash
# 前のコミットに戻す
git revert HEAD
git push origin main

# または特定のコミットに戻る
git reset --hard <previous-commit-hash>
git push -f origin main
```

## 次回以降のデプロイ

初回セットアップが完了したら、次回からは簡単です：

```bash
# コードを変更
# テストを実行
# コミット＆プッシュ
git add .
git commit -m "Add new feature"
git push origin main

# または空コミット
git commit --allow-empty -m "Deploy"
git push origin main
```

これだけで自動的にデプロイが実行されます！
