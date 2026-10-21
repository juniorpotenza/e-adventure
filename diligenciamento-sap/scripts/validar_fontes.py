#!/usr/bin/env python3
"""Valida app-source/Src/*.pa.yaml antes de empacotar.

Verificações (todas offline):
  1. cada arquivo e o conjunto combinado obedecem ao schema oficial pa.yaml v3.0;
  2. todo "Control:" pertence ao enum oficial de controles 1P (ou CanvasComponent/CodeComponent);
  3. nomes de controles e telas são únicos no app;
  4. toda lista DIL_* referenciada existe em schema/dil_schema.json;
  5. todo identificador com cara de coluna (Maiuscula_Com_Sublinhado) existe no esquema real;
  6. Navigate() só aponta para telas existentes; EditorState lista todas as telas;
  7. salvaguardas do DIL_10: nenhum envio de e-mail pelo app e nenhuma gravação de "Envio_Email".

Também gera docs/Checklist_Colunas_Inferidas.md: colunas usadas pelo app cujo nome/tipo
ainda não tem evidência direta — conferir no SharePoint antes de liberar.

Uso:  python3 -I scripts/validar_fontes.py [pasta_Src]   (código de saída 1 se houver erro)
"""
import json
import re
import sys
from pathlib import Path

import jsonschema
import yaml

RAIZ = Path(__file__).resolve().parent.parent
SRC = Path(sys.argv[1]) if len(sys.argv) > 1 else RAIZ / "app-source" / "Src"
ESQ = json.loads((RAIZ / "schema" / "dil_schema.json").read_text())
SCHEMA = yaml.safe_load((RAIZ / "schema" / "microsoft" / "pa.schema.yaml").read_text())
# O schema publicado tem um regex malformado em CodeComponent-ComponentName (parêntese
# desbalanceado); por isso a meta-validação do schema é dispensada. O app não usa CodeComponent.
VALIDADOR = jsonschema.Draft7Validator(SCHEMA)
ENUM = set(yaml.safe_load((RAIZ / "schema" / "microsoft" / "ControlTypeId-1P-controls-enum.schema.yaml").read_text())["enum"])

erros, avisos = [], []


def controles(filhos, caminho):
    for item in filhos or []:
        for nome, c in item.items():
            yield nome, c, caminho
            yield from controles(c.get("Children"), caminho + [nome])


def formulas(no):
    if isinstance(no, dict):
        for k, v in no.items():
            if k == "Properties" and isinstance(v, dict):
                for p, fx in v.items():
                    if isinstance(fx, str):
                        yield p, fx
            else:
                yield from formulas(v)
    elif isinstance(no, list):
        for x in no:
            yield from formulas(x)


def chamadas(fx, funcao):
    """Trechos balanceados de cada chamada funcao(...) na fórmula."""
    res = []
    for m in re.finditer(rf"\b{funcao}\(", fx):
        nivel, i = 0, m.end() - 1
        while i < len(fx):
            nivel += {"(": 1, ")": -1}.get(fx[i], 0)
            if nivel == 0:
                break
            i += 1
        res.append(fx[m.start():i + 1])
    return res


def sem_strings(fx):
    return re.sub(r'"(?:[^"]|"")*"', '""', fx)


