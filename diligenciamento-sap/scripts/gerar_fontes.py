#!/usr/bin/env python3
"""Gera as fontes do Canvas App (formato oficial *.pa.yaml, schema v3.0) em app-source/Src.

O app é descrito aqui em Python para que telas, cabeçalho e menu sejam gerados
automaticamente e de forma consistente (o usuário não monta controles à mão).

Regras de segurança aplicadas na geração:
  * só são usadas colunas presentes em schema/dil_schema.json (o validador confere);
  * nenhuma fórmula envia e-mail; o envio continua exclusivo do fluxo DIL_10;
  * o app nunca grava o evento "Envio_Email" no histórico (chave de idempotência do DIL_10);
  * ações criadas no app não podem ser do tipo "Escalonamento" (gatilho do DIL_09/DIL_10).

Controles clássicos cujo nome colide com um controle moderno levam o prefixo
"Classic/" (Button, TextInput, DropDown, ComboBox, DatePicker): sem o prefixo o
Studio carrega o controle MODERNO e rejeita Fill, Size, Default, HintText etc.
(erro PA2108 observado no Studio em 21/10/2026).

Fórmulas usam a sintaxe invariante do arquivo-fonte ("," entre argumentos e ";"
para encadear). O Studio em pt-BR exibe ";" e ";;" automaticamente.

Uso:  python3 -I scripts/gerar_fontes.py
"""
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent
SAIDA = RAIZ / "app-source" / "Src"

AVISO = (
    "# Gerado por scripts/gerar_fontes.py — NÃO editar à mão; altere o gerador e rode de novo.\n"
    "# Formato: Power Apps Source Code (*.pa.yaml), schema v3.0.\n"
)

# ---------------------------------------------------------------------------
# Layout (tablet/desktop 16:9) e identidade visual do protótipo aprovado
# ---------------------------------------------------------------------------
LARG_MENU = 220
ALT_CAB = 56
X0 = LARG_MENU + 20
Y_TITULO = ALT_CAB + 14
Y0 = ALT_CAB + 64
LARG_CONTEUDO = f"Parent.Width - {X0 + 20}"
ALT_CONTEUDO = f"Parent.Height - {Y0 + 16}"

TELAS = [
    # chave, nome da tela, rótulo do menu, perfis que veem o item (* = todos)
    ("inicio", "scrInicio", "Início", "*"),
    ("carteira", "scrCarteira", "Minha carteira", "*"),
    ("dossie", "scrDossie", "Dossiê do processo", "*"),
    ("ocorrencias", "scrOcorrencias", "Ocorrências", "*"),
    ("acoes", "scrAcoes", "Ações", "*"),
    ("agenda", "scrAgenda", "Agenda / Marcos", "*"),
    ("comunicacoes", "scrComunicacoes", "Comunicações", "*"),
    ("gestao", "scrGestao", "Visão da gestão", "Coordenador,Gestor,Auditor,Administrador"),
    ("admin", "scrAdmin", "Administração", "Gestor,Administrador"),
]

FORMULAS_NOMEADAS = """\
nfCorNavy = RGBA(18, 48, 74, 1);
nfCorNavy2 = RGBA(28, 69, 100, 1);
nfCorFundo = RGBA(243, 246, 250, 1);
nfCorCartao = RGBA(255, 255, 255, 1);
nfCorLinha = RGBA(220, 228, 236, 1);
nfCorTexto = RGBA(24, 43, 58, 1);
nfCorSuave = RGBA(101, 119, 137, 1);
nfCorAzul = RGBA(23, 92, 211, 1);
nfCorVerde = RGBA(6, 118, 71, 1);
nfCorLaranja = RGBA(181, 71, 8, 1);
nfCorVermelho = RGBA(180, 35, 24, 1);
nfEmailUsuario = Lower(User().Email);
nfPessoaAtual = {
    '@odata.type': "#Microsoft.Azure.Connectors.SharePoint.SPListExpandedUser",
    Claims: "i:0#.f|membership|" & Lower(User().Email),
    DisplayName: User().FullName,
    Email: Lower(User().Email),
    Department: "",
    JobTitle: "",
    Picture: ""
};
nfOcorFechadas = ["Resolvida", "Cancelada"];
nfAcaoFechadas = ["Concluída", "Cancelada"];
nfMarcoFechados = ["Concluído", "Cancelado"];
nfOrigemApp = "Power Apps";
nfMenu = Table(
""" + ",\n".join(
    f'    {{Chave: "{k}", Rotulo: "{r}", Perfis: "{p}"}}' for k, _, r, p in TELAS) + "\n);\n"

# Carga central das coleções (App.OnStart e botão "Atualizar" do cabeçalho).
CARGA = """\
Concurrent(
    ClearCollect(colVinc, Filter(DIL_Vinculo_RC_PO, Ativo = true)),
    ClearCollect(colItensPO, Filter(DIL_Itens_Pedido, Ativo = true)),
    ClearCollect(colOcor, Filter(DIL_Ocorrencias, Ativo = true)),
    ClearCollect(colAcoes, Filter(DIL_Acoes, Ativo = true)),
    ClearCollect(colMarcos, DIL_Marcos),
    ClearCollect(colComun, DIL_Comunicacoes),
    ClearCollect(colEnvios, Filter(DIL_Historico, Tipo_Evento.Value = "Envio_Email"))
);
ClearCollect(
    colCarteira,
    AddColumns(
        AddColumns(
            colVinc As v,
            ItemPO, LookUp(colItensPO, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO),
            QtdOcorrAbertas, CountRows(Filter(colOcor, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && !(Status_Ocorrencia.Value in nfOcorFechadas))),
            QtdCriticas, CountRows(Filter(colOcor, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && Severidade.Value = "Crítica" && !(Status_Ocorrencia.Value in nfOcorFechadas))),
            QtdAcoesPend, CountRows(Filter(colAcoes, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && !(Status_Acao.Value in nfAcaoFechadas))),
            QtdMarcosAtrasados, CountRows(Filter(colMarcos, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && IsBlank(Data_Realizada) && Data_Planejada < Today() && !(Status_Marco.Value in nfMarcoFechados)))
        ) As c,
        Situacao, If(c.QtdCriticas > 0, "Crítico", c.QtdOcorrAbertas > 0 || c.QtdMarcosAtrasados > 0, "Em risco", "Normal")
    )
);
Set(gblCarregadoEm, Now())"""

ON_START = """\
Set(
    gblPerfil,
    Coalesce(
        LookUp(DIL_Configuracao, Categoria = "Perfil_Usuario" && Nome_Parametro = nfEmailUsuario, Valor_Texto),
        "Diligenciador"
    )
);
Set(gblPodeEditar, gblPerfil in ["Diligenciador", "Coordenador", "Gestor", "Administrador"]);
Set(gblPodeGestao, gblPerfil in ["Coordenador", "Gestor", "Auditor", "Administrador"]);
Set(gblPodeAdmin, gblPerfil in ["Gestor", "Administrador"]);
""" + CARGA


def cor_situacao(expr):
    return f'Switch({expr}, "Crítico", nfCorVermelho, "Em risco", nfCorLaranja, nfCorVerde)'


def cor_severidade(expr):
    return f'Switch({expr}, "Crítica", nfCorVermelho, "Alta", nfCorLaranja, "Média", nfCorAzul, nfCorSuave)'


def data(expr, fmt="dd/mm/yyyy"):
    return f'If(IsBlank({expr}), "—", Text({expr}, "{fmt}"))'


# ---------------------------------------------------------------------------
# Fábrica de controles
# ---------------------------------------------------------------------------
def f(v):
    """Converte valor Python em fórmula Power Fx ("=..." no YAML)."""
    if isinstance(v, bool):
        return "=" + ("true" if v else "false")
    if isinstance(v, (int, float)):
        return f"={v}"
    return "=" + str(v)


def ctl(nome, tipo, props, filhos=None, variante=None):
    corpo = {"Control": tipo}
    if variante:
        corpo["Variant"] = variante
    corpo["Properties"] = {k: f(v) for k, v in props.items()}
    if filhos:
        corpo["Children"] = filhos
    return {nome: corpo}


def txt(s):
    return '"' + s.replace('"', '""') + '"'


def rotulo(nome, texto, x, y, w, h, tam=11, cor="nfCorTexto", negrito=False, **extra):
    p = {"Text": texto, "X": x, "Y": y, "Width": w, "Height": h, "Size": tam, "Color": cor,
         "Font": "Font.'Segoe UI'"}
    if negrito:
        p["FontWeight"] = "FontWeight.Semibold"
    p.update(extra)
    return ctl(nome, "Label", p)


def retangulo(nome, x, y, w, h, cor="nfCorCartao", **extra):
    p = {"X": x, "Y": y, "Width": w, "Height": h, "Fill": cor}
    p.update(extra)
    return ctl(nome, "Rectangle", p)


def botao(nome, texto, x, y, w, h, on_select, primario=True, **extra):
    p = {"Text": texto, "X": x, "Y": y, "Width": w, "Height": h, "OnSelect": on_select,
         "Fill": "nfCorAzul" if primario else "nfCorCartao",
         "Color": "nfCorCartao" if primario else "nfCorAzul",
         "HoverFill": "nfCorNavy2" if primario else "nfCorFundo",
         "BorderColor": "nfCorAzul", "BorderThickness": 1, "Size": 10,
         "RadiusTopLeft": 4, "RadiusTopRight": 4, "RadiusBottomLeft": 4, "RadiusBottomRight": 4}
    p.update(extra)
    return ctl(nome, "Classic/Button", p)


def entrada(nome, dica, x, y, w, h=36, multilinha=False, padrao='""', **extra):
    p = {"Default": padrao, "HintText": txt(dica), "X": x, "Y": y, "Width": w, "Height": h,
         "Size": 10, "BorderColor": "nfCorLinha"}
    if multilinha:
        p["Mode"] = "TextMode.MultiLine"
    p.update(extra)
    return ctl(nome, "Classic/TextInput", p)


def lista_suspensa(nome, itens, x, y, w, h=36, **extra):
    p = {"Items": itens, "X": x, "Y": y, "Width": w, "Height": h, "Size": 10,
         "BorderColor": "nfCorLinha"}
    p.update(extra)
    return ctl(nome, "Classic/DropDown", p)


def galeria(nome, itens, x, y, w, h, alt_modelo, filhos, **extra):
    p = {"Items": itens, "X": x, "Y": y, "Width": w, "Height": h, "TemplateSize": alt_modelo,
         "TemplatePadding": 0, "ShowScrollbar": True}
    p.update(extra)
    return ctl(nome, "Gallery", p, filhos, variante="BrowseLayout_Vertical_OneTextVariant_ver5.0")


def opcoes_com_todos(tabela_choices, rotulo_todos="(Todos)"):
    return (f'Ungroup(Table({{o: ["{rotulo_todos}"]}}, '
            f'{{o: ShowColumns({tabela_choices}, Value)}}), o)')


