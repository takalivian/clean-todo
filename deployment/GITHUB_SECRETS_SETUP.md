# GitHub Secrets 設定ガイド

GitHub ActionsでSecretsが読み込めない問題を解決するためのガイドです。

## 問題の症状

```
❌ ERROR: AWS_ACCESS_KEY_ID secret is not set
```

ログに `if [ -z "" ]; then` のように空文字列が表示される。

## 原因

1. **Secretsが設定されていない**
2. **Secretsの設定場所が間違っている**
3. **リポジトリがフォーク（Fork）されている**
4. **Secretsの名前が間違っている**
5. **リポジトリの権限設定の問題**

## 解決手順

### ステップ1: リポジトリの確認

まず、以下を確認してください：

#### A. リポジトリがフォークではないことを確認

- GitHubのリポジトリページで、リポジトリ名の下に "forked from ..." と表示されている場合、フォークリポジトリです
- **フォークリポジトリの場合、親リポジトリのSecretsは使用できません**
- 解決策: 自分のアカウントで新しいリポジトリを作成（フォークではなく、importまたは新規作成）

#### B. リポジトリがPrivateかPublicか確認

- リポジトリ名の横に "Private" または "Public" と表示されています
- Publicリポジトリの場合、一部の制限がある可能性があります

### ステップ2: Secretsの正しい設定場所

**必ず以下の場所で設定してください：**

1. GitHubリポジトリのページを開く
2. **Settings** タブをクリック（リポジトリの管理権限が必要）
3. 左サイドバーの **Secrets and variables** を展開
4. **Actions** をクリック
5. **Repository secrets** タブを選択（デフォルトで選択されているはず）

### ステップ3: Secretsの作成

#### 1つ目: AWS_ACCESS_KEY_ID

1. **New repository secret** ボタンをクリック
2. **Name** に以下を **正確に** 入力（コピー＆ペースト推奨）:
   ```
   AWS_ACCESS_KEY_ID
   ```
3. **Secret** にAWSアクセスキーIDを貼り付け
   - `AKIA` で始まる20文字の文字列
   - スペースや改行が入らないように注意
4. **Add secret** をクリック

#### 2つ目: AWS_SECRET_ACCESS_KEY

1. **New repository secret** ボタンをクリック
2. **Name** に以下を **正確に** 入力:
   ```
   AWS_SECRET_ACCESS_KEY
   ```
3. **Secret** にAWSシークレットアクセスキーを貼り付け
   - 40文字程度の英数字と記号の文字列
   - スペースや改行が入らないように注意
4. **Add secret** をクリック

#### 3つ目: S3_BUCKET

1. **New repository secret** ボタンをクリック
2. **Name** に以下を **正確に** 入力:
   ```
   S3_BUCKET
   ```
3. **Secret** に以下を入力:
   ```
   clean-todo-deployment
   ```
4. **Add secret** をクリック

### ステップ4: 設定の確認

設定後、**Repository secrets** セクションに以下の3つが表示されるはずです：

```
AWS_ACCESS_KEY_ID          Updated X seconds ago
AWS_SECRET_ACCESS_KEY      Updated X seconds ago
S3_BUCKET                  Updated X seconds ago
```

**重要な確認ポイント：**
- [ ] 名前が完全に一致している（大文字小文字、アンダースコアの数）
- [ ] 3つとも「Repository secrets」に設定されている
- [ ] 「Environment secrets」や「Organization secrets」ではない
- [ ] 各Secretに「Updated」と表示されている

### ステップ5: AWS認証情報の取得（まだ持っていない場合）

#### ローカルで使用している認証情報を確認

```bash
# アクセスキーIDを確認
aws configure get aws_access_key_id

# シークレットアクセスキーを確認
aws configure get aws_secret_access_key

# または、credentialsファイルを直接確認
cat ~/.aws/credentials
```

出力例：
```
[default]
aws_access_key_id = AKIAIOSFODNN7EXAMPLE
aws_secret_access_key = wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
```

#### 新しい認証情報を作成する場合

