#!/usr/bin/env bash
# Run PHPUnit tests and capture all output to logs
# Usage: ./scripts/run-tests-with-logging.sh
# Output: storage/logs/test-results.log

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_DIR="$PROJECT_ROOT/storage/logs"
LOG_FILE="$LOG_DIR/test-results.log"

mkdir -p "$LOG_DIR"

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
echo "================================================================================
TEST RUN - $TIMESTAMP
================================================================================
" | tee "$LOG_FILE"

cd "$PROJECT_ROOT"
php artisan test 2>&1 | tee -a "$LOG_FILE"
EXIT_CODE=${PIPESTATUS[0]}

echo "
--------------------------------------------------------------------------------
SUMMARY - Exit Code: $EXIT_CODE | Completed: $(date '+%Y-%m-%d %H:%M:%S')
--------------------------------------------------------------------------------
" >> "$LOG_FILE"

echo ""
echo "[LOG] Full output written to: $LOG_FILE"
exit $EXIT_CODE