def main():
    combinado = {}
    for arq in sorted(SRC.glob("*.pa.yaml")):
        doc = yaml.safe_load(arq.read_text(encoding="utf-8"))
        for e in VALIDADOR.iter_errors(doc):
            erros.append(f"{arq.name}: schema v3.0: {e.message} em {list(e.absolute_path)}")
        for chave, val in doc.items():
            if chave == "Screens":
                for t in val:
                    if t in combinado.get("Screens", {}):
                        erros.append(f"tela duplicada: {t}")
                combinado.setdefault("Screens", {}).update(val)
            else:
                combinado[chave] = val
    for e in VALIDADOR.iter_errors(combinado):
        erros.append(f"app combinado: {e.message} em {list(e.absolute_path)}")

    telas = set(combinado.get("Screens", {}))
    ordem = combinado.get("EditorState", {}).get("ScreensOrder", [])
    if set(ordem) != telas:
        erros.append(f"EditorState.ScreensOrder difere das telas: {sorted(set(ordem) ^ telas)}")

    nomes = {}
    for t, corpo in combinado.get("Screens", {}).items():
        for nome, c, cam in controles(corpo.get("Children"), [t]):
            if nome in nomes or nome in telas:
                erros.append(f"nome de controle duplicado: {nome} ({'/'.join(cam)} e {nomes.get(nome)})")
            nomes[nome] = "/".join(cam)
            tipo = c["Control"].split("@")[0]
            if tipo not in ENUM and tipo not in ("CanvasComponent", "CodeComponent"):
                erros.append(f"{nome}: tipo de controle fora do enum oficial: {c['Control']}")

    listas = set(ESQ["listas"])
    colunas = {}
    for lst, d in ESQ["listas"].items():
        for c in d.get("colunas", []):
            colunas.setdefault(c["exibicao"], []).append((lst, c))
    usadas, listas_usadas = {}, set()
    for prop, fx in formulas(combinado):
        limpo = sem_strings(fx)
        for lst in re.findall(r"\bDIL_[A-Za-z_]+", limpo):
            listas_usadas.add(lst)
            if lst not in listas:
                erros.append(f"lista inexistente no esquema: {lst}")
            elif "colunas" not in ESQ["listas"][lst]:
                erros.append(f"lista sem esquema verificado: {lst}")
        for ident in re.findall(r"(?<![\w.@'])([A-Z][a-z0-9]+(?:_[A-Za-z0-9]+)+)\b", limpo) + \
                re.findall(r"\.([A-Z][a-z0-9]+(?:_[A-Za-z0-9]+)+)\b", limpo):
            if ident.startswith("DIL_"):
                continue
            if ident not in colunas:
                erros.append(f"coluna inexistente no esquema: {ident} (propriedade {prop})")
            else:
                usadas[ident] = colunas[ident]
        for alvo in re.findall(r"Navigate\(\s*(\w+)", fx):
            if alvo not in telas:
                erros.append(f"Navigate para tela inexistente: {alvo}")
        if re.search(r"Office365Outlook|SendEmail|\.Run\(|Office365Users", limpo):
            erros.append(f"chamada proibida (envio/fluxo) em {prop}: o envio é exclusivo do DIL_10")
        for trecho in chamadas(fx, "Patch") + chamadas(fx, "Collect"):
            if "Envio_Email" in trecho:
                erros.append(f"gravação contendo Envio_Email (reservado ao DIL_10, quebra a idempotência): {trecho[:80]}...")
        if re.search(r'Value\s*:\s*"Envio_Email"', fx):
            erros.append(f"valor de escolha Envio_Email montado pelo app em {prop} (reservado ao DIL_10)")

    if SRC == RAIZ / "app-source" / "Src":
        escrever_checklist(usadas, listas_usadas)
    for a in avisos:
        print("AVISO:", a)
    for e in erros:
        print("ERRO:", e)
    print(f"{len(telas)} telas, {len(nomes)} controles, {len(usadas)} colunas distintas usadas; "
          f"{len(erros)} erro(s).")
    sys.exit(1 if erros else 0)


def escrever_checklist(usadas, listas_usadas):
    linhas = ["# Checklist — colunas usadas pelo app sem evidência direta (gerado)", "",
              "> Gerado por `scripts/validar_fontes.py`. Conferir cada item em *Configurações da lista* "
              "no SharePoint (nome e tipo) antes de liberar o app. Se algo divergir, corrija o gerador, não o Studio.", "",
              "| Coluna (exibição) | Lista | Interno | Tipo usado | Evidência |", "|---|---|---|---|---|"]
    pend = 0
    for nome, ocorrencias in sorted(usadas.items()):
        for lst, c in ocorrencias:
            if lst not in listas_usadas or (
                    c["interno_status"].startswith("confirmado") and c["tipo_status"].startswith("confirmado")):
                continue
            pend += 1
            linhas.append(f"| `{nome}` | {lst} | `{c['interno']}` ({c['interno_status']}) | {c['tipo']} | {c['tipo_status']} |")
    linhas += ["", f"Total pendente de conferência: **{pend}** coluna(s).",
               "", "Observação: o mesmo nome de exibição existe em várias listas; a tabela lista cada lista usada pelo app "
               "em que a coluna ainda não tem evidência direta (pode haver itens não usados naquela lista específica)."]
    (RAIZ / "docs" / "Checklist_Colunas_Inferidas.md").write_text("\n".join(linhas) + "\n")


if __name__ == "__main__":
    main()
