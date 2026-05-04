#!/bin/bash
# ============================================================
# setup_github.sh — wrzuca projekt na GitHub jedną komendą
# Użycie: bash setup_github.sh TWOJ_GITHUB_USERNAME NAZWA_REPO
# Przykład: bash setup_github.sh ogencro ip-grabber
# ============================================================

GITHUB_USER="${1:-TWOJ_USERNAME}"
REPO_NAME="${2:-ip-grabber}"

echo ""
echo "=== OG ENCRO IP Grabber — GitHub Setup ==="
echo "User: $GITHUB_USER"
echo "Repo: $REPO_NAME"
echo ""

# 1. Init git
git init
git add .
git commit -m "🚀 initial commit — IP Grabber v2 by OG ENCRO"

# 2. Stwórz repo na GitHub przez API
echo ""
echo "Tworzenie repo na GitHub..."
echo "Potrzebujesz Personal Access Token z uprawnieniem 'repo'"
echo "Wygeneruj na: https://github.com/settings/tokens/new"
echo ""
read -s -p "Wklej GitHub PAT (token nie będzie widoczny): " GH_TOKEN
echo ""

curl -s -X POST \
  -H "Authorization: token $GH_TOKEN" \
  -H "Accept: application/vnd.github.v3+json" \
  https://api.github.com/user/repos \
  -d "{\"name\":\"$REPO_NAME\",\"private\":true,\"description\":\"IP Grabber v2 — risk scoring, Telegram alerts, Gemini AI ready\"}"

echo ""
echo "Linkowanie z GitHub..."
git remote add origin "https://${GH_TOKEN}@github.com/${GITHUB_USER}/${REPO_NAME}.git"
git branch -M main
git push -u origin main

echo ""
echo "✅ Gotowe! Repo dostępne na:"
echo "   https://github.com/${GITHUB_USER}/${REPO_NAME}"
echo ""
