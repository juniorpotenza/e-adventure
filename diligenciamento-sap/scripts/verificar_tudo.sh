#!/usr/bin/env bash
# Regenera e valida tudo o que é possível validar fora do tenant.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -f local/DIL.zip ]; then python3 -I scripts/extrair_esquema.py local/DIL.zip; fi
python3 -I scripts/gerar_fontes.py
python3 -I scripts/validar_fontes.py
if command -v pac >/dev/null; then python3 -I scripts/checar_sintaxe_fx.py; else echo "AVISO: pac ausente; sintaxe Power Fx não checada."; fi
git diff --stat -- app-source schema docs || true