1. AWS Management Consoleにログイン
2. **IAM** サービスを開く
3. **Users** → 自分のユーザーを選択
4. **Security credentials** タブ
5. **Create access key** をクリック
6. **Use case**: "Command Line Interface (CLI)" を選択
7. アクセスキーIDとシークレットアクセスキーをメモ
   - **シークレットアクセスキーは1度しか表示されません！**
   - ダウンロードするか、安全な場所にコピーしてください

### ステップ6: リポジトリの権限設定

1. GitHubリポジトリの **Settings** → **Actions** → **General**
2. **Actions permissions** セクション:
   - "Allow all actions and reusable workflows" を選択
3. **Workflow permissions** セクション:
   - "Read and write permissions" を選択
   - "Allow GitHub Actions to create and approve pull requests" にチェック
4. **Save** をクリック

### ステップ7: デプロイワークフローの再実行

#### A. 新しいコミットをプッシュ

```bash
cd /Users/takamura/products/private/clean-todo

# ダミーの変更を作成
echo "" >> README.md

git add .
git commit -m "Test GitHub Secrets configuration"
git push origin main
```

#### B. 手動でワークフローを再実行

1. GitHubリポジトリの **Actions** タブ
2. 失敗したワークフロー実行をクリック
3. 右上の **Re-run jobs** → **Re-run all jobs**

### ステップ8: エラーが続く場合のトラブルシューティング

#### デバッグワークフローを実行

1. **Actions** タブ → 左サイドバーの **Debug Secrets**
2. **Run workflow** → **Run workflow**
3. 実行結果を確認

このワークフローでもSecretsが読み込めない場合:

#### チェックリスト

- [ ] リポジトリの **Settings** タブにアクセスできる（管理者権限がある）
- [ ] リポジトリがフォークされていない
- [ ] Secretsが「Repository secrets」に設定されている
- [ ] Secretsの名前が正確（AWS_ACCESS_KEY_ID、AWS_SECRET_ACCESS_KEY、S3_BUCKET）
- [ ] 値にスペースや改行が含まれていない
- [ ] リポジトリのActions権限が有効

#### リポジトリを作り直す（最終手段）

フォークリポジトリの場合、またはどうしても解決しない場合：

1. 新しいプライベートリポジトリを作成
2. ローカルのコードをプッシュ:
   ```bash
   git remote set-url origin https://github.com/YOUR_USERNAME/NEW_REPO_NAME.git
   git push -u origin main
   ```
3. 新しいリポジトリでSecretsを設定

## よくある間違い

### ❌ 間違い1: Secretsの名前が違う

```
AWS_ACCESS_KEY_id     ← 最後のidが小文字
AWS_ACCESS-KEY-ID     ← アンダースコアではなくハイフン
AWS ACCESS KEY ID     ← スペースが含まれている
```

### ❌ 間違い2: 設定場所が違う

- **❌ Organization secrets**: Organizationレベルで設定
- **❌ Environment secrets**: Environment用の設定
- **✅ Repository secrets**: これが正解！

### ❌ 間違い3: 値にスペースや改行が入っている

```
❌ AKIAIOSFODNN7EXAMPLE
   ↑ 最後にスペース

❌ AKIAIOSFODNN7EXAMPLE
   ↑ 最初に改行

✅ AKIAIOSFODNN7EXAMPLE
```

### ❌ 間違い4: フォークリポジトリで設定

フォーク元のリポジトリのSecretsは、フォーク先では使用できません。
自分のリポジトリで設定する必要があります。

## 確認コマンド

設定後、以下のコマンドで確認できます：

```bash
# ローカルのAWS認証情報を確認
aws sts get-caller-identity

# 期待される出力:
# {
#     "UserId": "AIDAI...",
#     "Account": "123456789012",
#     "Arn": "arn:aws:iam::123456789012:user/your-user"
# }
```

## サポート

それでも解決しない場合、以下の情報を含めて質問してください：

1. リポジトリがフォークかどうか
2. リポジトリがPrivateかPublicか
3. Settings → Secrets and variablesにアクセスできるか
4. Debug Secretsワークフローの実行結果
5. Actions → Generalの権限設定のスクリーンショット