def cartao_kpi(sufixo, i, titulo, valor, cor_destaque="nfCorAzul", n=6, y=Y0 + 40):
    larg = f"({LARG_CONTEUDO} - {(n - 1) * 12}) / {n}"
    x = f"{X0} + {i} * ({larg} + 12)"
    return [
        retangulo(f"kpiFundo{i}_{sufixo}", x, y, larg, 92, BorderColor="nfCorLinha", BorderThickness=1),
        retangulo(f"kpiFaixa{i}_{sufixo}", x, y, 4, 92, cor_destaque),
        rotulo(f"kpiTitulo{i}_{sufixo}", txt(titulo), f"{x} + 16", y + 10, f"{larg} - 24", 22, 10, "nfCorSuave"),
        rotulo(f"kpiValor{i}_{sufixo}", valor, f"{x} + 16", y + 36, f"{larg} - 24", 44, 24, cor_destaque, True),
    ]


def cabecalho_e_menu(chave, titulo):
    s = chave.capitalize()
    menu_item = [
        retangulo(f"mnuSel_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 4",
                  f'If(ThisItem.Chave = "{chave}", nfCorNavy2, nfCorNavy)', OnSelect="Select(Parent)"),
        rotulo(f"mnuRotulo_{s}", "ThisItem.Rotulo", 16, 0, "Parent.TemplateWidth - 24",
               "Parent.TemplateHeight - 4", 11, "nfCorCartao", False, OnSelect="Select(Parent)",
               FontWeight=f'If(ThisItem.Chave = "{chave}", FontWeight.Semibold, FontWeight.Normal)'),
    ]
    nav = ("Switch(ThisItem.Chave, " + ", ".join(
        f'"{k}", Navigate({t}, ScreenTransition.None)' for k, t, _, _ in TELAS) + ")")
    return [
        retangulo(f"cabFundo_{s}", 0, 0, "Parent.Width", ALT_CAB, "nfCorNavy"),
        rotulo(f"cabMarca_{s}", txt("DIL · Diligenciamento SAP"), 20, 0, 300, ALT_CAB, 14, "nfCorCartao", True),
        rotulo(f"cabUsuario_{s}", 'User().FullName & "  ·  " & gblPerfil', "Parent.Width - 560", 0, 360,
               ALT_CAB, 10, "nfCorCartao", Align="Align.Right"),
        botao(f"cabAtualizar_{s}", txt("Atualizar dados"), "Parent.Width - 180", 12, 160, 32,
              CARGA + ';\nNotify("Dados atualizados.", NotificationType.Success)', primario=False),
        retangulo(f"menuFundo_{s}", 0, ALT_CAB, LARG_MENU, f"Parent.Height - {ALT_CAB}", "nfCorNavy"),
        galeria(f"galMenu_{s}", "Filter(nfMenu, Perfis = \"*\" || gblPerfil in Split(Perfis, \",\"))",
                0, ALT_CAB + 12, LARG_MENU, f"Parent.Height - {ALT_CAB + 60}", 44, menu_item,
                OnSelect=nav, ShowScrollbar=False),
        rotulo(f"menuRodape_{s}", '"Atualizado " & ' + data("gblCarregadoEm", "dd/mm hh:mm"), 16,
               "Parent.Height - 40", LARG_MENU - 24, 28, 9, "nfCorLinha"),
        rotulo(f"lblTitulo_{s}", txt(titulo), X0, Y_TITULO, LARG_CONTEUDO, 40, 20, "nfCorTexto", True),
    ]


def tela(chave, nome_tela, titulo, filhos, on_visible=None):
    props = {"Fill": "nfCorFundo"}
    if on_visible:
        props["OnVisible"] = on_visible
    return {"Screens": {nome_tela: {"Properties": {k: f(v) for k, v in props.items()},
                                    "Children": cabecalho_e_menu(chave, titulo) + filhos}}}


def historico(entidade, chave_expr, tipo_evento, campo, anterior, novo, obs):
    """Registro manual no DIL_Historico (nunca usa Envio_Email: reservado ao DIL_10)."""
    assert "Envio_Email" not in (tipo_evento + campo)
    return f"""Patch(
        DIL_Historico,
        Defaults(DIL_Historico),
        {{
            Chave_Historico: "HIST-APP-" & Text(Now(), "yyyymmddhhmmss") & "-" & Text(RandBetween(100, 999)),
            Entidade: {{Value: "{entidade}"}},
            Chave_Entidade: {chave_expr},
            Tipo_Evento: {{Value: "{tipo_evento}"}},
            Campo_Alterado: "{campo}",
            Valor_Anterior: {anterior},
            Valor_Novo: {novo},
            Data_Evento: Now(),
            Usuario_Evento: User().FullName,
            Usuario_Evento_Pessoa: nfPessoaAtual,
            Origem_Evento: {{Value: nfOrigemApp}},
            Observacao: {obs}
        }}
    )"""


def gravar(patch_principal, patch_hist, msg_ok, depois):
    """Padrão de gravação: interrompe no primeiro erro e informa o usuário."""
    return f"""IfError(
    {patch_principal},
    Notify("Não foi possível gravar: " & FirstError.Message, NotificationType.Error),
    {patch_hist},
    Notify("Registro gravado, mas o histórico falhou: " & FirstError.Message, NotificationType.Warning),
    Notify("{msg_ok}", NotificationType.Success);
    {depois}
)"""


def linha_cab(sufixo, colunas, y, x0=X0):
    """Rótulos de cabeçalho de grade: colunas = [(titulo, x_rel, largura)]."""
    return [rotulo(f"cab{sufixo}{i}", txt(t), f"{x0} + {x}", y, w, 28, 9, "nfCorSuave", True)
            for i, (t, x, w) in enumerate(colunas)]


