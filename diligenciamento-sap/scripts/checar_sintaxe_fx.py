#!/usr/bin/env python3
"""Checa a SINTAXE de todas as fórmulas de app-source/Src com o motor Power Fx do PAC.

Executa `pac power-fx run` (Preview) sobre cada fórmula. Como as listas SharePoint, os
controles e as funções exclusivas do Canvas (Navigate, Patch...) não existem fora do Studio,
erros de VÍNCULO de nomes/tipos são esperados e ignorados; só erros de ANÁLISE SINTÁTICA
(parênteses, operadores, vírgulas, fim inesperado) reprovam.

Uso:  python3 -I scripts/checar_sintaxe_fx.py      (requer pac no PATH)
"""
import re
import subprocess
import sys
import tempfile
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent
SRC = RAIZ / "app-source" / "Src"
# Somente mensagens do analisador sintático (aridade/tipos/nomes são erros de vínculo, ignorados).
SINTAXE = re.compile(r"^Expected |Unexpected characters|'Eof'|[Uu]nterminated|ParenClose|BracketClose|CurlyClose")


def formulas():
    for arq in sorted(SRC.glob("*.pa.yaml")):
        doc = yaml.safe_load(arq.read_text(encoding="utf-8"))
        pilha = [(arq.stem, doc)]
        while pilha:
            onde, no = pilha.pop()
            if isinstance(no, dict):
                for k, v in no.items():
                    if k == "Properties" and isinstance(v, dict):
                        for p, fx in v.items():
                            if isinstance(fx, str):
                                yield f"{onde}.{p}", fx[1:]
                    else:
                        pilha.append((k if k not in ("Children", "Screens") else onde, v))
            elif isinstance(no, list):
                pilha.extend((onde, x) for x in no)


def expressoes(local, fx):
    """App.Formulas contém 'nome = expr; ...' -> checa cada expr separadamente."""
    if local.endswith("App.Formulas"):
        for parte in re.split(r";\s*\n(?=\w+\s*=)", fx.strip().rstrip(";")):
            nome, _, expr = parte.partition("=")
            yield f"{local}:{nome.strip()}", expr
    else:
        yield local, fx


def desbalanceada(fx):
    """Parênteses/colchetes/chaves e aspas fora de strings; o REPL não relata isso sozinho."""
    pares, pilha, i = {")": "(", "]": "[", "}": "{"}, [], 0
    while i < len(fx):
        c = fx[i]
        if c in "\"'":
            fim = i + 1
            while fim < len(fx) and not (fx[fim] == c and fx[fim + 1:fim + 2] != c):
                fim += 2 if fx[fim] == c else 1
            if fim >= len(fx):
                return f"aspas {c} sem fechamento"
            i = fim
        elif c in "([{":
            pilha.append(c)
        elif c in pares:
            if not pilha or pilha.pop() != pares[c]:
                return f"'{c}' sem abertura correspondente"
        i += 1
    return f"'{pilha[-1]}' sem fechamento" if pilha else None


def main():
    itens = [(l, " ".join(e.split())) for loc, fx in formulas() for l, e in expressoes(loc, fx)]
    falhas = {}
    for n, (local, expr) in enumerate(itens, 1):
        motivo = desbalanceada(expr)
        if motivo:
            falhas[n] = [motivo]
    # Cada fórmula é seguida de um marcador; marcador ausente = a fórmula "engoliu" as linhas seguintes.
    linhas = []
    for n, (_, expr) in enumerate(itens, 1):
        linhas += [expr if n not in falhas else "0", f'Notify("@@{n}")']
    with tempfile.TemporaryDirectory() as tmp:
        arq = Path(tmp) / "formulas.fx"
        arq.write_text("\n".join(linhas) + "\n", encoding="utf-8")
        r = subprocess.run(["pac", "power-fx", "run", "--file", str(arq)], capture_output=True)
    saida = (r.stdout + r.stderr).decode("utf-8", "ignore").replace("\x00", "")
    vistos = {int(x) for x in re.findall(r"@@(\d+)", saida)}
    for n in range(1, len(itens) + 1):
        if n not in vistos and n not in falhas:
            falhas[n] = ["o motor Power Fx não concluiu a análise (expressão incompleta)"]
    for linha in saida.splitlines():
        m = re.match(r"\s*Line (\d+): Error [\d-]+: (.*)", linha)
        if m and int(m.group(1)) % 2 == 1 and SINTAXE.search(m.group(2)):
            falhas.setdefault((int(m.group(1)) + 1) // 2, []).append(m.group(2).strip())
    for n, msgs in sorted(falhas.items()):
        print(f"ERRO DE SINTAXE em {itens[n - 1][0]}: {msgs[0]}")
    print(f"{len(itens)} fórmulas analisadas; {len(falhas)} com erro de sintaxe.")
    sys.exit(1 if falhas else 0)


if __name__ == "__main__":
    main()
