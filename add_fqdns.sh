#!/usr/bin/env sh
set -eu

IP="${1:-127.0.0.1}"
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

{
  cat "$TMP_FILE"
  echo "$START_MARKER"
  echo "$IP finanz.bund.de"
  echo "$IP meldeamt.bund.de"
  echo "$IP finanz.region.gv.at"
  echo "$IP meldeamt.region.gv.at"
  echo "$END_MARKER"
} | sudo tee "$HOSTS_FILE" >/dev/null

echo "Updated $HOSTS_FILE with fqdn_issue demo block for IP=$IP"
