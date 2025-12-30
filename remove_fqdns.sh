#!/usr/bin/env sh
set -eu

HOSTS_FILE="${HOSTS_FILE:-/etc/hosts}"

START_MARKER="# >>> fqdn_issue demo (managed) >>>"
END_MARKER="# <<< fqdn_issue demo (managed) <<<"

TMP_FILE="$(mktemp)"

cleanup() {
  rm -f "$TMP_FILE"
}
trap cleanup EXIT

if [ ! -f "$HOSTS_FILE" ]; then
  echo "Hosts file not found: $HOSTS_FILE" >&2
  exit 1
fi

awk -v start="$START_MARKER" -v end="$END_MARKER" '
  $0==start {inblock=1; next}
  $0==end {inblock=0; next}
  inblock==0 {print}
' "$HOSTS_FILE" > "$TMP_FILE"

sudo tee "$HOSTS_FILE" >/dev/null < "$TMP_FILE"

echo "Removed fqdn_issue demo block from $HOSTS_FILE"
