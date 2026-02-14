#!/usr/bin/env bash
set -euo pipefail

# Convenience wrapper (repo root) -> bin/prod.sh
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/bin/prod.sh" "$@"

