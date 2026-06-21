#!/usr/bin/env bash
#
# Cut a ConvoroCP release so the self-updater ships BOTH backend and frontend.
#
# The updater pulls the GitHub source tarball (no built assets) and then looks
# for a `dist.tar.gz` release asset containing public/build. This script builds
# the frontend and attaches that asset, so a panel "Update now" delivers the new
# UI too. (If the asset is ever missing, the updater falls back to building on
# the box when Node is present.)
#
# Usage:
#   deploy/release.sh v1.1.2 [notes-file]
#   deploy/release.sh v1.1.2 -            # read notes from stdin
#
set -euo pipefail

TAG="${1:?usage: release.sh <vX.Y.Z> [notes-file]}"
NOTES="${2:-}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Keep config/convorocp.php's version default in lockstep with the release tag.
# The Updates page reads config('convorocp.version') to detect new releases, so
# if this drifts the panel reports the wrong installed version (and the self-
# updater can't tell it's up to date). Bump + commit it so the tag includes it.
CLEAN="${TAG#v}"
echo "==> Setting installed-version default to ${CLEAN}"
sed -i -E "s/(env\('CONVOROCP_VERSION', ')[^']*(')/\1${CLEAN}\2/" config/convorocp.php
if ! git diff --quiet config/convorocp.php; then
  git add config/convorocp.php
  git commit -m "chore: set version default to ${CLEAN} for ${TAG}"
  git push origin HEAD
fi

echo "==> Building frontend assets"
npm ci --no-audit --no-fund
npm run build

echo "==> Packing dist.tar.gz (public/build)"
DIST="$(mktemp -d)/dist.tar.gz"
tar -czf "$DIST" public/build

echo "==> Creating release $TAG"
args=(release create "$TAG" --target master --latest --title "ConvoroCP ${TAG#v}")
if [ -n "$NOTES" ]; then args+=(--notes-file "$NOTES"); else args+=(--generate-notes); fi
gh "${args[@]}"

echo "==> Uploading dist.tar.gz"
gh release upload "$TAG" "$DIST#dist.tar.gz" --clobber

echo "==> Done: $TAG (with dist.tar.gz)"
