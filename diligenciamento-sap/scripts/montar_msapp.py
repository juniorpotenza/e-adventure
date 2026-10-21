#!/usr/bin/env python3
"""Monta um .msapp CANDIDATO a partir do .msapp original do usuário + app-source/Src.

Método suportado usado (Power Platform CLI, comandos em Preview):
    pac canvas unpack --layout SourceCode   ->  Src/*.pa.yaml + <app>.msapr
    pac canvas pack   --layout SourceCode   ->  .msapp com packed.json "LoadFromYaml": true
O Studio reconstrói o app a partir de Src/*.pa.yaml ao abrir. O próprio PAC avisa que o
resultado DEVE ser validado abrindo o app para edição no Power Apps Studio.

Garantias:
  * o original nunca é alterado; uma cópia com sha256 vai para artifacts/original/;
  * telas do original que não pertencem ao gerador são preservadas (só scrInicio é substituída,
    e apenas se estiver vazia, salvo --substituir-telas);
  * propriedades do App do original são mantidas; Formulas, OnStart e StartScreen são definidas;
  * fontes geradas passam pelo validador; o pacote final é desempacotado de novo e comparado.

Uso:
  python3 -I scripts/montar_msapp.py "<caminho>/Sistema de Diligenciamento.msapp" [--substituir-telas]
Requer: pac (Microsoft.PowerApps.CLI.Tool) no PATH, PyYAML.
"""
import datetime as dt
import hashlib
import json
import shutil
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent
GERADO = RAIZ / "app-source" / "Src"
ORIG_DIR = RAIZ / "artifacts" / "original"
CAND_DIR = RAIZ / "artifacts" / "candidate"


def sha256(p):
    return hashlib.sha256(Path(p).read_bytes()).hexdigest()


def pac(*args):
    r = subprocess.run(["pac", "canvas", *args], capture_output=True, text=True)
    saida = (r.stdout + r.stderr).strip()
    if r.returncode != 0 or "succeeded" not in saida.lower():
        sys.exit(f"Falha em 'pac canvas {args[0]}':\n{saida}")
    return saida


def carregar(p):
    return yaml.safe_load(p.read_text(encoding="utf-8")) or {}


class _Dumper(yaml.SafeDumper):
    def increase_indent(self, flow=False, indentless=False):
        # Listas indentadas sob a chave-mãe, como o Studio grava (formato aceito na abertura).
        return super().increase_indent(flow, False)


_Dumper.add_representer(str, lambda d, s: d.represent_scalar(
    "tag:yaml.org,2002:str", s, style="|" if "\n" in s else None))


def salvar(p, obj):
    """Grava sem comentário extra no topo; formato idêntico ao candidato v3 validado no Studio."""
    p.write_text(yaml.dump(obj, Dumper=_Dumper, sort_keys=False, allow_unicode=True, width=10_000),
                 encoding="utf-8")


