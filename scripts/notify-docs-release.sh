#!/usr/bin/env bash
# Ask docs.churchcrm.io to stage or publish one ChurchCRM release.
# GH_TOKEN must be able to send repository_dispatch to that repo (Actions: write).
# VERSION is the release tag. MERGE=true publishes; MERGE=false only stages the pin pull request.
set -euo pipefail

: "${VERSION:?VERSION is required}"
DOCS_REPO="${DOCS_REPO:-ChurchCRM/docs.churchcrm.io}"
MERGE="${MERGE:-false}"

if [ -z "${GH_TOKEN:-}" ]; then
  echo "::warning::DOCS_RELEASE_TOKEN is not set. The docs site was not updated. The token needs Actions: write on ${DOCS_REPO}."
  exit 0
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "::error::VERSION must be a stable x.y.z tag. Found: $VERSION" >&2
  exit 1
fi

merge_json=false
if [ "$MERGE" = "true" ]; then
  merge_json=true
fi

payload=$(jq -n \
  --arg version "$VERSION" \
  --arg next "${NEXT_VERSION:-}" \
  --argjson merge "$merge_json" \
  '{event_type:"crm-released", client_payload:{version:$version, next_version:$next, merge:$merge}}')

echo "Dispatching crm-released for ${VERSION} (merge=${merge_json})"
printf '%s' "$payload" | gh api "repos/${DOCS_REPO}/dispatches" --method POST --input -
echo "Docs workflow dispatched. It merges only after CI is green and CodeRabbit has approved."
