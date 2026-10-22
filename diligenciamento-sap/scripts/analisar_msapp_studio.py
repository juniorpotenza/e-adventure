#!/usr/bin/env python3
"""Analisa um .msapp salvo pelo Power Apps Studio (com as listas conectadas).

Relata:
  * erros do Verificador (AppCheckerResult.sarif), agrupados; avisos apenas contados;
  * fontes de dados conectadas (nomes, duplicadas como DIL_Acoes_1);
  * de qual versão gerada vêm as telas (compara Src com app-source/Src).
Também grava local/esquema_studio.json (esquema real das listas), usado por extrair_esquema.py.

Uso:  python3 -I scripts/analisar_msapp_studio.py <arquivo.msapp>
"""
import collections
import json
import sys
import zipfile
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent


def ler(z, nome):
    alvo = [n for n in z.namelist() if n.replace("\\", "/") == nome]
    return z.read(alvo[0]).decode("utf-8-sig") if alvo else None


def main():
    z = zipfile.ZipFile(sys.argv[1])
    print("integridade:", z.testzip() or "OK")

    sarif = json.loads(ler(z, "AppCheckerResult.sarif") or '{"runs": []}')
    erros, avisos = [], collections.Counter()
    for run in sarif.get("runs", []):
        for r in run.get("results", []):
            regra = r.get("ruleId", "")
            local = ""
            for loc in r.get("locations", []):
                for ll in loc.get("logicalLocations", []):
                    local = ll.get("fullyQualifiedName", local)
            nivel = r.get("properties", {}).get("level", "")
            if regra.startswith("app-Err") or nivel == "High" and not regra.startswith("acc-"):
                erros.append((regra, local, r.get("message", {}).get("arguments", [])))
            else:
                avisos[regra] += 1
    print(f"\nERROS ({len(erros)}):")
    for regra, local, args in erros:
        print(f"  {regra:40s} {local:55s} {args}")
    print(f"\navisos ({sum(avisos.values())}):", dict(avisos.most_common()))

    ds = json.loads(ler(z, "References/DataSources.json") or '{"DataSources": []}')["DataSources"]
    nomes = [d["Name"] for d in ds if d.get("Type") == "ConnectedDataSourceInfo"]
    print(f"\nfontes conectadas ({len(nomes)}):", ", ".join(sorted(nomes)))
    dup = [n for n in nomes if n[-2:-1] == "_" and n[-1].isdigit()]
    if dup:
        print("  ATENÇÃO, fontes duplicadas:", dup)
    esquema = {}
    for d in ds:
        if d.get("Type") != "ConnectedDataSourceInfo" or not d.get("DataEntityMetadataJson"):
            continue
        meta = json.loads(next(iter(d["DataEntityMetadataJson"].values())))
        props = meta.get("schema", {}).get("items", {}).get("properties", {})
        esquema[d["Name"]] = {k: (p.get("title"), p.get("type"), p.get("format", ""), "") for k, p in props.items()}
    (RAIZ / "local").mkdir(exist_ok=True)
    (RAIZ / "local" / "esquema_studio.json").write_text(json.dumps(esquema, ensure_ascii=False, indent=1))

    gerado = {p.name: yaml.safe_load(p.read_text()) for p in (RAIZ / "app-source" / "Src").glob("scr*.pa.yaml")}
    print("\ntelas no arquivo:")
    for n in sorted(z.namelist()):
        nome = n.replace("\\", "/")
        if nome.startswith("Src/scr"):
            doc = yaml.safe_load(ler(z, nome))
            tela = next(iter(doc["Screens"]))
            filhos = len(json.dumps(doc))
            ref = gerado.get(nome.split("/")[-1])
            igual = "" if ref is None else f"(gerado atual: {len(json.dumps(ref))} bytes)"
            print(f"  {tela:18s} {filhos} bytes {igual}")


if __name__ == "__main__":
    main()
