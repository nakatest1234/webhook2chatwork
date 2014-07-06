webhook2chatwork
================

github webhook to chatwork

1. php設定
  ```
  token: chatwork api用のトークン
  rid: チャット部屋のID(GETパラメータから設定可能)
  ```

2. github設定
  ```
  リポジトリ → 設定 → webhooks & services → Add webhook → Payload URL
  URLにGETパラメータ(rid=部屋ID)追加で投稿する部屋を設定可能
  ```
