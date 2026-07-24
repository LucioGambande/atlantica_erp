#!/usr/bin/env bash
# Hook PreToolUse (Bash) de Claude Code: bloquea git commit/push estando en un branch protegido.
# Opt-in por proyecto: lee .claude/protected-branches (un branch por línea). Sin ese archivo, no hace nada.
# El candado real y agnóstico es la branch protection de GitHub — esto es el cinturón local.
set -euo pipefail

LIST=".claude/protected-branches"
[ -f "$LIST" ] || exit 0

INPUT="$(cat || true)"
printf '%s' "$INPUT" | grep -Eq 'git[^"]*(commit|push)' || exit 0

BRANCH="$(git branch --show-current 2>/dev/null || git rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
[ -n "$BRANCH" ] || exit 0

if grep -Fxq "$BRANCH" "$LIST"; then
  echo "BLOQUEADO: estás en '$BRANCH', que es un branch protegido (ver $LIST)." >&2
  echo "Creá un branch de trabajo (feature/<ticket-o-slug>) y abrí un PR. No commitees ni pushees directo acá." >&2
  exit 2
fi

exit 0
