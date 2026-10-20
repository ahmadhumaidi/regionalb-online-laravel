#!/usr/bin/env bash
set -euo pipefail
# Compatibility alias for older deployment commands.
exec "$(dirname "${BASH_SOURCE[0]}")/prepare-production.sh" "$@"
