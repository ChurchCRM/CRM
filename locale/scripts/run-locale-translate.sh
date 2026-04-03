#!/bin/bash
# Locale Translation Workflow Executor
# Invoked by /locale-translate skill

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Parse arguments
ACTION=""
LOCALE=""
VERSION=""

while [[ $# -gt 0 ]]; do
  case $1 in
    --list) ACTION="list" ;;
    --locale) LOCALE="$2"; ACTION="single"; shift ;;
    --all) ACTION="all" ;;
    --version) VERSION="$2"; shift ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
  shift
done

case "$ACTION" in
  list)
    # List locales with missing terms
    node "$SCRIPT_DIR/locale-translate.js" --list
    ;;

  single)
    if [ -z "$LOCALE" ]; then
      echo "Error: --locale requires a code (e.g., fr)"
      exit 1
    fi
    # Single locale translation will be handled by agent
    echo "🔄 Translating locale: $LOCALE"
    echo "Note: This requires agent execution. Use /locale-translate --all for autonomous batch processing."
    exit 1
    ;;

  all)
    # All locales - launch autonomous agent
    # This requires Claude Code to interpret and execute
    echo "🌍 Launching autonomous locale translation agent..."
    echo "Processing all remaining locales on branch: locale/{version}-{date}"
    echo ""
    echo "The agent will:"
    echo "  ✓ Detect version from package.json"
    echo "  ✓ Skip already-translated locales"
    echo "  ✓ Translate each locale with church vocabulary"
    echo "  ✓ Commit + push one locale per commit"
    echo "  ✓ Resume on timeout"
    echo ""
    echo "Status: Ready for agent execution"
    exit 0
    ;;

  *)
    echo "Usage: $0 --list | --locale <code> | --all"
    exit 1
    ;;
esac