def tela_vazia(doc):
    corpo = next(iter(doc.get("Screens", {}).values()), {}) or {}
    return not corpo.get("Children")


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    substituir = "--substituir-telas" in sys.argv
    if len(args) != 1:
        sys.exit(__doc__)
    original = Path(args[0]).resolve()
    if not original.is_file():
        sys.exit(f"Arquivo não encontrado: {original}")

    r = subprocess.run([sys.executable, "-I", str(RAIZ / "scripts" / "validar_fontes.py")])
    if r.returncode != 0:
        sys.exit("Fontes geradas reprovadas no validador; nada foi empacotado.")

    carimbo = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    base = original.stem.replace(" ", "_")
    ORIG_DIR.mkdir(parents=True, exist_ok=True)
    CAND_DIR.mkdir(parents=True, exist_ok=True)
    h_orig = sha256(original)
    backup = ORIG_DIR / f"{base}__{h_orig[:12]}.msapp"
    if not backup.exists():
        shutil.copy2(original, backup)

    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        src = tmp / "src"
        print(pac("unpack", "--msapp", str(backup), "--sources", str(src), "--layout", "SourceCode"))
        srcdir = src / "Src"
        if not srcdir.is_dir():
            sys.exit("O .msapp não contém a pasta Src (formato antigo). Abra e salve no Studio atual e tente de novo.")
        shutil.copytree(srcdir, CAND_DIR / f"{base}__{carimbo}__Src_original")

        estado = carregar(srcdir / "_EditorState.pa.yaml").get("EditorState", {}) if (srcdir / "_EditorState.pa.yaml").exists() else {}
        ordem_orig = estado.get("ScreensOrder", [])
        app_orig = carregar(srcdir / "App.pa.yaml") if (srcdir / "App.pa.yaml").exists() else {}

        geradas = sorted(p for p in GERADO.glob("scr*.pa.yaml"))
        nomes_gerados = [p.name[:-len(".pa.yaml")] for p in geradas]
        for p in geradas:
            destino = srcdir / p.name
            if destino.exists() and not substituir and not tela_vazia(carregar(destino)):
                sys.exit(f"A tela {p.stem} já existe no original e tem controles. "
                         "Revise e use --substituir-telas se for intencional.")
            shutil.copy2(p, destino)

        app_gerado = carregar(GERADO / "App.pa.yaml")
        props = dict((app_orig.get("App") or {}).get("Properties") or {})
        props.update(app_gerado["App"]["Properties"])
        salvar(srcdir / "App.pa.yaml", {"App": {"Properties": props}})

        ordem_gerada = carregar(GERADO / "_EditorState.pa.yaml")["EditorState"]["ScreensOrder"]
        estado["ScreensOrder"] = ordem_gerada + [t for t in ordem_orig if t not in ordem_gerada]
        salvar(srcdir / "_EditorState.pa.yaml", {"EditorState": estado})

        candidato = CAND_DIR / f"{base}__candidato__{carimbo}.msapp"
        print(pac("pack", "--sources", str(src), "--msapp", str(candidato), "--layout", "SourceCode"))

        # Conferência pós-empacotamento: packed.json + round-trip das fontes.
        with zipfile.ZipFile(candidato) as z:
            packed = json.loads(z.read("packed.json"))
            nomes_zip = set(z.namelist())
        if not packed.get("LoadConfiguration", {}).get("LoadFromYaml"):
            sys.exit("packed.json sem LoadFromYaml=true: o Studio não carregaria as telas geradas.")
        volta = tmp / "volta"
        pac("unpack", "--msapp", str(candidato), "--sources", str(volta), "--layout", "SourceCode")
        divergentes = [p.name for p in srcdir.glob("*.pa.yaml")
                       if (volta / "Src" / p.name).read_bytes() != p.read_bytes()]
        if divergentes:
            sys.exit(f"Round-trip divergente em: {divergentes}")

    manifesto = {
        "gerado_em": carimbo,
        "original": {"arquivo": original.name, "sha256": h_orig, "copia": str(backup.relative_to(RAIZ))},
        "candidato": {"arquivo": str(candidato.relative_to(RAIZ)), "sha256": sha256(candidato)},
        "telas_geradas": nomes_gerados,
        "telas_preservadas_do_original": [t for t in ordem_orig if t not in nomes_gerados],
        "packed_json": packed,
        "src_no_pacote": sorted(n for n in nomes_zip if n.startswith("Src")),
        "status": "CANDIDATO — não validado no Power Apps Studio",
    }
    (CAND_DIR / f"{base}__candidato__{carimbo}.json").write_text(json.dumps(manifesto, ensure_ascii=False, indent=2))
    print(json.dumps(manifesto, ensure_ascii=False, indent=2))
    print("\nPróximo passo obrigatório: abrir o candidato no Power Apps Studio (Arquivo > Abrir > Procurar), "
          "adicionar as listas SharePoint e seguir docs/Guia_Importacao.md.")


if __name__ == "__main__":
    main()