# ---------------------------------------------------------------------------
# Telas
# ---------------------------------------------------------------------------
def tela_inicio():
    s = "Inicio"
    abertas = '!(Status_Acao.Value in nfAcaoFechadas)'
    kpis = [
        ("Itens na carteira", "CountRows(colCarteira)", "nfCorAzul"),
        ("Críticos", 'CountRows(Filter(colCarteira, Situacao = "Crítico"))', "nfCorVermelho"),
        ("Em risco", 'CountRows(Filter(colCarteira, Situacao = "Em risco"))', "nfCorLaranja"),
        ("Marcos atrasados", "CountRows(Filter(colMarcos, IsBlank(Data_Realizada) && Data_Planejada < Today()"
                             " && !(Status_Marco.Value in nfMarcoFechados)))", "nfCorLaranja"),
        ("Ações pendentes", f"CountRows(Filter(colAcoes, {abertas}))", "nfCorAzul"),
        ("Minhas ações vencidas", f"CountRows(Filter(colAcoes, Lower(Responsavel_Pessoa.Email) = nfEmailUsuario"
                                  f" && Prazo < Now() && {abertas}))", "nfCorVermelho"),
    ]
    filhos = [rotulo(f"lblSaudacao_{s}", '"Olá, " & User().FirstName & ". Situação em " & Text(Today(), "dd/mm/yyyy") & "."',
                     X0, Y0, LARG_CONTEUDO, 28, 11, "nfCorSuave")]
    for i, (t, v, c) in enumerate(kpis):
        filhos += cartao_kpi(s, i, t, v, c)
    y = Y0 + 150
    larg_esq = f"({LARG_CONTEUDO}) * 0.62"
    x_dir = f"{X0} + ({LARG_CONTEUDO}) * 0.62 + 16"
    larg_dir = f"({LARG_CONTEUDO}) * 0.38 - 16"
    alt = f"Parent.Height - {y + 16}"
    linha_prior = [
        retangulo(f"pFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao",
                  OnSelect="Select(Parent)"),
        retangulo(f"pFaixa_{s}", 0, 0, 4, "Parent.TemplateHeight - 1", cor_situacao("ThisItem.Situacao")),
        rotulo(f"pChave_{s}", '"RC " & ThisItem.Numero_RC & "/" & ThisItem.Item_RC & "  ·  PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO',
               16, 6, "Parent.TemplateWidth - 140", 24, 11, "nfCorTexto", True),
        rotulo(f"pDesc_{s}", 'Coalesce(ThisItem.ItemPO.Descricao_Produto, "—") & "  ·  " & Coalesce(ThisItem.ItemPO.Nome_Fornecedor, "—")',
               16, 30, "Parent.TemplateWidth - 140", 22, 9, "nfCorSuave"),
        rotulo(f"pSit_{s}", "ThisItem.Situacao", "Parent.TemplateWidth - 120", 6, 110, 24, 10,
               cor_situacao("ThisItem.Situacao"), True, Align="Align.Right"),
        rotulo(f"pQtd_{s}", 'ThisItem.QtdOcorrAbertas & " ocorr. · " & ThisItem.QtdAcoesPend & " ações"',
               "Parent.TemplateWidth - 160", 30, 150, 22, 9, "nfCorSuave", Align="Align.Right"),
    ]
    linha_marco = [
        retangulo(f"mFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao"),
        rotulo(f"mData_{s}", data("ThisItem.Data_Planejada", "dd/mm"), 12, 0, 60, "Parent.TemplateHeight - 1",
               12, "nfCorAzul", True),
        rotulo(f"mDesc_{s}", 'Coalesce(ThisItem.Descricao_Marco, "Marco") & Char(10) & "PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO',
               76, 0, "Parent.TemplateWidth - 84", "Parent.TemplateHeight - 1", 9, "nfCorTexto"),
    ]
    filhos += [
        rotulo(f"lblPrior_{s}", txt("Prioridades que exigem atenção"), X0, y, larg_esq, 28, 13, "nfCorTexto", True),
        galeria(f"galPrioridades_{s}",
                'FirstN(Sort(Filter(colCarteira, Situacao <> "Normal"), QtdCriticas * 1000 + QtdMarcosAtrasados * 10 + QtdOcorrAbertas, SortOrder.Descending), 50)',
                X0, y + 32, larg_esq, f"{alt} - 32", 58, linha_prior,
                OnSelect="Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
        rotulo(f"lblPriorVazio_{s}", txt("Nenhum item crítico ou em risco."), X0, y + 40, larg_esq, 28, 10,
               "nfCorSuave", Visible=f"IsEmpty(galPrioridades_{s}.AllItems)"),
        rotulo(f"lblMarcos_{s}", txt("Próximos marcos (15 dias)"), x_dir, y, larg_dir, 28, 13, "nfCorTexto", True),
        galeria(f"galMarcos_{s}",
                "Sort(Filter(colMarcos, IsBlank(Data_Realizada) && Data_Planejada >= Today() && Data_Planejada <= DateAdd(Today(), 15, TimeUnit.Days)), Data_Planejada)",
                x_dir, y + 32, larg_dir, f"{alt} - 32", 48, linha_marco),
    ]
    return tela("inicio", "scrInicio", "Central do Diligenciador", filhos)


def tela_carteira():
    s = "Carteira"
    yf = Y0
    filhos = [
        entrada(f"txtBusca_{s}", "Buscar RC, PO, material, descrição ou fornecedor", X0, yf, 360),
        lista_suspensa(f"ddSituacao_{s}", '["(Todos)", "Crítico", "Em risco", "Normal"]', X0 + 372, yf, 150),
        lista_suspensa(f"ddFornecedor_{s}",
                       'Ungroup(Table({o: ["(Todos)"]}, {o: Sort(Distinct(colCarteira, ItemPO.Nome_Fornecedor), Value)}), o)',
                       X0 + 534, yf, 260),
        lista_suspensa(f"ddEscopo_{s}", '["Todos os itens", "Com ações sob minha responsabilidade"]',
                       X0 + 806, yf, 300),
    ]
    cols = [("RC / item", 16, 130), ("PO / item", 150, 130), ("Material · descrição", 284, 330),
            ("Fornecedor", 620, 240), ("1ª remessa", 864, 90), ("Pendências", 958, 170), ("Situação", 1132, 100)]
    filhos += linha_cab(s, cols, yf + 48)
    linha = [
        retangulo(f"cFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao",
                  OnSelect="Select(Parent)"),
        retangulo(f"cFaixa_{s}", 0, 0, 4, "Parent.TemplateHeight - 1", cor_situacao("ThisItem.Situacao")),
        rotulo(f"cRC_{s}", 'ThisItem.Numero_RC & " / " & ThisItem.Item_RC', 16, 0, 130, "Parent.TemplateHeight", 10, "nfCorTexto", True),
        rotulo(f"cPO_{s}", 'ThisItem.Numero_PO & " / " & ThisItem.Item_PO', 150, 0, 130, "Parent.TemplateHeight", 10, "nfCorTexto", True),
        rotulo(f"cDesc_{s}", 'Coalesce(ThisItem.ItemPO.Material, "—") & " · " & Coalesce(ThisItem.ItemPO.Descricao_Produto, "—")',
               284, 0, 330, "Parent.TemplateHeight", 9, "nfCorTexto"),
        rotulo(f"cForn_{s}", 'Coalesce(ThisItem.ItemPO.Nome_Fornecedor, "—")', 620, 0, 240, "Parent.TemplateHeight", 9, "nfCorTexto"),
        rotulo(f"cRem_{s}", data("ThisItem.ItemPO.Data_1_Remessa_Item_SAP"), 864, 0, 90, "Parent.TemplateHeight", 9, "nfCorTexto"),
        rotulo(f"cPend_{s}", 'ThisItem.QtdOcorrAbertas & " ocorr. · " & ThisItem.QtdAcoesPend & " ações · " & ThisItem.QtdMarcosAtrasados & " marcos atras."',
               958, 0, 170, "Parent.TemplateHeight", 9, "nfCorSuave"),
        rotulo(f"cSit_{s}", "ThisItem.Situacao", 1132, 0, 100, "Parent.TemplateHeight", 10,
               cor_situacao("ThisItem.Situacao"), True),
    ]
    itens = f"""Filter(
    colCarteira As c,
    IsBlank(txtBusca_{s}.Text) || txtBusca_{s}.Text in c.Numero_RC || txtBusca_{s}.Text in c.Numero_PO
        || txtBusca_{s}.Text in c.ItemPO.Material || txtBusca_{s}.Text in c.ItemPO.Descricao_Produto
        || txtBusca_{s}.Text in c.ItemPO.Nome_Fornecedor,
    ddSituacao_{s}.Selected.Value = "(Todos)" || c.Situacao = ddSituacao_{s}.Selected.Value,
    ddFornecedor_{s}.Selected.Value = "(Todos)" || c.ItemPO.Nome_Fornecedor = ddFornecedor_{s}.Selected.Value,
    ddEscopo_{s}.Selected.Value = "Todos os itens"
        || CountRows(Filter(colAcoes, Numero_PO = c.Numero_PO && Item_PO = c.Item_PO && Lower(Responsavel_Pessoa.Email) = nfEmailUsuario)) > 0
)"""
    filhos += [
        galeria(f"galCarteira_{s}", itens, X0, yf + 78, LARG_CONTEUDO, f"Parent.Height - {yf + 78 + 44}", 52, linha,
                OnSelect="Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
        rotulo(f"lblTotal_{s}", f'CountRows(galCarteira_{s}.AllItems) & " de " & CountRows(colCarteira) & " itens (RC/PO vinculados e ativos)"',
               X0, "Parent.Height - 40", LARG_CONTEUDO, 28, 9, "nfCorSuave"),
    ]
    return tela("carteira", "scrCarteira", "Minha carteira", filhos)


def tela_dossie():
    s = "Dossie"
    filtro_item = "Numero_PO = gblItem.Numero_PO && Item_PO = gblItem.Item_PO"
    on_visible = f"""If(
    IsBlank(gblItem),
    Notify("Selecione um item na carteira.", NotificationType.Information); Navigate(scrCarteira, ScreenTransition.None),
    ClearCollect(colDosMarcos, Filter(colMarcos, {filtro_item}));
    ClearCollect(colDosOcor, Filter(colOcor, {filtro_item}));
    ClearCollect(colDosAcoes, Filter(colAcoes, {filtro_item}));
    ClearCollect(colDosComun, Filter(colComun, {filtro_item}));
    ClearCollect(
        colDosChaves,
        ForAll(colDosMarcos, {{Chave: Chave_Marco}}),
        ForAll(colDosOcor, {{Chave: Chave_Ocorrencia}}),
        ForAll(colDosAcoes, {{Chave: Chave_Acao}}),
        ForAll(colDosComun, {{Chave: Chave_Comunicacao}})
    );
    Clear(colDosHist);
    ForAll(colDosChaves As k, Collect(colDosHist, Filter(DIL_Historico, Chave_Entidade = k.Chave)));
    UpdateContext({{locAba: Coalesce(locAba, "Resumo")}})
)"""
    yc = Y0 + 96
    filhos = [
        retangulo(f"dCab_{s}", X0, Y0, LARG_CONTEUDO, 84, BorderColor="nfCorLinha", BorderThickness=1),
        retangulo(f"dCabFaixa_{s}", X0, Y0, 4, 84, cor_situacao("gblItem.Situacao")),
        rotulo(f"dIdent_{s}", '"RC " & gblItem.Numero_RC & " / " & gblItem.Item_RC & "   ·   PO " & gblItem.Numero_PO & " / " & gblItem.Item_PO',
               X0 + 16, Y0 + 8, f"{LARG_CONTEUDO} - 420", 30, 15, "nfCorTexto", True),
        rotulo(f"dDesc_{s}", 'Coalesce(gblItem.ItemPO.Material, "—") & " · " & Coalesce(gblItem.ItemPO.Descricao_Produto, "—") & "  ·  " & Coalesce(gblItem.ItemPO.Nome_Fornecedor, "—")',
               X0 + 16, Y0 + 44, f"{LARG_CONTEUDO} - 420", 28, 10, "nfCorSuave"),
        rotulo(f"dSit_{s}", "gblItem.Situacao", f"{X0} + {LARG_CONTEUDO} - 400", Y0 + 8, 120, 30, 13,
               cor_situacao("gblItem.Situacao"), True),
        botao(f"btnNovaOcor_{s}", txt("Registrar ocorrência"), f"{X0} + {LARG_CONTEUDO} - 270", Y0 + 8, 250, 32,
              'Set(gblPrefill, gblItem); Set(gblAbrirNova, true); Navigate(scrOcorrencias, ScreenTransition.None)',
              Visible="gblPodeEditar"),
        botao(f"btnVoltar_{s}", txt("‹ Voltar à carteira"), f"{X0} + {LARG_CONTEUDO} - 270", Y0 + 46, 250, 30,
              "Navigate(scrCarteira, ScreenTransition.None)", primario=False),
    ]
    abas = ["Resumo", "Marcos", "Ocorrências", "Ações", "Comunicações", "Histórico"]
    for i, a in enumerate(abas):
        filhos.append(botao(f"btnAba{i}_{s}", txt(a), f"{X0} + {i * 150}", yc, 144, 34,
                            f'UpdateContext({{locAba: "{a}"}})', primario=False,
                            Fill=f'If(locAba = "{a}", nfCorAzul, nfCorCartao)',
                            Color=f'If(locAba = "{a}", nfCorCartao, nfCorAzul)'))
    yg = yc + 46
    alt = f"Parent.Height - {yg + 16}"

    resumo = [
        ("Status de liberação do PO", 'Coalesce(gblItem.ItemPO.Status_Liberacao_PO, "—")'),
        ("Status EF / EM (SAP)", 'Coalesce(gblItem.ItemPO.Status_EF, "—") & " / " & Coalesce(gblItem.ItemPO.Status_EM, "—")'),
        ("Data 1ª remessa (SAP)", data("gblItem.ItemPO.Data_1_Remessa_Item_SAP")),
        ("Código do fornecedor", 'Coalesce(gblItem.ItemPO.Codigo_Fornecedor, "—")'),
        ("Ocorrências abertas", "gblItem.QtdOcorrAbertas"),
        ("Ações pendentes", "gblItem.QtdAcoesPend"),
        ("Marcos atrasados", "gblItem.QtdMarcosAtrasados"),
        ("Tipo / origem do vínculo RC×PO", 'Coalesce(gblItem.Tipo_Vinculo.Value, "—") & " / " & Coalesce(gblItem.Origem_Vinculo.Value, "—")'),
    ]
    for i, (t, v) in enumerate(resumo):
        yy = yg + i * 44
        filhos += [
            rotulo(f"rTit{i}_{s}", txt(t), X0, yy, 280, 40, 10, "nfCorSuave", Visible='locAba = "Resumo"'),
            rotulo(f"rVal{i}_{s}", v, X0 + 290, yy, 500, 40, 11, "nfCorTexto", True, Visible='locAba = "Resumo"'),
        ]

    def grade(nome, itens, aba, linhas, alt_modelo=56):
        return galeria(f"gal{nome}_{s}", itens, X0, yg, LARG_CONTEUDO, alt, alt_modelo, linhas,
                       Visible=f'locAba = "{aba}"')

    def fundo(n):
        return retangulo(f"{n}Fundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao")

    filhos += [
        grade("Marcos", "Sort(colDosMarcos, Data_Planejada)", "Marcos", [
            fundo("dm"),
            rotulo(f"dmData_{s}", '"Plan.: " & ' + data("ThisItem.Data_Planejada") + ' & Char(10) & "Real.: " & ' + data("ThisItem.Data_Realizada"),
                   12, 0, 150, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"dmDesc_{s}", 'Coalesce(ThisItem.Descricao_Marco, "—")', 170, 0, "Parent.TemplateWidth - 520",
                   "Parent.TemplateHeight", 10, "nfCorTexto", True),
            rotulo(f"dmStatus_{s}", 'Coalesce(ThisItem.Status_Marco.Value, "—")', "Parent.TemplateWidth - 340", 0, 120,
                   "Parent.TemplateHeight", 10, "nfCorAzul", True),
            rotulo(f"dmResp_{s}", 'Coalesce(ThisItem.Responsavel_Pessoa.DisplayName, "—")', "Parent.TemplateWidth - 210", 0, 200,
                   "Parent.TemplateHeight", 9, "nfCorSuave"),
        ]),
        grade("Ocor", "Sort(colDosOcor, Data_Ocorrencia, SortOrder.Descending)", "Ocorrências", [
            fundo("do"),
            rotulo(f"doData_{s}", data("ThisItem.Data_Ocorrencia"), 12, 0, 90, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"doSev_{s}", 'Coalesce(ThisItem.Severidade.Value, "—")', 104, 0, 80, "Parent.TemplateHeight", 10,
                   cor_severidade("ThisItem.Severidade.Value"), True),
            rotulo(f"doDesc_{s}", 'Coalesce(ThisItem.Tipo_Ocorrencia.Value, "—") & " · " & Coalesce(ThisItem.Descricao, "")',
                   190, 0, "Parent.TemplateWidth - 340", "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"doStatus_{s}", 'Coalesce(ThisItem.Status_Ocorrencia.Value, "—")', "Parent.TemplateWidth - 140", 0, 130,
                   "Parent.TemplateHeight", 10, "nfCorAzul", True),
        ]),
        grade("Acoes", "Sort(colDosAcoes, Prazo)", "Ações", [
            fundo("da"),
            rotulo(f"daPrazo_{s}", data("ThisItem.Prazo"), 12, 0, 90, "Parent.TemplateHeight", 10,
                   'If(ThisItem.Prazo < Now() && !(ThisItem.Status_Acao.Value in nfAcaoFechadas), nfCorVermelho, nfCorTexto)', True),
            rotulo(f"daDesc_{s}", 'Coalesce(ThisItem.Tipo_Acao.Value, "—") & " · " & Coalesce(ThisItem.Descricao_Acao, "")',
                   104, 0, "Parent.TemplateWidth - 470", "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"daResp_{s}", 'Coalesce(ThisItem.Responsavel_Pessoa.DisplayName, ThisItem.Responsavel, "—")',
                   "Parent.TemplateWidth - 360", 0, 200, "Parent.TemplateHeight", 9, "nfCorSuave"),
            rotulo(f"daStatus_{s}", 'Coalesce(ThisItem.Status_Acao.Value, "—") & " · " & Coalesce(ThisItem.Prioridade.Value, "—")',
                   "Parent.TemplateWidth - 150", 0, 140, "Parent.TemplateHeight", 10, "nfCorAzul", True),
        ]),
        grade("Comun", "Sort(colDosComun, Data_Comunicacao, SortOrder.Descending)", "Comunicações", [
            fundo("dc"),
            rotulo(f"dcData_{s}", data("ThisItem.Data_Comunicacao", "dd/mm/yyyy hh:mm"), 12, 0, 120, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"dcAssunto_{s}", 'Coalesce(ThisItem.Assunto, "—")', 136, 0, "Parent.TemplateWidth - 400",
                   "Parent.TemplateHeight", 10, "nfCorTexto", True),
            rotulo(f"dcEnvio_{s}", 'If(IsBlank(LookUp(colEnvios, Chave_Entidade = ThisItem.Chave_Comunicacao)), "Sem registro de envio", "E-mail enviado (DIL_10)")',
                   "Parent.TemplateWidth - 250", 0, 240, "Parent.TemplateHeight", 9, "nfCorSuave"),
        ]),
        grade("Hist", "Sort(colDosHist, Data_Evento, SortOrder.Descending)", "Histórico", [
            fundo("dh"),
            rotulo(f"dhData_{s}", data("ThisItem.Data_Evento", "dd/mm/yyyy hh:mm"), 12, 0, 120, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"dhTipo_{s}", 'Coalesce(ThisItem.Entidade.Value, "—") & " · " & Coalesce(ThisItem.Tipo_Evento.Value, "—")',
                   136, 0, 200, "Parent.TemplateHeight", 9, "nfCorAzul", True),
            rotulo(f"dhDesc_{s}", 'Coalesce(ThisItem.Campo_Alterado, "") & ": " & Coalesce(ThisItem.Valor_Anterior, "∅") & " → " & Coalesce(ThisItem.Valor_Novo, "∅") & Char(10) & Coalesce(ThisItem.Observacao, "")',
                   340, 0, "Parent.TemplateWidth - 350", "Parent.TemplateHeight", 9, "nfCorTexto"),
        ], 60),
    ]
    return tela("dossie", "scrDossie", "Dossiê do processo", filhos, on_visible)


def tela_ocorrencias():
    s = "Ocor"
    yf = Y0
    filhos = [
        entrada(f"txtBusca_{s}", "Buscar por PO, RC ou descrição", X0, yf, 300),
        lista_suspensa(f"ddStatus_{s}", opcoes_com_todos("Choices([@DIL_Ocorrencias].Status_Ocorrencia)"), X0 + 312, yf, 170),
        lista_suspensa(f"ddSev_{s}", opcoes_com_todos("Choices([@DIL_Ocorrencias].Severidade)"), X0 + 494, yf, 150),
        botao(f"btnNova_{s}", txt("+ Nova ocorrência"), f"{X0} + {LARG_CONTEUDO} - 200", yf, 200, 36,
              "Set(gblPrefill, Blank()); UpdateContext({locNova: true})", Visible="gblPodeEditar"),
    ]
    larg_lista = f"({LARG_CONTEUDO}) * 0.58"
    xd = f"{X0} + ({LARG_CONTEUDO}) * 0.58 + 16"
    wd = f"({LARG_CONTEUDO}) * 0.42 - 16"
    yl = yf + 52
    itens = f"""Sort(
    Filter(
        colOcor,
        IsBlank(txtBusca_{s}.Text) || txtBusca_{s}.Text in Numero_PO || txtBusca_{s}.Text in Numero_RC || txtBusca_{s}.Text in Descricao,
        ddStatus_{s}.Selected.Value = "(Todos)" || Status_Ocorrencia.Value = ddStatus_{s}.Selected.Value,
        ddSev_{s}.Selected.Value = "(Todos)" || Severidade.Value = ddSev_{s}.Selected.Value
    ),
    Data_Ocorrencia,
    SortOrder.Descending
)"""
    linha = [
        retangulo(f"oFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        retangulo(f"oFaixa_{s}", 0, 0, 4, "Parent.TemplateHeight - 1", cor_severidade("ThisItem.Severidade.Value")),
        rotulo(f"oTit_{s}", '"PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO & "  ·  " & Coalesce(ThisItem.Tipo_Ocorrencia.Value, "—")',
               14, 4, "Parent.TemplateWidth - 150", 24, 10, "nfCorTexto", True),
        rotulo(f"oDesc_{s}", 'Coalesce(ThisItem.Descricao, "")', 14, 28, "Parent.TemplateWidth - 150", 30, 9, "nfCorSuave"),
        rotulo(f"oSev_{s}", 'Coalesce(ThisItem.Severidade.Value, "—")', "Parent.TemplateWidth - 130", 4, 120, 24, 10,
               cor_severidade("ThisItem.Severidade.Value"), True, Align="Align.Right"),
        rotulo(f"oStatus_{s}", 'Coalesce(ThisItem.Status_Ocorrencia.Value, "—") & " · " & ' + data("ThisItem.Data_Ocorrencia", "dd/mm"),
               "Parent.TemplateWidth - 150", 30, 140, 24, 9, "nfCorSuave", Align="Align.Right"),
    ]
    filhos.append(galeria(f"galOcor_{s}", itens, X0, yl, larg_lista, f"Parent.Height - {yl + 16}", 64, linha))

    sel = f"galOcor_{s}.Selected"
    det = [
        ("Chave", f"{sel}.Chave_Ocorrencia"),
        ("RC / PO", f'"RC " & {sel}.Numero_RC & "/" & {sel}.Item_RC & " · PO " & {sel}.Numero_PO & "/" & {sel}.Item_PO'),
        ("Tipo / severidade", f'Coalesce({sel}.Tipo_Ocorrencia.Value, "—") & " / " & Coalesce({sel}.Severidade.Value, "—")'),
        ("Aberta em", data(f"{sel}.Data_Ocorrencia", "dd/mm/yyyy hh:mm")),
        ("Resolvida em", data(f"{sel}.Data_Resolucao")),
        ("Responsável", f'Coalesce({sel}.Responsavel_Pessoa.DisplayName, {sel}.Responsavel, "—")'),
        ("Ações vinculadas", f'CountRows(Filter(colAcoes, Chave_Ocorrencia = {sel}.Chave_Ocorrencia))'),
    ]
    vis_det = f"!locNova && !IsBlank({sel})"
    filhos.append(retangulo(f"detFundo_{s}", xd, yl, wd, f"Parent.Height - {yl + 16}", BorderColor="nfCorLinha",
                            BorderThickness=1, Visible="!locNova"))
    filhos.append(rotulo(f"detVazio_{s}", txt("Selecione uma ocorrência na lista."), f"{xd} + 16", yl + 16, f"{wd} - 32", 30,
                         10, "nfCorSuave", Visible=f"!locNova && IsBlank({sel})"))
    for i, (t, v) in enumerate(det):
        yy = yl + 12 + i * 42
        filhos += [rotulo(f"detT{i}_{s}", txt(t), f"{xd} + 16", yy, 140, 38, 9, "nfCorSuave", Visible=vis_det),
                   rotulo(f"detV{i}_{s}", v, f"{xd} + 160", yy, f"{wd} - 176", 38, 10, "nfCorTexto", True, Visible=vis_det)]
    ydesc = yl + 12 + len(det) * 42
    filhos.append(rotulo(f"detDesc_{s}", f'Coalesce({sel}.Descricao, "")', f"{xd} + 16", ydesc, f"{wd} - 32", 60, 9,
                         "nfCorTexto", Visible=vis_det, VerticalAlign="VerticalAlign.Top"))
    yst = ydesc + 70
    pode = "gblPodeEditar"
    patch_status = f"""Patch(
        DIL_Ocorrencias,
        LookUp(DIL_Ocorrencias, ID = {sel}.ID),
        {{
            Status_Ocorrencia: ddNovoStatus_{s}.Selected,
            Data_Resolucao: If(ddNovoStatus_{s}.Selected.Value = "Resolvida", Today(), {sel}.Data_Resolucao)
        }}
    )"""
    hist_status = historico("Ocorrência", f"{sel}.Chave_Ocorrencia", "Status", "Status_Ocorrencia",
                            f"{sel}.Status_Ocorrencia.Value", f"ddNovoStatus_{s}.Selected.Value",
                            '"Alteração manual pelo aplicativo."')
    filhos += [
        rotulo(f"lblNovoStatus_{s}", txt("Alterar status"), f"{xd} + 16", yst, 140, 36, 9, "nfCorSuave",
               Visible=f"{vis_det} && {pode}"),
        lista_suspensa(f"ddNovoStatus_{s}", "Choices([@DIL_Ocorrencias].Status_Ocorrencia)", f"{xd} + 160", yst, 170,
                       Visible=f"{vis_det} && {pode}", Default=f"{sel}.Status_Ocorrencia.Value"),
        botao(f"btnSalvarStatus_{s}", txt("Salvar"), f"{xd} + 340", yst, 100, 36,
              gravar(patch_status, hist_status, "Status atualizado.", CARGA),
              Visible=f"{vis_det} && {pode}",
              DisplayMode=f"If(ddNovoStatus_{s}.Selected.Value = {sel}.Status_Ocorrencia.Value, DisplayMode.Disabled, DisplayMode.Edit)"),
        botao(f"btnNovaAcao_{s}", txt("+ Nova ação para esta ocorrência"), f"{xd} + 16", yst + 50, 280, 36,
              f"Set(gblOcorSel, {sel}); Set(gblAbrirNova, true); Navigate(scrAcoes, ScreenTransition.None)",
              Visible=f"{vis_det} && {pode}", primario=False),
    ]

    # Painel "Nova ocorrência"
    vis_n = "locNova"
    itens_item = ('ForAll(colCarteira, {Value: "PO " & Numero_PO & "/" & Item_PO & " · RC " & Numero_RC & "/" & Item_RC, '
                  'Numero_RC: Numero_RC, Item_RC: Item_RC, Numero_PO: Numero_PO, Item_PO: Item_PO, '
                  'Codigo_Fornecedor: ItemPO.Codigo_Fornecedor})')
    padrao_item = ('If(IsBlank(gblPrefill), Blank(), "PO " & gblPrefill.Numero_PO & "/" & gblPrefill.Item_PO & '
                   '" · RC " & gblPrefill.Numero_RC & "/" & gblPrefill.Item_RC)')
    patch_nova = f"""Patch(
        DIL_Ocorrencias,
        Defaults(DIL_Ocorrencias),
        {{
            Chave_Ocorrencia: locChave,
            Numero_RC: ddItem_{s}.Selected.Numero_RC,
            Item_RC: ddItem_{s}.Selected.Item_RC,
            Numero_PO: ddItem_{s}.Selected.Numero_PO,
            Item_PO: ddItem_{s}.Selected.Item_PO,
            Codigo_Fornecedor: ddItem_{s}.Selected.Codigo_Fornecedor,
            Tipo_Ocorrencia: ddTipo_{s}.Selected,
            Severidade: ddSevNova_{s}.Selected,
            Descricao: Trim(txtDesc_{s}.Text),
            Data_Ocorrencia: Now(),
            Status_Ocorrencia: {{Value: "Aberta"}},
            Responsavel: User().FullName,
            Responsavel_Pessoa: nfPessoaAtual,
            Ativo: true
        }}
    )"""
    hist_nova = historico("Ocorrência", "locChave", "Inclusão", "Ocorrência", '""',
                          f'"Severidade=" & ddSevNova_{s}.Selected.Value & "; Status=Aberta"',
                          '"Ocorrência registrada pelo aplicativo."')
    salvar_nova = f"""If(
    IsBlank(ddItem_{s}.Selected) || IsBlank(ddTipo_{s}.Selected) || IsBlank(ddSevNova_{s}.Selected) || IsBlank(Trim(txtDesc_{s}.Text)),
    Notify("Preencha item, tipo, severidade e descrição.", NotificationType.Error),
    UpdateContext({{locChave: "OCOR-APP-" & Text(Now(), "yyyymmddhhmmss") & "-" & Text(RandBetween(100, 999))}});
    {gravar(patch_nova, hist_nova, "Ocorrência registrada.",
            "Reset(txtDesc_" + s + "); UpdateContext({locNova: false}); Set(gblPrefill, Blank());" + chr(10) + CARGA)}
)"""
    campos = [
        ("Item (PO/RC) *", lista_suspensa(f"ddItem_{s}", itens_item, f"{xd} + 150", 0, f"{wd} - 166",
                                          Default=padrao_item, AllowEmptySelection=True, Visible=vis_n)),
        ("Tipo *", lista_suspensa(f"ddTipo_{s}", "Choices([@DIL_Ocorrencias].Tipo_Ocorrencia)", f"{xd} + 150", 0,
                                  f"{wd} - 166", Visible=vis_n)),
        ("Severidade *", lista_suspensa(f"ddSevNova_{s}", "Choices([@DIL_Ocorrencias].Severidade)", f"{xd} + 150", 0,
                                        f"{wd} - 166", Visible=vis_n)),
    ]
    filhos += [
        retangulo(f"novaFundo_{s}", xd, yl, wd, f"Parent.Height - {yl + 16}", BorderColor="nfCorAzul",
                  BorderThickness=1, Visible=vis_n),
        rotulo(f"novaTitulo_{s}", txt("Nova ocorrência"), f"{xd} + 16", yl + 8, f"{wd} - 32", 32, 13, "nfCorTexto", True,
               Visible=vis_n),
    ]
    for i, (t, c) in enumerate(campos):
        yy = yl + 52 + i * 48
        nome = next(iter(c))
        c[nome]["Properties"]["Y"] = f(yy)
        filhos += [rotulo(f"novaT{i}_{s}", txt(t), f"{xd} + 16", yy, 130, 36, 9, "nfCorSuave", Visible=vis_n), c]
    yd = yl + 52 + len(campos) * 48
    filhos += [
        rotulo(f"novaTDesc_{s}", txt("Descrição *"), f"{xd} + 16", yd, 130, 36, 9, "nfCorSuave", Visible=vis_n),
        entrada(f"txtDesc_{s}", "Descreva o fato, impacto e próxima providência", f"{xd} + 150", yd, f"{wd} - 166", 110,
                multilinha=True, Visible=vis_n),
        rotulo(f"novaAviso_{s}", txt("Atenção: ocorrência aberta com ação vinculada entra na regra de escalonamento SLA do fluxo DIL_08."),
               f"{xd} + 16", yd + 120, f"{wd} - 32", 44, 9, "nfCorLaranja", Visible=vis_n),
        botao(f"btnSalvarNova_{s}", txt("Registrar"), f"{xd} + 16", yd + 172, 140, 36, salvar_nova, Visible=vis_n),
        botao(f"btnCancelarNova_{s}", txt("Cancelar"), f"{xd} + 168", yd + 172, 120, 36,
              "UpdateContext({locNova: false}); Set(gblPrefill, Blank())", primario=False, Visible=vis_n),
    ]
    on_visible = "UpdateContext({locNova: gblAbrirNova && gblPodeEditar}); Set(gblAbrirNova, false)"
    return tela("ocorrencias", "scrOcorrencias", "Ocorrências", filhos, on_visible)


def tela_acoes():
    s = "Acoes"
    yf = Y0
    abertas = "!(Status_Acao.Value in nfAcaoFechadas)"
    filhos = [
        lista_suspensa(f"ddEscopo_{s}", '["Minhas ações", "Todas as ações"]', X0, yf, 170,
                       Default='If(gblPerfil = "Diligenciador", "Minhas ações", "Todas as ações")'),
        lista_suspensa(f"ddStatus_{s}", opcoes_com_todos("Choices([@DIL_Acoes].Status_Acao)"), X0 + 182, yf, 160),
        lista_suspensa(f"ddPrio_{s}", opcoes_com_todos("Choices([@DIL_Acoes].Prioridade)"), X0 + 354, yf, 150),
        lista_suspensa(f"ddPrazo_{s}", '["(Todos os prazos)", "Vencidas", "Vencem em 7 dias"]', X0 + 516, yf, 180),
        botao(f"btnNova_{s}", txt("+ Nova ação"), f"{X0} + {LARG_CONTEUDO} - 160", yf, 160, 36,
              "Set(gblOcorSel, Blank()); UpdateContext({locNova: true})", Visible="gblPodeEditar"),
    ]
    itens = f"""Sort(
    Filter(
        colAcoes,
        ddEscopo_{s}.Selected.Value = "Todas as ações" || Lower(Responsavel_Pessoa.Email) = nfEmailUsuario,
        ddStatus_{s}.Selected.Value = "(Todos)" || Status_Acao.Value = ddStatus_{s}.Selected.Value,
        ddPrio_{s}.Selected.Value = "(Todos)" || Prioridade.Value = ddPrio_{s}.Selected.Value,
        ddPrazo_{s}.Selected.Value = "(Todos os prazos)"
            || (ddPrazo_{s}.Selected.Value = "Vencidas" && Prazo < Now() && {abertas})
            || (ddPrazo_{s}.Selected.Value = "Vencem em 7 dias" && Prazo >= Now() && Prazo <= DateAdd(Now(), 7, TimeUnit.Days) && {abertas})
    ),
    Prazo
)"""
    larg_lista = f"({LARG_CONTEUDO}) * 0.58"
    xd = f"{X0} + ({LARG_CONTEUDO}) * 0.58 + 16"
    wd = f"({LARG_CONTEUDO}) * 0.42 - 16"
    yl = yf + 52
    vencida = "ThisItem.Prazo < Now() && !(ThisItem.Status_Acao.Value in nfAcaoFechadas)"
    linha = [
        retangulo(f"aFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        retangulo(f"aFaixa_{s}", 0, 0, 4, "Parent.TemplateHeight - 1", f"If({vencida}, nfCorVermelho, nfCorAzul)"),
        rotulo(f"aTit_{s}", 'Coalesce(ThisItem.Tipo_Acao.Value, "—") & "  ·  PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO',
               14, 4, "Parent.TemplateWidth - 150", 24, 10, "nfCorTexto", True),
        rotulo(f"aDesc_{s}", 'Coalesce(ThisItem.Descricao_Acao, "") & "  —  " & Coalesce(ThisItem.Responsavel_Pessoa.DisplayName, ThisItem.Responsavel, "sem responsável")',
               14, 28, "Parent.TemplateWidth - 150", 30, 9, "nfCorSuave"),
        rotulo(f"aPrazo_{s}", '"Prazo " & ' + data("ThisItem.Prazo"), "Parent.TemplateWidth - 140", 4, 130, 24, 10,
               f"If({vencida}, nfCorVermelho, nfCorTexto)", True, Align="Align.Right"),
        rotulo(f"aStatus_{s}", 'Coalesce(ThisItem.Status_Acao.Value, "—") & " · " & Coalesce(ThisItem.Prioridade.Value, "—")',
               "Parent.TemplateWidth - 160", 30, 150, 24, 9, "nfCorSuave", Align="Align.Right"),
    ]
    filhos.append(galeria(f"galAcoes_{s}", itens, X0, yl, larg_lista, f"Parent.Height - {yl + 16}", 64, linha))

    sel = f"galAcoes_{s}.Selected"
    vis_det = f"!locNova && !IsBlank({sel})"
    pode_editar = (f'gblPodeEditar && (Lower({sel}.Responsavel_Pessoa.Email) = nfEmailUsuario '
                   f'|| gblPerfil in ["Coordenador", "Gestor", "Administrador"])')
    det = [
        ("Chave", f"{sel}.Chave_Acao"),
        ("Ocorrência", f'Coalesce({sel}.Chave_Ocorrencia, "—")'),
        ("Responsável", f'Coalesce({sel}.Responsavel_Pessoa.DisplayName, {sel}.Responsavel, "—")'),
        ("Prazo", data(f"{sel}.Prazo", "dd/mm/yyyy hh:mm")),
        ("Concluída em", data(f"{sel}.Data_Conclusao")),
        ("Descrição", f'Coalesce({sel}.Descricao_Acao, "")'),
    ]
    filhos += [
        retangulo(f"detFundo_{s}", xd, yl, wd, f"Parent.Height - {yl + 16}", BorderColor="nfCorLinha", BorderThickness=1,
                  Visible="!locNova"),
        rotulo(f"detVazio_{s}", txt("Selecione uma ação na lista."), f"{xd} + 16", yl + 16, f"{wd} - 32", 30, 10,
               "nfCorSuave", Visible=f"!locNova && IsBlank({sel})"),
    ]
    for i, (t, v) in enumerate(det):
        yy = yl + 12 + i * 42
        filhos += [rotulo(f"detT{i}_{s}", txt(t), f"{xd} + 16", yy, 120, 38, 9, "nfCorSuave", Visible=vis_det),
                   rotulo(f"detV{i}_{s}", v, f"{xd} + 140", yy, f"{wd} - 156", 38, 10, "nfCorTexto", True, Visible=vis_det)]
    ye = yl + 12 + len(det) * 42 + 8
    patch_and = f"""Patch(
        DIL_Acoes,
        LookUp(DIL_Acoes, ID = {sel}.ID),
        {{
            Status_Acao: ddNovoStatus_{s}.Selected,
            Observacao: txtObs_{s}.Text,
            Data_Conclusao: If(ddNovoStatus_{s}.Selected.Value = "Concluída", Today(), {sel}.Data_Conclusao)
        }}
    )"""
    hist_and = historico("Ação", f"{sel}.Chave_Acao", "Status", "Status_Acao", f"{sel}.Status_Acao.Value",
                         f"ddNovoStatus_{s}.Selected.Value", f'"Andamento: " & txtObs_{s}.Text')
    vis_ed = f"{vis_det} && {pode_editar}"
    filhos += [
        rotulo(f"lblAndamento_{s}", txt("Atualizar andamento"), f"{xd} + 16", ye, f"{wd} - 32", 28, 11, "nfCorTexto", True,
               Visible=vis_ed),
        lista_suspensa(f"ddNovoStatus_{s}", "Choices([@DIL_Acoes].Status_Acao)", f"{xd} + 16", ye + 32, 200,
                       Default=f"{sel}.Status_Acao.Value", Visible=vis_ed),
        entrada(f"txtObs_{s}", "Observação / andamento", f"{xd} + 16", ye + 76, f"{wd} - 32", 80, multilinha=True,
                padrao=f"{sel}.Observacao", Visible=vis_ed),
        botao(f"btnSalvarAnd_{s}", txt("Salvar andamento"), f"{xd} + 16", ye + 166, 180, 36,
              gravar(patch_and, hist_and, "Ação atualizada.", CARGA), Visible=vis_ed),
        rotulo(f"lblSemPermissao_{s}", txt("Somente o responsável ou coordenação pode alterar esta ação."),
               f"{xd} + 16", ye, f"{wd} - 32", 40, 9, "nfCorSuave", Visible=f"{vis_det} && !({pode_editar})"),
    ]

    # Painel "Nova ação"
    vis_n = "locNova"
    itens_ocor = ('ForAll(Filter(colOcor, !(Status_Ocorrencia.Value in nfOcorFechadas)), '
                  '{Value: Chave_Ocorrencia & " · PO " & Numero_PO & "/" & Item_PO, Chave: Chave_Ocorrencia, '
                  'Numero_RC: Numero_RC, Item_RC: Item_RC, Numero_PO: Numero_PO, Item_PO: Item_PO})')
    padrao_ocor = ('If(IsBlank(gblOcorSel), Blank(), gblOcorSel.Chave_Ocorrencia & " · PO " & '
                   'gblOcorSel.Numero_PO & "/" & gblOcorSel.Item_PO)')
    patch_nova = f"""Patch(
        DIL_Acoes,
        Defaults(DIL_Acoes),
        {{
            Chave_Acao: locChave,
            Numero_RC: ddOcor_{s}.Selected.Numero_RC,
            Item_RC: ddOcor_{s}.Selected.Item_RC,
            Numero_PO: ddOcor_{s}.Selected.Numero_PO,
            Item_PO: ddOcor_{s}.Selected.Item_PO,
            Chave_Ocorrencia: ddOcor_{s}.Selected.Chave,
            Tipo_Acao: ddTipo_{s}.Selected,
            Descricao_Acao: Trim(txtDesc_{s}.Text),
            Responsavel: cmbResp_{s}.Selected.DisplayName,
            Responsavel_Pessoa: cmbResp_{s}.Selected,
            Prazo: dtPrazo_{s}.SelectedDate,
            Status_Acao: {{Value: "Aberta"}},
            Prioridade: ddPrioNova_{s}.Selected,
            Ativo: true
        }}
    )"""
    hist_nova = historico("Ação", "locChave", "Inclusão", "Ação", '""',
                          f'"Responsável=" & cmbResp_{s}.Selected.DisplayName & "; Prazo=" & Text(dtPrazo_{s}.SelectedDate, "dd/mm/yyyy")',
                          '"Ação registrada pelo aplicativo."')
    salvar_nova = f"""If(
    IsBlank(ddOcor_{s}.Selected) || IsBlank(ddTipo_{s}.Selected) || IsBlank(cmbResp_{s}.Selected)
        || IsBlank(dtPrazo_{s}.SelectedDate) || IsBlank(Trim(txtDesc_{s}.Text)),
    Notify("Preencha ocorrência, tipo, responsável, prazo e descrição.", NotificationType.Error),
    ddTipo_{s}.Selected.Value = "Escalonamento",
    Notify("Escalonamento é criado somente pelo fluxo DIL_08.", NotificationType.Error),
    UpdateContext({{locChave: "ACAO-APP-" & Text(Now(), "yyyymmddhhmmss") & "-" & Text(RandBetween(100, 999))}});
    {gravar(patch_nova, hist_nova, "Ação registrada.",
            "Reset(txtDesc_" + s + "); UpdateContext({locNova: false}); Set(gblOcorSel, Blank());" + chr(10) + CARGA)}
)"""
    campos = [
        ("Ocorrência *", lista_suspensa(f"ddOcor_{s}", itens_ocor, 0, 0, 0, Default=padrao_ocor,
                                        AllowEmptySelection=True, Visible=vis_n)),
        ("Tipo *", lista_suspensa(f"ddTipo_{s}", 'Filter(Choices([@DIL_Acoes].Tipo_Acao), Value <> "Escalonamento")',
                                  0, 0, 0, Visible=vis_n)),
        ("Prioridade", lista_suspensa(f"ddPrioNova_{s}", "Choices([@DIL_Acoes].Prioridade)", 0, 0, 0, Visible=vis_n)),
        ("Responsável *", ctl(f"cmbResp_{s}", "Classic/ComboBox", {
            "Items": "Choices([@DIL_Acoes].Responsavel_Pessoa)", "DisplayFields": '["DisplayName"]',
            "SearchFields": '["DisplayName", "Email"]', "SelectMultiple": False, "IsSearchable": True,
            "InputTextPlaceholder": txt("Buscar pessoa"), "Height": 36, "Size": 10, "BorderColor": "nfCorLinha",
            "Visible": vis_n})),
        ("Prazo *", ctl(f"dtPrazo_{s}", "Classic/DatePicker", {
            "DefaultDate": "DateAdd(Today(), 2, TimeUnit.Days)", "Height": 36, "Size": 10,
            "BorderColor": "nfCorLinha", "Visible": vis_n})),
    ]
    filhos += [
        retangulo(f"novaFundo_{s}", xd, yl, wd, f"Parent.Height - {yl + 16}", BorderColor="nfCorAzul", BorderThickness=1,
                  Visible=vis_n),
        rotulo(f"novaTitulo_{s}", txt("Nova ação"), f"{xd} + 16", yl + 8, f"{wd} - 32", 32, 13, "nfCorTexto", True, Visible=vis_n),
    ]
    for i, (t, c) in enumerate(campos):
        yy = yl + 48 + i * 46
        nome = next(iter(c))
        pr = c[nome]["Properties"]
        pr["X"], pr["Y"], pr["Width"] = f(f"{xd} + 150"), f(yy), f(f"{wd} - 166")
        filhos += [rotulo(f"novaT{i}_{s}", txt(t), f"{xd} + 16", yy, 130, 36, 9, "nfCorSuave", Visible=vis_n), c]
    yd = yl + 48 + len(campos) * 46
    filhos += [
        rotulo(f"novaTDesc_{s}", txt("Descrição *"), f"{xd} + 16", yd, 130, 36, 9, "nfCorSuave", Visible=vis_n),
        entrada(f"txtDesc_{s}", "O que deve ser feito e o resultado esperado", f"{xd} + 150", yd, f"{wd} - 166", 80,
                multilinha=True, Visible=vis_n),
        botao(f"btnSalvarNova_{s}", txt("Registrar"), f"{xd} + 16", yd + 92, 140, 36, salvar_nova, Visible=vis_n),
        botao(f"btnCancelarNova_{s}", txt("Cancelar"), f"{xd} + 168", yd + 92, 120, 36,
              "UpdateContext({locNova: false}); Set(gblOcorSel, Blank())", primario=False, Visible=vis_n),
    ]
    on_visible = "UpdateContext({locNova: gblAbrirNova && gblPodeEditar}); Set(gblAbrirNova, false)"
    return tela("acoes", "scrAcoes", "Ações", filhos, on_visible)


def tela_agenda():
    s = "Agenda"
    mes_txt = ('Switch(Month(locMes), 1, "Janeiro", 2, "Fevereiro", 3, "Março", 4, "Abril", 5, "Maio", 6, "Junho", '
               '7, "Julho", 8, "Agosto", 9, "Setembro", 10, "Outubro", 11, "Novembro", "Dezembro") & " de " & Year(locMes)')
    larg_cal = f"({LARG_CONTEUDO}) * 0.58"
    xd = f"{X0} + ({LARG_CONTEUDO}) * 0.58 + 16"
    wd = f"({LARG_CONTEUDO}) * 0.42 - 16"
    yf = Y0
    filhos = [
        botao(f"btnMesAnt_{s}", txt("‹"), X0, yf, 44, 36,
              "UpdateContext({locMes: DateAdd(locMes, -1, TimeUnit.Months), locDia: Blank()})", primario=False),
        rotulo(f"lblMes_{s}", mes_txt, X0 + 52, yf, 260, 36, 14, "nfCorTexto", True, Align="Align.Center"),
        botao(f"btnMesProx_{s}", txt("›"), X0 + 320, yf, 44, 36,
              "UpdateContext({locMes: DateAdd(locMes, 1, TimeUnit.Months), locDia: Blank()})", primario=False),
        botao(f"btnHoje_{s}", txt("Hoje"), X0 + 376, yf, 80, 36,
              "UpdateContext({locMes: Date(Year(Today()), Month(Today()), 1), locDia: Today()})", primario=False),
    ]
    dias = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"]
    for i, d in enumerate(dias):
        filhos.append(rotulo(f"lblDia{i}_{s}", txt(d), f"{X0} + {i} * ({larg_cal}) / 7", yf + 48, f"({larg_cal}) / 7", 24,
                             9, "nfCorSuave", True, Align="Align.Center"))
    yc = yf + 76
    mesmo_dia = 'Text(Data_Planejada, "yyyymmdd") = Text(ThisItem.Dia, "yyyymmdd")'
    celula = [
        retangulo(f"calFundo_{s}", 1, 1, "Parent.TemplateWidth - 2", "Parent.TemplateHeight - 2",
                  'If(!IsBlank(locDia) && ThisItem.Dia = locDia, RGBA(209, 224, 250, 1), nfCorCartao)',
                  OnSelect="Select(Parent)"),
        rotulo(f"calNum_{s}", "Day(ThisItem.Dia)", 6, 2, 40, 24, 10,
               'If(Month(ThisItem.Dia) <> Month(locMes), nfCorLinha, ThisItem.Dia = Today(), nfCorAzul, nfCorTexto)',
               True, OnSelect="Select(Parent)"),
        rotulo(f"calQtd_{s}", f'With({{q: CountRows(Filter(colMarcos, {mesmo_dia}))}}, If(q = 0, "", q & If(q = 1, " marco", " marcos")))',
               6, "Parent.TemplateHeight - 28", "Parent.TemplateWidth - 12", 24, 9,
               f'If(CountRows(Filter(colMarcos, {mesmo_dia} && IsBlank(Data_Realizada) && Data_Planejada < Today())) > 0, nfCorVermelho, nfCorAzul)',
               True, OnSelect="Select(Parent)"),
    ]
    filhos.append(ctl(f"galCalendario_{s}", "Gallery", {
        "Items": "With({ini: DateAdd(locMes, 1 - Weekday(locMes), TimeUnit.Days)}, ForAll(Sequence(42, 0), {Dia: DateAdd(ini, Value, TimeUnit.Days)}))",
        "X": X0, "Y": yc, "Width": larg_cal, "Height": f"Parent.Height - {yc + 16}", "WrapCount": 7,
        "TemplateSize": f"(Parent.Height - {yc + 16}) / 6", "TemplatePadding": 0, "ShowScrollbar": False,
        "OnSelect": "UpdateContext({locDia: ThisItem.Dia})"}, celula, variante="BrowseLayout_Vertical_OneTextVariant_ver5.0"))
    itens = """Sort(
    Filter(
        colMarcos,
        If(
            IsBlank(locDia),
            Data_Planejada >= Today() && Data_Planejada <= DateAdd(Today(), 30, TimeUnit.Days),
            Text(Data_Planejada, "yyyymmdd") = Text(locDia, "yyyymmdd")
        )
    ),
    Data_Planejada
)"""
    linha = [
        retangulo(f"agFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao"),
        rotulo(f"agData_{s}", data("ThisItem.Data_Planejada", "dd/mm"), 10, 0, 56, "Parent.TemplateHeight", 12,
               'If(IsBlank(ThisItem.Data_Realizada) && ThisItem.Data_Planejada < Today(), nfCorVermelho, nfCorAzul)', True),
        rotulo(f"agDesc_{s}", 'Coalesce(ThisItem.Descricao_Marco, "Marco") & Char(10) & "PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO & " · " & Coalesce(ThisItem.Status_Marco.Value, "—")',
               70, 0, "Parent.TemplateWidth - 80", "Parent.TemplateHeight", 9, "nfCorTexto"),
    ]
    filhos += [
        rotulo(f"lblLista_{s}", 'If(IsBlank(locDia), "Próximos 30 dias", "Marcos em " & Text(locDia, "dd/mm/yyyy"))',
               xd, yf, f"{wd} - 120", 36, 13, "nfCorTexto", True),
        botao(f"btnLimpar_{s}", txt("Limpar dia"), f"{xd} + {wd} - 110", yf, 110, 36, "UpdateContext({locDia: Blank()})",
              primario=False, Visible="!IsBlank(locDia)"),
        galeria(f"galLista_{s}", itens, xd, yf + 48, wd, f"Parent.Height - {yf + 64}", 52, linha),
        rotulo(f"lblVazio_{s}", txt("Nenhum marco no período."), xd, yf + 56, wd, 28, 10, "nfCorSuave",
               Visible=f"IsEmpty(galLista_{s}.AllItems)"),
    ]
    on_visible = "If(IsBlank(locMes), UpdateContext({locMes: Date(Year(Today()), Month(Today()), 1)}))"
    return tela("agenda", "scrAgenda", "Agenda / Marcos", filhos, on_visible)


def tela_comunicacoes():
    s = "Comun"
    yf = Y0
    filhos = [
        retangulo(f"aviso_{s}", X0, yf, LARG_CONTEUDO, 40, "RGBA(255, 244, 229, 1)", BorderColor="nfCorLaranja",
                  BorderThickness=1),
        rotulo(f"avisoTxt_{s}", txt("Consulta apenas. O envio de e-mails é feito exclusivamente pelo fluxo DIL_10 "
                                    "(comunicações COM-SLA-*), que impede reenvio do mesmo evento."),
               X0 + 12, yf, f"{LARG_CONTEUDO} - 24", 40, 10, "nfCorLaranja"),
        entrada(f"txtBusca_{s}", "Buscar assunto, PO ou RC", X0, yf + 52, 300),
        lista_suspensa(f"ddEnvio_{s}", '["(Todas)", "Enviada pelo DIL_10", "Aguardando DIL_10", "Fora do escopo do DIL_10", "Inativa"]',
                       X0 + 312, yf + 52, 240),
    ]
    status = """If(
            !IsBlank(LookUp(colEnvios, Chave_Entidade = c.Chave_Comunicacao)), "Enviada pelo DIL_10",
            !c.Ativo, "Inativa",
            StartsWith(c.Chave_Comunicacao, "COM-SLA-"), "Aguardando DIL_10",
            "Fora do escopo do DIL_10"
        )"""
    itens = f"""Sort(
    Filter(
        AddColumns(
            colComun As c,
            StatusEnvio, {status},
            DataEnvio, LookUp(colEnvios, Chave_Entidade = c.Chave_Comunicacao, Data_Evento)
        ),
        IsBlank(txtBusca_{s}.Text) || txtBusca_{s}.Text in Assunto || txtBusca_{s}.Text in Numero_PO || txtBusca_{s}.Text in Numero_RC,
        ddEnvio_{s}.Selected.Value = "(Todas)" || StatusEnvio = ddEnvio_{s}.Selected.Value
    ),
    Data_Comunicacao,
    SortOrder.Descending
)"""
    larg_lista = f"({LARG_CONTEUDO}) * 0.55"
    xd = f"{X0} + ({LARG_CONTEUDO}) * 0.55 + 16"
    wd = f"({LARG_CONTEUDO}) * 0.45 - 16"
    yl = yf + 104
    cor_env = 'Switch(ThisItem.StatusEnvio, "Enviada pelo DIL_10", nfCorVerde, "Aguardando DIL_10", nfCorLaranja, nfCorSuave)'
    linha = [
        retangulo(f"cmFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        rotulo(f"cmAssunto_{s}", 'Coalesce(ThisItem.Assunto, "(sem assunto)")', 12, 4, "Parent.TemplateWidth - 24", 24, 10,
               "nfCorTexto", True),
        rotulo(f"cmInfo_{s}", data("ThisItem.Data_Comunicacao", "dd/mm/yyyy hh:mm") + ' & " · " & Coalesce(ThisItem.Tipo_Comunicacao.Value, "—") & " · " & Coalesce(ThisItem.Sentido.Value, "—")',
               12, 28, "Parent.TemplateWidth - 200", 24, 9, "nfCorSuave"),
        rotulo(f"cmStatus_{s}", "ThisItem.StatusEnvio", "Parent.TemplateWidth - 190", 28, 180, 24, 9, cor_env, True,
               Align="Align.Right"),
    ]
    filhos.append(galeria(f"galComun_{s}", itens, X0, yl, larg_lista, f"Parent.Height - {yl + 16}", 58, linha))
    sel = f"galComun_{s}.Selected"
    vis = f"!IsBlank({sel})"
    det = [
        ("Chave", f"{sel}.Chave_Comunicacao"),
        ("RC / PO", f'"RC " & {sel}.Numero_RC & "/" & {sel}.Item_RC & " · PO " & {sel}.Numero_PO & "/" & {sel}.Item_PO'),
        ("Situação do envio", f'{sel}.StatusEnvio & If(IsBlank({sel}.DataEnvio), "", " em " & Text({sel}.DataEnvio, "dd/mm/yyyy hh:mm"))'),
        ("Responsável", f'Coalesce({sel}.Responsavel_Pessoa.DisplayName, {sel}.Responsavel, "—")'),
        ("Assunto", f'Coalesce({sel}.Assunto, "")'),
    ]
    filhos += [
        retangulo(f"detFundo_{s}", xd, yl, wd, f"Parent.Height - {yl + 16}", BorderColor="nfCorLinha", BorderThickness=1),
        rotulo(f"detVazio_{s}", txt("Selecione uma comunicação."), f"{xd} + 16", yl + 16, f"{wd} - 32", 30, 10, "nfCorSuave",
               Visible=f"IsBlank({sel})"),
    ]
    for i, (t, v) in enumerate(det):
        yy = yl + 12 + i * 42
        filhos += [rotulo(f"detT{i}_{s}", txt(t), f"{xd} + 16", yy, 130, 38, 9, "nfCorSuave", Visible=vis),
                   rotulo(f"detV{i}_{s}", v, f"{xd} + 150", yy, f"{wd} - 166", 38, 10, "nfCorTexto", True, Visible=vis)]
    yr = yl + 12 + len(det) * 42
    filhos += [
        rotulo(f"detTResumo_{s}", txt("Conteúdo registrado (corpo usado pelo DIL_10)"), f"{xd} + 16", yr, f"{wd} - 32", 28,
               9, "nfCorSuave", Visible=vis),
        rotulo(f"detResumo_{s}", f'Coalesce({sel}.Resumo, "")', f"{xd} + 16", yr + 30, f"{wd} - 32",
               f"Parent.Height - {yr + 30 + 70}", 10, "nfCorTexto", Visible=vis, VerticalAlign="VerticalAlign.Top"),
        botao(f"btnAnexo_{s}", txt("Abrir anexo"), f"{xd} + 16", "Parent.Height - 66", 150, 36,
              f"Launch({sel}.Anexo_Link)", primario=False,
              Visible=f'{vis} && StartsWith(Coalesce({sel}.Anexo_Link, ""), "https://")'),
    ]
    return tela("comunicacoes", "scrComunicacoes", "Comunicações", filhos)


def barras(s, n, titulo, itens, x, y, w, h):
    """Gráfico de barras horizontais simples (Items com colunas Rotulo, Qtd, Max)."""
    linha = [
        rotulo(f"b{n}Rot_{s}", "ThisItem.Rotulo", 0, 0, 170, "Parent.TemplateHeight", 9, "nfCorTexto"),
        retangulo(f"b{n}Barra_{s}", 176, 8, "Max(2, (Parent.TemplateWidth - 230) * ThisItem.Qtd / Max(1, ThisItem.Max))",
                  "Parent.TemplateHeight - 16", "nfCorAzul"),
        rotulo(f"b{n}Qtd_{s}", "ThisItem.Qtd", "Parent.TemplateWidth - 50", 0, 48, "Parent.TemplateHeight", 10,
               "nfCorTexto", True, Align="Align.Right"),
    ]
    return [
        retangulo(f"b{n}Fundo_{s}", x, y, w, h, BorderColor="nfCorLinha", BorderThickness=1),
        rotulo(f"b{n}Titulo_{s}", txt(titulo), f"{x} + 12", f"{y} + 6", f"{w} - 24", 28, 11, "nfCorTexto", True),
        galeria(f"galB{n}_{s}", itens, f"{x} + 12", f"{y} + 40", f"{w} - 24", f"{h} - 50", 32, linha, ShowScrollbar=True),
    ]


def agrupar(origem, expr_rotulo):
    return (f'With({{t: AddColumns(GroupBy(AddColumns({origem}, Rotulo, {expr_rotulo}), Rotulo, Itens), Qtd, CountRows(Itens))}}, '
            f'Sort(AddColumns(t, Max, Max(t, Qtd)), Qtd, SortOrder.Descending))')


def tela_gestao():
    s = "Gestao"
    abertas_oc = "Filter(colOcor, !(Status_Ocorrencia.Value in nfOcorFechadas))"
    abertas_ac = "Filter(colAcoes, !(Status_Acao.Value in nfAcaoFechadas))"
    kpis = [
        ("Itens na carteira", "CountRows(colCarteira)", "nfCorAzul"),
        ("Ocorrências abertas", f"CountRows({abertas_oc})", "nfCorLaranja"),
        ("Ações vencidas", f"CountRows(Filter({abertas_ac}, Prazo < Now()))", "nfCorVermelho"),
        ("Escalonamentos SLA abertos", f'CountRows(Filter({abertas_ac}, Tipo_Acao.Value = "Escalonamento"))', "nfCorVermelho"),
        ("E-mails enviados (DIL_10)", "CountRows(colEnvios)", "nfCorVerde"),
    ]
    filhos = []
    for i, (t, v, c) in enumerate(kpis):
        filhos += cartao_kpi(s, i, t, v, c, n=5, y=Y0)
    y = Y0 + 108
    w = f"({LARG_CONTEUDO} - 32) / 3"
    h = f"Parent.Height - {y + 70}"
    filhos += barras(s, 1, "Carteira por situação", agrupar("colCarteira", "Situacao"), X0, y, w, h)
    filhos += barras(s, 2, "Ocorrências abertas por severidade",
                     agrupar(abertas_oc, 'Coalesce(Severidade.Value, "(sem severidade)")'), f"{X0} + {w} + 16", y, w, h)
    filhos += barras(s, 3, "Ações pendentes por responsável",
                     agrupar(abertas_ac, 'Coalesce(Responsavel_Pessoa.DisplayName, Responsavel, "(sem responsável)")'),
                     f"{X0} + 2 * ({w} + 16)", y, w, h)
    filhos.append(rotulo(f"lblBI_{s}", txt("Escopo: registros ativos carregados no app (limite de delegação). "
                                           "Tendências e indicadores executivos: relatório Power BI (pendente de publicação)."),
                         X0, "Parent.Height - 56", LARG_CONTEUDO, 40, 9, "nfCorSuave"))
    on_visible = 'If(!gblPodeGestao, Notify("Acesso restrito ao perfil de gestão.", NotificationType.Warning); Navigate(scrInicio, ScreenTransition.None))'
    return tela("gestao", "scrGestao", "Visão da gestão", filhos, on_visible)


def tela_admin():
    s = "Admin"
    on_visible = """If(
    !gblPodeAdmin,
    Notify("Acesso restrito à administração.", NotificationType.Warning); Navigate(scrInicio, ScreenTransition.None),
    Concurrent(
        ClearCollect(colExec, FirstN(Sort(DIL_Integracao_Execucao, ID, SortOrder.Descending), 30)),
        ClearCollect(colConfig, DIL_Configuracao),
        ClearCollect(colSLA, DIL_SLA),
        ClearCollect(colHistRecente, FirstN(Sort(DIL_Historico, ID, SortOrder.Descending), 100))
    );
    UpdateContext({locAba: Coalesce(locAba, "Integrações")})
)"""
    abas = ["Integrações", "Parâmetros e perfis", "Regras SLA", "Modelos de e-mail", "Auditoria"]
    filhos = []
    for i, a in enumerate(abas):
        filhos.append(botao(f"btnAba{i}_{s}", txt(a), f"{X0} + {i * 180}", Y0, 174, 34, f'UpdateContext({{locAba: "{a}"}})',
                            primario=False, Fill=f'If(locAba = "{a}", nfCorAzul, nfCorCartao)',
                            Color=f'If(locAba = "{a}", nfCorCartao, nfCorAzul)'))
    yg = Y0 + 48
    alt = f"Parent.Height - {yg + 16}"

    def fundo(n):
        return retangulo(f"{n}Fundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao")

    def info(nome, aba, texto, y=yg, h=60):
        return rotulo(f"{nome}_{s}", txt(texto), X0, y, LARG_CONTEUDO, h, 10, "nfCorTexto",
                      Visible=f'locAba = "{aba}"', VerticalAlign="VerticalAlign.Top")

    filhos += [
        galeria(f"galExec_{s}", "colExec", X0, yg, LARG_CONTEUDO, alt, 56, [
            fundo("ex"),
            rotulo(f"exChave_{s}", 'ThisItem.Chave_Execucao & Char(10) & Coalesce(ThisItem.Tipo_Integracao.Value, "—")',
                   12, 0, 260, "Parent.TemplateHeight", 9, "nfCorTexto", True),
            rotulo(f"exStatus_{s}", 'Coalesce(ThisItem.Status_Execucao.Value, "—")', 280, 0, 110, "Parent.TemplateHeight", 10,
                   'Switch(ThisItem.Status_Execucao.Value, "Concluída", nfCorVerde, "Iniciada", nfCorAzul, nfCorVermelho)', True),
            rotulo(f"exDatas_{s}", '"Início " & ' + data("ThisItem.Data_Inicio", "dd/mm hh:mm") + ' & Char(10) & "Fim " & ' + data("ThisItem.Data_Fim", "dd/mm hh:mm"),
                   396, 0, 150, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"exQtd_{s}", '"Lidos " & ThisItem.Registros_Lidos & " · Proc. " & ThisItem.Registros_Processados & " · Erro " & ThisItem.Registros_Erro',
                   552, 0, 230, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"exMsg_{s}", 'Coalesce(ThisItem.Mensagem, "")', 788, 0, "Parent.TemplateWidth - 800", "Parent.TemplateHeight",
                   9, "nfCorSuave"),
        ], Visible='locAba = "Integrações"'),
        info("infoParam", "Parâmetros e perfis",
             "Perfis (proposta, a aprovar): linhas em DIL_Configuracao com Categoria = \"Perfil_Usuario\", "
             "Nome_Parametro = e-mail do usuário em minúsculas e Valor_Texto = Diligenciador, Coordenador, Gestor, Auditor "
             "ou Administrador. Sem linha, o perfil é Diligenciador. O menu por perfil NÃO substitui as permissões "
             "SharePoint das listas. Edição pelo app: pendente de aprovação (somente leitura nesta versão).", h=80),
        galeria(f"galConfig_{s}", "colConfig", X0, yg + 90, LARG_CONTEUDO, f"{alt} - 90", 44, [
            fundo("cf"),
            rotulo(f"cfCat_{s}", 'Coalesce(ThisItem.Categoria, "—")', 12, 0, 200, "Parent.TemplateHeight", 9, "nfCorSuave", True),
            rotulo(f"cfNome_{s}", 'Coalesce(ThisItem.Nome_Parametro, "—")', 220, 0, 320, "Parent.TemplateHeight", 10, "nfCorTexto", True),
            rotulo(f"cfValor_{s}", 'Coalesce(ThisItem.Valor_Texto, "—")', 548, 0, "Parent.TemplateWidth - 560", "Parent.TemplateHeight",
                   10, "nfCorTexto"),
        ], Visible='locAba = "Parâmetros e perfis"'),
        info("infoSLA", "Regras SLA",
             "Importante: o fluxo DIL_08 calcula o escalonamento com horas fixas no próprio fluxo "
             "(Crítica 4 h, Alta 8 h, Média 24 h, demais 48 h, contadas de Data_Ocorrencia). "
             "A lista DIL_SLA é consultada somente pelo DIL_09 para escolher o canal. Alterar DIL_SLA não muda o prazo "
             "de escalonamento; essa mudança exige alteração planejada do DIL_08 (pendente).", h=80),
        galeria(f"galSLA_{s}", "colSLA", X0, yg + 90, LARG_CONTEUDO, f"{alt} - 90", 44, [
            fundo("sl"),
            rotulo(f"slChave_{s}", 'Coalesce(ThisItem.Chave_SLA, "—")', 12, 0, 260, "Parent.TemplateHeight", 10, "nfCorTexto", True),
            rotulo(f"slNome_{s}", 'Coalesce(ThisItem.Nome_SLA, "—")', 280, 0, "Parent.TemplateWidth - 290", "Parent.TemplateHeight",
                   10, "nfCorTexto"),
        ], Visible='locAba = "Regras SLA"'),
        info("infoModelos", "Modelos de e-mail",
             "Situação atual: o DIL_10 envia o Assunto e o Resumo gravados em cada comunicação COM-SLA-* "
             "(gerada pelo DIL_09) e registra Envio_Email no DIL_Historico para não reenviar. "
             "Modelos editáveis são uma mudança separada no DIL_10 (verificar esquema real, buscar modelo, substituir "
             "variáveis, testar envio e idempotência, aprovar e documentar rollback). Pendente de aprovação.", h=120),
        galeria(f"galAud_{s}", "colHistRecente", X0, yg, LARG_CONTEUDO, alt, 52, [
            fundo("au"),
            rotulo(f"auData_{s}", data("ThisItem.Data_Evento", "dd/mm/yyyy hh:mm"), 12, 0, 120, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"auTipo_{s}", 'Coalesce(ThisItem.Origem_Evento.Value, "—") & Char(10) & Coalesce(ThisItem.Tipo_Evento.Value, "—")',
                   136, 0, 150, "Parent.TemplateHeight", 9, "nfCorAzul", True),
            rotulo(f"auDesc_{s}", 'ThisItem.Chave_Entidade & Char(10) & Coalesce(ThisItem.Observacao, "")', 292, 0,
                   "Parent.TemplateWidth - 300", "Parent.TemplateHeight", 9, "nfCorTexto"),
        ], Visible='locAba = "Auditoria"'),
    ]
    return tela("admin", "scrAdmin", "Administração e configurações", filhos, on_visible)


# ---------------------------------------------------------------------------
# Serialização
# ---------------------------------------------------------------------------
class Dumper(yaml.SafeDumper):
    pass


def _str(dumper, data):
    estilo = "|" if "\n" in data else None
    return dumper.represent_scalar("tag:yaml.org,2002:str", data, style=estilo)


Dumper.add_representer(str, _str)


def escrever(nome, obj):
    texto = yaml.dump(obj, Dumper=Dumper, sort_keys=False, allow_unicode=True, width=10_000)
    (SAIDA / nome).write_text(AVISO + texto, encoding="utf-8")


def main():
    SAIDA.mkdir(parents=True, exist_ok=True)
    for antigo in SAIDA.glob("*.pa.yaml"):
        antigo.unlink()
    escrever("App.pa.yaml", {"App": {"Properties": {
        "Formulas": f(FORMULAS_NOMEADAS),
        "OnStart": f(ON_START),
        "StartScreen": f("scrInicio"),
    }}})
    telas = [tela_inicio(), tela_carteira(), tela_dossie(), tela_ocorrencias(), tela_acoes(),
             tela_agenda(), tela_comunicacoes(), tela_gestao(), tela_admin()]
    for t in telas:
        nome = next(iter(t["Screens"]))
        escrever(f"{nome}.pa.yaml", t)
    escrever("_EditorState.pa.yaml", {"EditorState": {"ScreensOrder": [t for _, t, _, _ in TELAS]}})
    print("OK:", len(telas), "telas geradas em", SAIDA.relative_to(RAIZ))


if __name__ == "__main__":
    main()
