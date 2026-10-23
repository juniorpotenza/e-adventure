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
    ("inicio", "scrInicio", "Minha fila", "*"),
    ("carteira", "scrCarteira", "Minha carteira", "*"),
    ("dossie", "scrDossie", "Diligenciar item", "-"),
    ("ocorrencias", "scrOcorrencias", "Ocorrências", "*"),
    ("acoes", "scrAcoes", "Ações", "*"),
    ("agenda", "scrAgenda", "Agenda de entregas", "*"),
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
nfPrioridadesAltas = ["Crítica", "Urgente", "Alta"];
nfPerfil = Coalesce(
    LookUp(
        DIL_Configuracao,
        Lower(Trim(Categoria)) = "perfil_usuario" && Lower(Trim(Nome_Parametro)) = Lower(User().Email),
        Trim(Valor_Texto)
    ),
    "Diligenciador"
);
nfPodeEditar = Lower(nfPerfil) in ["diligenciador", "coordenador", "gestor", "administrador"];
nfPodeGestao = Lower(nfPerfil) in ["coordenador", "gestor", "auditor", "administrador"];
nfPodeAdmin = Lower(nfPerfil) in ["gestor", "administrador"];
nfMarcaContato = "[Contato]";
nfMarcaNovaData = "[Nova data]";
nfMarcaEntregue = "[Entregue]";
nfMarcaDivergencia = "[Recebido c/ divergência NF]";
nfMarcaNF = "[NF emitida]";
nfMarcaParcial = "[Entrega parcial]";
nfClassesSAP = ["Completo", "Parcial", "Não atendido"];
nfMenu = Table(
""" + ",\n".join(
    f'    {{Chave: "{k}", Rotulo: "{r}", Perfis: "{p}"}}' for k, _, r, p in TELAS) + "\n);\n"



def classe_sap(x):
    """Classe de atendimento de um código Status_EM/Status_EF do SAP. A tabela da Administração
    (DIL_Configuracao, Categoria Status_SAP) prevalece; sem linha, vale o padrão: 4 = Completo,
    3 = Parcial, demais = Não atendido. Também aceita a descrição por extenso."""
    return (f'Coalesce(LookUp(colStatusSAP, Codigo = Trim({x}), Classe), If(IsBlank(Trim({x})), "Não informado", '
            f'Trim({x}) = "4" || "complet" in Lower({x}), "Completo", '
            f'Trim({x}) = "3" || "parcial" in Lower({x}), "Parcial", "Não atendido"))')


# Carga central das coleções (App.OnStart e botão "Atualizar" do cabeçalho).
CARGA = """\
Concurrent(
    ClearCollect(colVinc, Filter(DIL_Vinculo_RC_PO, Ativo = true)),
    ClearCollect(colItensPO, Filter(DIL_Itens_Pedido, Ativo = true)),
    ClearCollect(colOcor, Filter(DIL_Ocorrencias, Ativo = true)),
    ClearCollect(colAcoes, AddColumns(Filter(DIL_Acoes, Ativo = true), PrazoData, IfError(DateTimeValue(Prazo), Blank()))),
    ClearCollect(colMarcos, DIL_Marcos),
    ClearCollect(colComun, DIL_Comunicacoes),
    ClearCollect(colComp, Filter(DIL_Compromissos, Ativo = true)),
    ClearCollect(colReq, Filter(DIL_Requisicoes, Ativa = true)),
    ClearCollect(colItensRC, Filter(DIL_Itens_Requisicao, Ativo = true)),
    ClearCollect(colForn, Filter(DIL_Fornecedores, Ativo = true)),
    ClearCollect(colEnvios, Filter(DIL_Historico, Tipo_Evento.Value = "Envio_Email")),
    ClearCollect(colPedidos, ShowColumns(Filter(DIL_Pedidos, Ativo = true), Numero_PO, Grupo_Compradores)),
    ClearCollect(colStatusSAP, ForAll(Filter(DIL_Configuracao, Lower(Trim(Categoria)) = "status_sap"), {Codigo: Trim(Nome_Parametro), Classe: Trim(Valor_Texto)})),
    ClearCollect(colModelos, Filter(DIL_Configuracao, Lower(Trim(Categoria)) = "modelo_email" && Ativo = true))
);
ClearCollect(
    colCarteiraBase,
    AddColumns(
        AddColumns(
            AddColumns(
                AddColumns(
                    colVinc As v,
                    ItemPO, LookUp(colItensPO, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO),
                    RC, LookUp(colReq, Numero_RC = v.Numero_RC),
                    ItemRC, LookUp(colItensRC, Numero_RC = v.Numero_RC && Item_RC = v.Item_RC),
                    Grupo, Coalesce(LookUp(colPedidos, Numero_PO = v.Numero_PO, Grupo_Compradores), "(sem grupo)"),
                    CompEntrega, First(Sort(Filter(colComp, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && Tipo_Compromisso.Value = "Entrega"), Data_Registro, SortOrder.Descending)),
                    UltimoContato, Max(Filter(colComun, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && !StartsWith(Chave_Comunicacao, "COM-SLA-")), Data_Comunicacao),
                    UltimaMarca, First(Sort(Filter(colComun, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && StartsWith(Chave_Comunicacao, "COM-APP-") && StartsWith(Assunto, "[")), ID, SortOrder.Descending)).Assunto,
                    UltimoParcial, First(Sort(Filter(colComun, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && StartsWith(Chave_Comunicacao, "COM-APP-") && StartsWith(Assunto, nfMarcaParcial)), ID, SortOrder.Descending)).Assunto,
                    QtdOcorrAbertas, CountRows(Filter(colOcor, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && !(Status_Ocorrencia.Value in nfOcorFechadas))),
                    QtdCriticas, CountRows(Filter(colOcor, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && Severidade.Value = "Crítica" && !(Status_Ocorrencia.Value in nfOcorFechadas))),
                    QtdAcoesPend, CountRows(Filter(colAcoes, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && !(Status_Acao.Value in nfAcaoFechadas))),
                    QtdAcoesVencidas, CountRows(Filter(colAcoes, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && PrazoData < Now() && !(Status_Acao.Value in nfAcaoFechadas))),
                    QtdMarcosAtrasados, CountRows(Filter(colMarcos, Numero_PO = v.Numero_PO && Item_PO = v.Item_PO && IsBlank(Data_Realizada) && Data_Planejada < Today() && !(Status_Marco.Value in nfMarcoFechados)))
                ) As a,
                DataSAP, a.ItemPO.Data_1_Remessa_Item_SAP,
                DataNegociada, a.CompEntrega.Data_Compromisso,
                PrioridadeAlta, a.RC.Prioridade.Value in nfPrioridadesAltas,
                LimiteContato, Switch(a.RC.Prioridade.Value, "Crítica", 2, "Urgente", 2, "Alta", 3, "Média", 7, "Baixa", 14, 7),
                DiasSemContato, If(IsBlank(a.UltimoContato), 999, DateDiff(a.UltimoContato, Today(), TimeUnit.Days)),
                AtendEM, @@CLASSE_EM@@,
                AtendEF, @@CLASSE_EF@@,
                QtdInformada, Match(a.UltimoParcial, "recebido (?<rec>[0-9.]+) de (?<tot>[0-9.]+)")
            ) As b,
            EntregueSAP, b.AtendEM = "Completo",
            Entregue, b.AtendEM = "Completo" || b.CompEntrega.Status_Compromisso.Value = "Cumprido",
            QtdPedida, IfError(Value(b.QtdInformada.tot, "en-US"), Blank()),
            QtdRecebida, IfError(Value(b.QtdInformada.rec, "en-US"), Blank()),
            ProblemaNF, If(
                (b.AtendEM = "Completo" && b.AtendEF in ["Não atendido", "Parcial"]) || (b.AtendEM = "Parcial" && b.AtendEF = "Não atendido"), "Mercadoria recebida, fatura pendente",
                (b.AtendEF = "Completo" && b.AtendEM in ["Não atendido", "Parcial"]) || (b.AtendEF = "Parcial" && b.AtendEM = "Não atendido"), "NF lançada sem entrada de mercadoria",
                StartsWith(b.UltimaMarca, nfMarcaDivergencia) && b.AtendEM <> "Completo", "Divergência de NF informada",
                ""
            ),
            DataVigente, Coalesce(b.DataNegociada, b.DataSAP),
            GanhoDias, If(IsBlank(b.DataNegociada) || IsBlank(b.DataSAP), Blank(), DateDiff(b.DataNegociada, b.DataSAP, TimeUnit.Days))
        ) As c,
        Parcial, !c.Entregue && (c.AtendEM = "Parcial" || c.AtendEF = "Parcial" || !IsBlank(c.UltimoParcial)),
        Saldo, If(IsBlank(c.QtdPedida) || IsBlank(c.QtdRecebida), Blank(), c.QtdPedida - c.QtdRecebida),
        Situacao, If(
            c.Entregue, "Entregue",
            IsBlank(c.DataVigente), "Sem data",
            c.DataVigente < Today(), "Atrasado",
            c.DataVigente <= DateAdd(Today(), 7, TimeUnit.Days), "Vence em 7 dias",
            "Em dia"
        )
    )
);
ClearCollect(
    colCarteiraMeio,
    AddColumns(
        colCarteiraBase As d,
        SaldoTexto, If(
            !d.Parcial, "",
            IsBlank(d.Saldo), "quantidade faltante não informada",
            "recebido " & d.QtdRecebida & " de " & d.QtdPedida & " — faltam " & d.Saldo
        ),
        SituacaoFisica, If(
            d.EntregueSAP, "Entregue — entrada de mercadoria no SAP" & If(d.ProblemaNF = "", "", " (" & d.ProblemaNF & ")"),
            StartsWith(d.UltimaMarca, nfMarcaDivergencia) && d.Entregue, "Recebido — NF com divergência",
            d.Entregue, "Entregue (informado no app)",
            d.Parcial, "Parcialmente atendido",
            StartsWith(d.UltimaMarca, nfMarcaNF) || d.AtendEF = "Completo", "NF emitida — material sem entrada",
            ""
        )
    )
);
ClearCollect(
    colCarteira,
    AddColumns(
        colCarteiraMeio As c,
        Urgencia, If(
            c.Entregue && c.ProblemaNF <> "", 100,
            c.Entregue, 0,
            c.Situacao = "Atrasado", 1000 + DateDiff(c.DataVigente, Today(), TimeUnit.Days) + If(c.PrioridadeAlta, 200, 0) + If(c.Parcial, 50, 0),
            c.Situacao = "Vence em 7 dias", 700 - DateDiff(Today(), c.DataVigente, TimeUnit.Days) + If(c.PrioridadeAlta, 200, 0),
            c.QtdAcoesVencidas > 0, 500,
            c.Parcial, 450,
            c.PrioridadeAlta && Coalesce(c.GanhoDias, 0) <= 0, 400,
            c.ProblemaNF <> "", 350,
            c.DiasSemContato > c.LimiteContato, 300 + Min(c.DiasSemContato, 99),
            0
        ),
        Motivo, If(
            c.Entregue && c.ProblemaNF = "Divergência de NF informada", "Recebido: acompanhar regularização da NF",
            c.Entregue && c.ProblemaNF <> "", "Recebido: fatura (NF) sem entrada no SAP — verificar divergência",
            c.Entregue, "Entregue",
            c.Situacao = "Atrasado", "Atrasado há " & DateDiff(c.DataVigente, Today(), TimeUnit.Days) & " dia(s): " & If(c.Parcial, "cobrar o saldo (" & c.SaldoTexto & ")", "cobrar nova data"),
            c.Situacao = "Vence em 7 dias", "Entrega em " & DateDiff(Today(), c.DataVigente, TimeUnit.Days) & " dia(s): confirmar " & If(c.Parcial, "o saldo", "com o fornecedor"),
            c.QtdAcoesVencidas > 0, c.QtdAcoesVencidas & " ação(ões) vencida(s)",
            c.Parcial, "Parcialmente atendido: diligenciar o saldo (" & c.SaldoTexto & ")",
            c.PrioridadeAlta && Coalesce(c.GanhoDias, 0) <= 0, "Prioridade " & c.RC.Prioridade.Value & ": buscar antecipação",
            c.ProblemaNF <> "", c.ProblemaNF & ": confirmar chegada do material",
            c.DiasSemContato > c.LimiteContato, If(c.DiasSemContato = 999, "Nenhum contato registrado", "Sem contato há " & c.DiasSemContato & " dias"),
            "Em dia"
        )
    )
);
Set(gblCarregadoEm, Now())""".replace("@@CLASSE_EM@@", classe_sap("a.ItemPO.Status_EM")).replace(
    "@@CLASSE_EF@@", classe_sap("a.ItemPO.Status_EF"))

ON_START = """\
""" + CARGA


def cor_situacao(expr):
    return (f'Switch({expr}, "Atrasado", nfCorVermelho, "Vence em 7 dias", nfCorLaranja, "Em dia", nfCorVerde, '
            f'"Entregue", nfCorAzul, nfCorSuave)')


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


# Versões exatamente como o Power Apps Studio gravou ao salvar o app (21/10/2026).
VERSOES = {"Label": "2.5.1", "Rectangle": "2.3.0", "Gallery": "2.15.0", "Classic/Button": "2.2.0",
           "Classic/TextInput": "2.3.2", "Classic/DropDown": "2.3.1", "Classic/DatePicker": "2.6.0",
           "Classic/ComboBox": "2.4.0"}


def ctl(nome, tipo, props, filhos=None, variante=None):
    corpo = {"Control": f"{tipo}@{VERSOES[tipo]}" if tipo in VERSOES else tipo}
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


def cartao_kpi(sufixo, i, titulo, valor, cor_destaque="nfCorAzul", n=6, y=Y0 + 40, visivel=None):
    larg = f"({LARG_CONTEUDO} - {(n - 1) * 12}) / {n}"
    x = f"{X0} + {i} * ({larg} + 12)"
    v = {"Visible": visivel} if visivel else {}
    return [
        retangulo(f"kpiFundo{i}_{sufixo}", x, y, larg, 92, BorderColor="nfCorLinha", BorderThickness=1, **v),
        retangulo(f"kpiFaixa{i}_{sufixo}", x, y, 4, 92, cor_destaque, **v),
        rotulo(f"kpiTitulo{i}_{sufixo}", txt(titulo), f"{x} + 16", y + 10, f"{larg} - 24", 22, 10, "nfCorSuave", **v),
        rotulo(f"kpiValor{i}_{sufixo}", valor, f"{x} + 16", y + 36, f"{larg} - 24", 44, 22, cor_destaque, True, **v),
    ]


def cabecalho_e_menu(chave, titulo, apos_atualizar=""):
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
        rotulo(f"cabUsuario_{s}", 'User().FullName & "  ·  perfil: " & nfPerfil', "Parent.Width - 560", 0, 360,
               ALT_CAB, 10, "nfCorCartao", Align="Align.Right"),
        botao(f"cabAtualizar_{s}", txt("Atualizar dados"), "Parent.Width - 180", 12, 160, 32,
              CARGA + apos_atualizar + ';\nNotify("Dados atualizados.", NotificationType.Success)', primario=False),
        retangulo(f"menuFundo_{s}", 0, ALT_CAB, LARG_MENU, f"Parent.Height - {ALT_CAB}", "nfCorNavy"),
        galeria(f"galMenu_{s}", "Filter(nfMenu, Perfis = \"*\" || Lower(nfPerfil) in Split(Lower(Perfis), \",\"))",
                0, ALT_CAB + 12, LARG_MENU, f"Parent.Height - {ALT_CAB + 60}", 44, menu_item,
                OnSelect=nav, ShowScrollbar=False),
        rotulo(f"menuRodape_{s}", '"Atualizado " & ' + data("gblCarregadoEm", "dd/mm hh:mm"), 16,
               "Parent.Height - 40", LARG_MENU - 24, 28, 9, "nfCorLinha"),
        rotulo(f"lblTitulo_{s}", txt(titulo), X0, Y_TITULO, LARG_CONTEUDO, 40, 20, "nfCorTexto", True),
    ]


def bloqueio(s, condicao, mensagem, texto_botao="Voltar ao Início", destino="scrInicio"):
    """Painel por cima do conteúdo (Navigate não é permitido em OnVisible)."""
    return [
        retangulo(f"blqFundo_{s}", LARG_MENU, ALT_CAB, f"Parent.Width - {LARG_MENU}", f"Parent.Height - {ALT_CAB}",
                  "nfCorFundo", Visible=condicao),
        rotulo(f"blqMsg_{s}", txt(mensagem), X0, Y0 + 40, LARG_CONTEUDO, 40, 13, "nfCorTexto", True, Visible=condicao),
        rotulo(f"blqDiag_{s}", '"Seu perfil no app: " & nfPerfil & "   ·   e-mail usado para localizar o perfil em DIL_Configuracao: " & Lower(User().Email)',
               X0, Y0 + 150, LARG_CONTEUDO, 40, 10, "nfCorSuave", Visible=condicao),
        botao(f"blqBotao_{s}", txt(texto_botao), X0, Y0 + 96, 220, 40,
              f"Navigate({destino}, ScreenTransition.None)", Visible=condicao),
    ]


def tela(chave, nome_tela, titulo, filhos, on_visible=None, apos_atualizar=""):
    props = {"Fill": "nfCorFundo"}
    if on_visible:
        props["OnVisible"] = on_visible
    return {"Screens": {nome_tela: {"Properties": {k: f(v) for k, v in props.items()},
                                    "Children": cabecalho_e_menu(chave, titulo, apos_atualizar) + filhos}}}


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
    """Fila de trabalho do diligenciador: o que fazer hoje, ordenado por urgência."""
    s = "Inicio"
    minha = "Minha carteira"
    escopo = f"""Filter(
    colCarteira,
    ddEscopo_{s}.Selected.Value = "Toda a carteira" || Lower(RC.Responsavel_Diligencia.Email) = nfEmailUsuario
)"""
    filhos = [
        rotulo(f"lblSaudacao_{s}", '"Olá, " & First(Split(User().FullName, " ")).Value & ". Esta é a sua fila de hoje (" & Text(Today(), "dd/mm/yyyy") & ")."',
               X0, Y0, f"{LARG_CONTEUDO} - 260", 28, 11, "nfCorSuave"),
        lista_suspensa(f"ddEscopo_{s}", f'["{minha}", "Toda a carteira"]', f"{X0} + {LARG_CONTEUDO} - 240", Y0 - 4, 240,
                       Default=f'If(CountRows(Filter(colCarteira, Lower(RC.Responsavel_Diligencia.Email) = nfEmailUsuario)) > 0, "{minha}", "Toda a carteira")'),
    ]
    kpis = [
        ("Atrasados", f'CountRows(Filter({escopo}, Situacao = "Atrasado"))', "nfCorVermelho"),
        ("Entregas em 7 dias", f'CountRows(Filter({escopo}, Situacao = "Vence em 7 dias"))', "nfCorLaranja"),
        ("Prioritários a antecipar", f'CountRows(Filter({escopo}, !Entregue && PrioridadeAlta && Coalesce(GanhoDias, 0) <= 0))', "nfCorAzul"),
        ("Parcialmente atendidos", f'CountRows(Filter({escopo}, Parcial))', "nfCorLaranja"),
        ("Sem contato no prazo", f'CountRows(Filter({escopo}, !Entregue && DiasSemContato > LimiteContato))', "nfCorLaranja"),
        ("Ganho negociado (dias)", f'Sum(Filter({escopo}, GanhoDias > 0), GanhoDias)', "nfCorVerde"),
    ]
    for i, (t, v, c) in enumerate(kpis):
        filhos += cartao_kpi(s, i, t, v, c, n=6)
    y = Y0 + 150
    linha = [
        retangulo(f"fFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 2", "nfCorCartao",
                  OnSelect="Select(Parent)"),
        retangulo(f"fFaixa_{s}", 0, 0, 5, "Parent.TemplateHeight - 2", cor_situacao("ThisItem.Situacao")),
        rotulo(f"fMotivo_{s}", "ThisItem.Motivo", 16, 4, "Parent.TemplateWidth - 360", 24, 11,
               cor_situacao("ThisItem.Situacao"), True, OnSelect="Select(Parent)"),
        rotulo(f"fItem_{s}", '"PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO & "  ·  " & Coalesce(ThisItem.ItemPO.Nome_Fornecedor, "—") & "  ·  " & Coalesce(ThisItem.ItemPO.Descricao_Produto, "—")',
               16, 28, "Parent.TemplateWidth - 360", 22, 9, "nfCorTexto", OnSelect="Select(Parent)"),
        rotulo(f"fDatas_{s}", '"Remessa SAP " & ' + data("ThisItem.DataSAP", "dd/mm") + ' & "  →  negociada " & ' + data("ThisItem.DataNegociada", "dd/mm")
               + ' & If(Coalesce(ThisItem.GanhoDias, 0) > 0, "  (+" & ThisItem.GanhoDias & " d)", "") & Char(10) & "Prioridade " & Coalesce(ThisItem.RC.Prioridade.Value, "—") & "  ·  último contato " & If(ThisItem.DiasSemContato = 999, "—", ' + data("ThisItem.UltimoContato", "dd/mm") + ')',
               "Parent.TemplateWidth - 340", 4, 210, 46, 9, "nfCorSuave", OnSelect="Select(Parent)"),
        botao(f"fBtn_{s}", txt("Diligenciar ›"), "Parent.TemplateWidth - 120", 12, 108, 32,
              "Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
    ]
    filhos += [
        rotulo(f"lblFila_{s}", f'"Fila de trabalho (" & CountRows(galFila_{s}.AllItems) & ")  —  ordenada por urgência"',
               X0, y, LARG_CONTEUDO, 28, 13, "nfCorTexto", True),
        galeria(f"galFila_{s}", f"Sort(Filter({escopo}, Urgencia > 0), Urgencia, SortOrder.Descending)",
                X0, y + 32, LARG_CONTEUDO, f"Parent.Height - {y + 48}", 58, linha,
                OnSelect="Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
        rotulo(f"lblFilaVazia_{s}", txt("Nada pendente na fila. Bom trabalho!"), X0, y + 40, LARG_CONTEUDO, 28, 10,
               "nfCorSuave", Visible=f"IsEmpty(galFila_{s}.AllItems)"),
    ]
    return tela("inicio", "scrInicio", "Minha fila de hoje", filhos,
                "If(IsEmpty(colCarteira),\n" + CARGA + "\n)")


def tela_carteira():
    s = "Carteira"
    yf = Y0
    filhos = [
        entrada(f"txtBusca_{s}", "Buscar RC, PO, material, descrição ou fornecedor", X0, yf, 360),
        lista_suspensa(f"ddSituacao_{s}", '["(Em aberto)", "(Todos)", "Atrasado", "Vence em 7 dias", "Em dia", "Sem data", "Entregue", "Parcialmente atendido", "Problema de NF"]', X0 + 372, yf, 150),
        lista_suspensa(f"ddFornecedor_{s}",
                       'Ungroup(Table({o: ["(Todos)"]}, {o: Sort(Distinct(colCarteira, ItemPO.Nome_Fornecedor), Value)}), o)',
                       X0 + 534, yf, 260),
        lista_suspensa(f"ddEscopo_{s}", '["Minha carteira (responsável pela RC)", "Com ações sob minha responsabilidade", "Todos os itens"]',
                       X0 + 806, yf, 300,
                       Default='If(CountRows(Filter(colCarteira, Lower(RC.Responsavel_Diligencia.Email) = nfEmailUsuario)) > 0, "Minha carteira (responsável pela RC)", "Todos os itens")'),
    ]
    cols = [("RC / item · responsável", 16, 120), ("PO / item", 140, 110), ("Material · descrição", 254, 270),
            ("Fornecedor", 528, 170), ("Remessa SAP → negociada", 702, 150), ("Ganho · contato", 856, 120),
            ("Situação · prioridade", 980, 120)]
    filhos += linha_cab(s, cols, yf + 48)
    alt = "Parent.TemplateHeight"
    linha = [
        retangulo(f"cFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao",
                  OnSelect="Select(Parent)"),
        retangulo(f"cFaixa_{s}", 0, 0, 4, "Parent.TemplateHeight - 1", cor_situacao("ThisItem.Situacao")),
        rotulo(f"cRC_{s}", 'ThisItem.Numero_RC & " / " & ThisItem.Item_RC & Char(10) & Coalesce(ThisItem.RC.Responsavel_Diligencia.DisplayName, "sem responsável")',
               16, 0, 120, alt, 9, "nfCorTexto", True),
        rotulo(f"cPO_{s}", 'ThisItem.Numero_PO & " / " & ThisItem.Item_PO', 140, 0, 110, alt, 10, "nfCorTexto", True),
        rotulo(f"cDesc_{s}", 'Coalesce(ThisItem.ItemPO.Material, "—") & " · " & Coalesce(ThisItem.ItemPO.Descricao_Produto, "—")',
               254, 0, 270, alt, 9, "nfCorTexto"),
        rotulo(f"cForn_{s}", 'Coalesce(ThisItem.ItemPO.Nome_Fornecedor, "—")', 528, 0, 170, alt, 9, "nfCorTexto"),
        rotulo(f"cDatas_{s}", data("ThisItem.DataSAP") + ' & Char(10) & "→ " & ' + data("ThisItem.DataNegociada"),
               702, 0, 150, alt, 9, "nfCorTexto"),
        rotulo(f"cGanho_{s}", 'If(IsBlank(ThisItem.GanhoDias), "—", If(ThisItem.GanhoDias > 0, "+", "") & ThisItem.GanhoDias & " d") & Char(10) & If(ThisItem.DiasSemContato = 999, "sem contato", "há " & ThisItem.DiasSemContato & " d")',
               856, 0, 120, alt, 9, 'If(Coalesce(ThisItem.GanhoDias, 0) > 0, nfCorVerde, nfCorTexto)'),
        rotulo(f"cSit_{s}", 'ThisItem.Situacao & If(ThisItem.Parcial, " · parcial", "") & If(ThisItem.ProblemaNF <> "", " · NF", "") & Char(10) & Coalesce(ThisItem.RC.Prioridade.Value, "—")', 980, 0, 120, alt, 10,
               cor_situacao("ThisItem.Situacao"), True),
    ]
    itens = f"""Filter(
    colCarteira As c,
    IsBlank(txtBusca_{s}.Text) || txtBusca_{s}.Text in c.Numero_RC || txtBusca_{s}.Text in c.Numero_PO
        || txtBusca_{s}.Text in c.ItemPO.Material || txtBusca_{s}.Text in c.ItemPO.Descricao_Produto
        || txtBusca_{s}.Text in c.ItemPO.Nome_Fornecedor || txtBusca_{s}.Text in c.RC.Responsavel_Diligencia.DisplayName,
    Switch(ddSituacao_{s}.Selected.Value, "(Todos)", true, "(Em aberto)", c.Situacao <> "Entregue",
        "Parcialmente atendido", c.Parcial, "Problema de NF", c.ProblemaNF <> "", c.Situacao = ddSituacao_{s}.Selected.Value),
    ddFornecedor_{s}.Selected.Value = "(Todos)" || c.ItemPO.Nome_Fornecedor = ddFornecedor_{s}.Selected.Value,
    Switch(
        ddEscopo_{s}.Selected.Value,
        "Todos os itens", true,
        "Minha carteira (responsável pela RC)", Lower(c.RC.Responsavel_Diligencia.Email) = nfEmailUsuario,
        CountRows(Filter(colAcoes, Numero_PO = c.Numero_PO && Item_PO = c.Item_PO && Lower(Responsavel_Pessoa.Email) = nfEmailUsuario)) > 0
    )
)"""
    filhos += [
        galeria(f"galCarteira_{s}", itens, X0, yf + 78, LARG_CONTEUDO, f"Parent.Height - {yf + 78 + 44}", 52, linha,
                OnSelect="Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
        rotulo(f"lblTotal_{s}", f'CountRows(galCarteira_{s}.AllItems) & " de " & CountRows(colCarteira) & " itens (RC/PO vinculados e ativos)"',
               X0, "Parent.Height - 40", LARG_CONTEUDO, 28, 9, "nfCorSuave"),
    ]
    return tela("carteira", "scrCarteira", "Minha carteira", filhos)


def carga_dossie(filtro_item):
    """Coleções do item aberto (também usada após gravar um contato)."""
    return f"""UpdateContext({{locForn: LookUp(colForn, Codigo_Fornecedor = gblItem.ItemPO.Codigo_Fornecedor)}});
    ClearCollect(colDosMarcos, Filter(colMarcos, {filtro_item}));
    ClearCollect(colDosOcor, Filter(colOcor, {filtro_item}));
    ClearCollect(colDosAcoes, Filter(colAcoes, {filtro_item}));
    ClearCollect(colDosComun, Filter(colComun, {filtro_item}));
    ClearCollect(colDosComp, Filter(colComp, {filtro_item}));
    ClearCollect(
        colDosChaves,
        ForAll(colDosMarcos, {{Chave: Chave_Marco}}),
        ForAll(colDosOcor, {{Chave: Chave_Ocorrencia}}),
        ForAll(colDosAcoes, {{Chave: Chave_Acao}}),
        ForAll(colDosComun, {{Chave: Chave_Comunicacao}}),
        ForAll(colDosComp, {{Chave: Chave_Compromisso}})
    );
    Clear(colDosHist);
    ForAll(colDosChaves As k, Collect(colDosHist, Filter(DIL_Historico, Chave_Entidade = k.Chave)))"""


def tela_dossie():
    s = "Dossie"
    filtro_item = "Numero_PO = gblItem.Numero_PO && Item_PO = gblItem.Item_PO"
    on_visible = f"""If(
    IsBlank(gblItem),
    false,
    {carga_dossie(filtro_item)};
    UpdateContext({{locAba: If(nfPodeEditar, "Registrar contato", "Contatos")}})
)"""
    yc = Y0 + 172
    ganho = ('If(IsBlank(gblItem.GanhoDias), "—", If(gblItem.GanhoDias > 0, "+" & gblItem.GanhoDias & " dia(s) antecipado(s)", '
             'gblItem.GanhoDias = 0, "0 dia", gblItem.GanhoDias & " dia(s) (postergado)"))')
    filhos = [
        retangulo(f"dCab_{s}", X0, Y0, LARG_CONTEUDO, 112, BorderColor="nfCorLinha", BorderThickness=1),
        retangulo(f"dCabFaixa_{s}", X0, Y0, 5, 112, cor_situacao("gblItem.Situacao")),
        rotulo(f"dIdent_{s}", '"PO " & gblItem.Numero_PO & " / " & gblItem.Item_PO & "   ·   RC " & gblItem.Numero_RC & " / " & gblItem.Item_RC & "   ·   " & Coalesce(gblItem.ItemPO.Nome_Fornecedor, "—")',
               X0 + 16, Y0 + 6, f"{LARG_CONTEUDO} - 200", 30, 14, "nfCorTexto", True),
        rotulo(f"dDesc_{s}", 'Coalesce(gblItem.ItemPO.Material, "—") & " · " & Coalesce(gblItem.ItemPO.Descricao_Produto, "—") & "   ·   prioridade " & Coalesce(gblItem.RC.Prioridade.Value, "—") & "   ·   contato fornecedor: " & Coalesce(locForn.Contato_Principal, "—") & " " & Coalesce(locForn.Telefone, "") & " " & Coalesce(locForn.Email_Principal, "")',
               X0 + 16, Y0 + 36, f"{LARG_CONTEUDO} - 32", 26, 9, "nfCorSuave"),
        rotulo(f"dDatas_{s}", '"Remessa SAP: " & ' + data("gblItem.DataSAP") + ' & "     Data negociada: " & ' + data("gblItem.DataNegociada")
               + ' & "     Ganho: " & ' + ganho + ' & "     Último contato: " & If(gblItem.DiasSemContato = 999, "nenhum", '
               + data("gblItem.UltimoContato") + ' & " (há " & gblItem.DiasSemContato & " d)")',
               X0 + 16, Y0 + 62, f"{LARG_CONTEUDO} - 200", 24, 10, "nfCorTexto", True),
        rotulo(f"dSitFis_{s}", 'If(gblItem.SituacaoFisica = "", "", "Situação física: " & gblItem.SituacaoFisica & "  ·  ") & If(gblItem.SaldoTexto = "", "", gblItem.SaldoTexto & "  ·  ") & If(gblItem.ProblemaNF = "" || gblItem.EntregueSAP, "", gblItem.ProblemaNF & "  ·  ") & "SAP: mercadoria " & gblItem.AtendEM & ", fatura " & gblItem.AtendEF',
               X0 + 16, Y0 + 86, f"{LARG_CONTEUDO} - 200", 22, 9, "nfCorAzul", True),
        rotulo(f"dSit_{s}", 'gblItem.Situacao & Char(10) & gblItem.Motivo', f"{X0} + {LARG_CONTEUDO} - 190", Y0 + 6, 180, 100, 10,
               cor_situacao("gblItem.Situacao"), True, Align="Align.Right", VerticalAlign="VerticalAlign.Top"),
        botao(f"btnContato_{s}", txt("Registrar contato / resultado"), X0, Y0 + 122, 260, 40,
              'UpdateContext({locAba: "Registrar contato"})', Visible="nfPodeEditar"),
        botao(f"btnNovaOcor_{s}", txt("Registrar problema"), X0 + 272, Y0 + 122, 190, 40,
              'Set(gblPrefill, gblItem); Set(gblAbrirNova, true); Navigate(scrOcorrencias, ScreenTransition.None)',
              Visible="nfPodeEditar", primario=False),
        botao(f"btnVoltar_{s}", txt("‹ Voltar à fila"), f"{X0} + {LARG_CONTEUDO} - 190", Y0 + 122, 190, 40,
              "Navigate(scrInicio, ScreenTransition.None)", primario=False),
    ]
    abas = ["Registrar contato", "Contatos", "Resumo", "Compromissos", "Marcos", "Ocorrências", "Ações", "Histórico"]
    for i, a in enumerate(abas):
        filhos.append(botao(f"btnAba{i}_{s}", txt(a), f"{X0} + {i * 138}", yc, 134, 34,
                            f'UpdateContext({{locAba: "{a}"}})', primario=False,
                            Fill=f'If(locAba = "{a}", nfCorAzul, nfCorCartao)',
                            Color=f'If(locAba = "{a}", nfCorCartao, nfCorAzul)'))
    yg = yc + 46
    alt = f"Parent.Height - {yg + 16}"

    resumo = [
        ("Status de liberação do PO", 'Coalesce(gblItem.ItemPO.Status_Liberacao_PO, "—")'),
        ("Entrada de fatura (EF)", 'Coalesce(gblItem.ItemPO.Status_EF, "—") & " · " & gblItem.AtendEF'),
        ("Entrada de mercadoria (EM)", 'Coalesce(gblItem.ItemPO.Status_EM, "—") & " · " & gblItem.AtendEM'),
        ("Data 1ª remessa (SAP)", data("gblItem.ItemPO.Data_1_Remessa_Item_SAP")),
        ("Código do fornecedor", 'Coalesce(gblItem.ItemPO.Codigo_Fornecedor, "—")'),
        ("Ocorrências abertas", "gblItem.QtdOcorrAbertas"),
        ("Ações pendentes", "gblItem.QtdAcoesPend"),
        ("Marcos atrasados", "gblItem.QtdMarcosAtrasados"),
        ("Tipo / origem do vínculo RC×PO", 'Coalesce(gblItem.Tipo_Vinculo.Value, "—") & " / " & Coalesce(gblItem.Origem_Vinculo.Value, "—")'),
    ]
    resumo2 = [
        ("Responsável pela diligência", 'Coalesce(gblItem.RC.Responsavel_Diligencia.DisplayName, "—")'),
        ("Grupo de compradores", "gblItem.Grupo"),
        ("Prioridade / risco (RC)", 'Coalesce(gblItem.RC.Prioridade.Value, "—") & " / " & Coalesce(gblItem.RC.Nivel_Risco.Value, "—")'),
        ("Status / criação da RC", 'Coalesce(gblItem.RC.Status_RC, "—") & " / " & ' + data("gblItem.RC.Data_Criacao_RC")),
        ("Item RC: utilização / avaliação", 'Coalesce(gblItem.ItemRC.Utilizacao_Material, "—") & " / " & Coalesce(gblItem.ItemRC.Tipo_Avaliacao, "—")'),
        ("Fornecedor / CNPJ", 'Coalesce(locForn.Nome_Fornecedor, gblItem.ItemPO.Nome_Fornecedor, "—") & " / " & Coalesce(locForn.CNPJ, "—")'),
        ("Contato / telefone", 'Coalesce(locForn.Contato_Principal, "—") & " / " & Coalesce(locForn.Telefone, "—")'),
        ("E-mail do fornecedor", 'Coalesce(locForn.Email_Principal, "—")'),
        ("Status / risco do fornecedor", 'Coalesce(locForn.Status_Fornecedor.Value, "—") & " / " & Coalesce(locForn.Nivel_Risco_Fornecedor.Value, "—")'),
    ]
    for col, (lista, x) in enumerate([(resumo, X0), (resumo2, X0 + 600)]):
        for i, (t, v) in enumerate(lista):
            yy = yg + i * 40
            filhos += [
                rotulo(f"rTit{col}{i}_{s}", txt(t), x, yy, 220, 40, 10, "nfCorSuave", Visible='locAba = "Resumo"'),
                rotulo(f"rVal{col}{i}_{s}", v, x + 230, yy, 340 if col == 0 else f"{LARG_CONTEUDO} - 830", 40, 10,
                       "nfCorTexto", True, Visible='locAba = "Resumo"'),
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
        grade("Acoes", "Sort(colDosAcoes, PrazoData)", "Ações", [
            fundo("da"),
            rotulo(f"daPrazo_{s}", data("ThisItem.PrazoData"), 12, 0, 90, "Parent.TemplateHeight", 10,
                   'If(ThisItem.PrazoData < Now() && !(ThisItem.Status_Acao.Value in nfAcaoFechadas), nfCorVermelho, nfCorTexto)', True),
            rotulo(f"daDesc_{s}", 'Coalesce(ThisItem.Tipo_Acao.Value, "—") & " · " & Coalesce(ThisItem.Descricao_Acao, "")',
                   104, 0, "Parent.TemplateWidth - 470", "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"daResp_{s}", 'Coalesce(ThisItem.Responsavel_Pessoa.DisplayName, ThisItem.Responsavel, "—")',
                   "Parent.TemplateWidth - 360", 0, 200, "Parent.TemplateHeight", 9, "nfCorSuave"),
            rotulo(f"daStatus_{s}", 'Coalesce(ThisItem.Status_Acao.Value, "—") & " · " & Coalesce(ThisItem.Prioridade.Value, "—")',
                   "Parent.TemplateWidth - 150", 0, 140, "Parent.TemplateHeight", 10, "nfCorAzul", True),
        ]),
        grade("Comun", "Sort(colDosComun, Data_Comunicacao, SortOrder.Descending)", "Contatos", [
            fundo("dc"),
            rotulo(f"dcData_{s}", data("ThisItem.Data_Comunicacao"), 12, 4, 90, 24, 10, "nfCorAzul", True),
            rotulo(f"dcAssunto_{s}", 'Coalesce(ThisItem.Assunto, "—") & "  ·  " & Coalesce(ThisItem.Tipo_Comunicacao.Value, "—") & " / " & Coalesce(ThisItem.Sentido.Value, "—")',
                   106, 4, "Parent.TemplateWidth - 330", 24, 10, "nfCorTexto", True),
            rotulo(f"dcResumo_{s}", 'Coalesce(ThisItem.Resumo, "")', 106, 30, "Parent.TemplateWidth - 330", 30, 9, "nfCorTexto"),
            rotulo(f"dcQuem_{s}", 'If(StartsWith(ThisItem.Chave_Comunicacao, "COM-SLA-"), If(IsBlank(LookUp(colEnvios, Chave_Entidade = ThisItem.Chave_Comunicacao)), "Escalonamento automático (aguardando DIL_10)", "E-mail automático enviado (DIL_10)"), "Registrado por " & Coalesce(ThisItem.Responsavel_Pessoa.DisplayName, ThisItem.Responsavel, "—"))',
                   "Parent.TemplateWidth - 220", 4, 210, 56, 9, "nfCorSuave", Align="Align.Right"),
        ], 66),
        grade("Hist", "Sort(colDosHist, Data_Evento, SortOrder.Descending)", "Histórico", [
            fundo("dh"),
            rotulo(f"dhData_{s}", data("ThisItem.Data_Evento", "dd/mm/yyyy hh:mm"), 12, 0, 120, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"dhTipo_{s}", 'Coalesce(ThisItem.Entidade.Value, "—") & " · " & Coalesce(ThisItem.Tipo_Evento.Value, "—")',
                   136, 0, 200, "Parent.TemplateHeight", 9, "nfCorAzul", True),
            rotulo(f"dhDesc_{s}", 'Coalesce(ThisItem.Campo_Alterado, "") & ": " & Coalesce(ThisItem.Valor_Anterior, "∅") & " → " & Coalesce(ThisItem.Valor_Novo, "∅") & Char(10) & Coalesce(ThisItem.Observacao, "")',
                   340, 0, "Parent.TemplateWidth - 350", "Parent.TemplateHeight", 9, "nfCorTexto"),
        ], 60),
    ]
    filhos += aba_compromissos(s, yg, filtro_item)
    filhos += painel_contato(s, yc + 46, filtro_item)
    filhos += bloqueio(s, "IsBlank(gblItem)", "Nenhum processo selecionado. Escolha um item na carteira.",
                       "Ir para a carteira", "scrCarteira")
    return tela("dossie", "scrDossie", "Diligenciar item", filhos, on_visible)


RESULTADOS = ["Somente contato (sem mudança)", "Nova data negociada", "Material entregue",
              "Chegou com divergência na NF", "NF emitida — material em trânsito",
              "Entrega parcial (informar quantidades)"]

# Variáveis dos modelos de e-mail (Administração > Modelos de e-mail), preenchidas com o item aberto.
VARIAVEIS_MODELO = [
    ("{PO}", "gblItem.Numero_PO"), ("{ITEM_PO}", "gblItem.Item_PO"),
    ("{RC}", "gblItem.Numero_RC"), ("{ITEM_RC}", "gblItem.Item_RC"),
    ("{MATERIAL}", 'Coalesce(gblItem.ItemPO.Material, "")'),
    ("{DESCRICAO}", 'Coalesce(gblItem.ItemPO.Descricao_Produto, "")'),
    ("{FORNECEDOR}", 'Coalesce(locForn.Nome_Fornecedor, gblItem.ItemPO.Nome_Fornecedor, "")'),
    ("{CONTATO}", 'Coalesce(locForn.Contato_Principal, "")'),
    ("{DATA_REMESSA}", 'If(IsBlank(gblItem.DataSAP), "—", Text(gblItem.DataSAP, "dd/mm/yyyy"))'),
    ("{DATA_NEGOCIADA}", 'If(IsBlank(gblItem.DataNegociada), "—", Text(gblItem.DataNegociada, "dd/mm/yyyy"))'),
    ("{DATA_PREVISTA}", 'If(IsBlank(gblItem.DataVigente), "—", Text(gblItem.DataVigente, "dd/mm/yyyy"))'),
    ("{DIAS_ATRASO}", 'Text(If(IsBlank(gblItem.DataVigente), 0, Max(0, DateDiff(gblItem.DataVigente, Today(), TimeUnit.Days))))'),
    ("{SALDO}", "gblItem.SaldoTexto"),
    ("{GRUPO_COMPRADORES}", "gblItem.Grupo"),
    ("{DILIGENCIADOR}", "User().FullName"),
]


def preencher(expr):
    for chave, valor in VARIAVEIS_MODELO:
        expr = f'Substitute({expr}, "{chave}", {valor})'
    return expr


def painel_contato(s, y, filtro_item):
    """Registrar contato com o fornecedor e o resultado. Grava em DIL_Comunicacoes (chave COM-APP-*, que o
    DIL_10 ignora) e, conforme o resultado, cria/atualiza o compromisso de Entrega (o DIL_05 cuida do marco;
    'Cumprido' encerra a cobrança e o DIL_07 encerra a ocorrência de atraso)."""
    aba = 'locAba = "Registrar contato"'
    vis = f"{aba} && nfPodeEditar"
    x = X0
    w = f"({LARG_CONTEUDO}) * 0.56"
    res = f"ddResultado_{s}.Selected.Value"
    com_data = f'{res} <> "{RESULTADOS[0]}"'
    parcial = f'{res} = "{RESULTADOS[5]}"'
    rotulo_data = (f'Switch({res}, "{RESULTADOS[1]}", "Nova data *", "{RESULTADOS[2]}", "Data da chegada *", '
                   f'"{RESULTADOS[3]}", "Data da chegada *", "{RESULTADOS[4]}", "Data da NF *", '
                   f'"{RESULTADOS[5]}", "Chegada · qtd *", "")')
    marca = (f'Switch({res}, "{RESULTADOS[1]}", nfMarcaNovaData, "{RESULTADOS[2]}", nfMarcaEntregue, '
             f'"{RESULTADOS[3]}", nfMarcaDivergencia, "{RESULTADOS[4]}", nfMarcaNF, "{RESULTADOS[5]}", nfMarcaParcial, '
             f'nfMarcaContato)')
    # Quantidades gravadas com ponto decimal, lidas de volta pela carga (coluna QtdInformada).
    qtds = (f'If({parcial}, " — recebido " & Substitute(Text(Value(txtQtdRec_{s}.Text)), ",", ".") & " de " '
            f'& Substitute(Text(Value(txtQtdTot_{s}.Text)), ",", "."), "")')
    assunto = (f'{marca} & If({com_data}, " " & Text(dtResultado_{s}.SelectedDate, "dd/mm/yyyy"), "") '
               f'& {qtds} & " — " & ddMeio_{s}.Selected.Value')
    comp = "gblItem.CompEntrega"
    novo_comp = lambda status: f"""Patch(
            DIL_Compromissos,
            Defaults(DIL_Compromissos),
            {{
                Chave_Compromisso: "COMP-APP-" & Text(Now(), "yyyymmddhhmmss") & "-" & Text(RandBetween(100, 999)),
                Numero_RC: gblItem.Numero_RC, Item_RC: gblItem.Item_RC,
                Numero_PO: gblItem.Numero_PO, Item_PO: gblItem.Item_PO,
                Codigo_Fornecedor: gblItem.ItemPO.Codigo_Fornecedor,
                Tipo_Compromisso: {{Value: "Entrega"}},
                Data_Compromisso: dtResultado_{s}.SelectedDate,
                Origem_Compromisso: {{Value: "Fornecedor"}},
                Status_Compromisso: {{Value: "{status}"}},
                Data_Registro: Now(),
                Responsavel_Registro: User().FullName,
                Responsavel_Registro_Pessoa: nfPessoaAtual,
                Observacao: {assunto},
                Ativo: true
            }}
        )"""
    passo_comp = f"""If(
        {res} = "{RESULTADOS[1]}" && IsBlank({comp}), {novo_comp("Aberto")},
        {res} = "{RESULTADOS[1]}",
        With(
            {{r: LookUp(DIL_Compromissos, ID = {comp}.ID)}},
            Patch(
            DIL_Compromissos,
            r,
            {{
                Data_Compromisso_Anterior: r.Data_Compromisso,
                Data_Compromisso: dtResultado_{s}.SelectedDate,
                Status_Compromisso: {{Value: "Aberto"}},
                Observacao: {assunto}
            }}
        )),
        {res} in ["{RESULTADOS[2]}", "{RESULTADOS[3]}"] && IsBlank({comp}), {novo_comp("Cumprido")},
        {res} in ["{RESULTADOS[2]}", "{RESULTADOS[3]}"],
        Patch(
            DIL_Compromissos,
            LookUp(DIL_Compromissos, ID = {comp}.ID),
            {{Status_Compromisso: {{Value: "Cumprido"}}, Observacao: {assunto}}}
        )
    )"""
    contato = f"""Patch(
        DIL_Comunicacoes,
        Defaults(DIL_Comunicacoes),
        {{
            Chave_Comunicacao: "COM-APP-" & Text(Now(), "yyyymmddhhmmss") & "-" & Text(RandBetween(100, 999)),
            Numero_RC: gblItem.Numero_RC, Item_RC: gblItem.Item_RC,
            Numero_PO: gblItem.Numero_PO, Item_PO: gblItem.Item_PO,
            Codigo_Fornecedor: gblItem.ItemPO.Codigo_Fornecedor,
            Tipo_Comunicacao: ddMeio_{s}.Selected,
            Data_Comunicacao: Today(),
            Sentido: ddSentido_{s}.Selected,
            Assunto: {assunto},
            Resumo: Trim(txtResumo_{s}.Text),
            Responsavel: User().FullName,
            Responsavel_Pessoa: nfPessoaAtual,
            Ativo: true
        }}
    )"""
    depois = (CARGA + ";\nSet(gblItem, LookUp(colCarteira, Numero_PO = gblItem.Numero_PO && Item_PO = gblItem.Item_PO));\n"
              + carga_dossie(filtro_item) + f";\nUpdateContext({{locResumoEmail: \"\"}}); Reset(txtResumo_{s}); Reset(ddResultado_{s}); "
              f"Reset(txtQtdRec_{s}); Reset(txtQtdTot_{s})")
    salvar = f"""If(
    IsBlank(Trim(txtResumo_{s}.Text)) || IsBlank(ddMeio_{s}.Selected) || IsBlank(ddSentido_{s}.Selected),
    Notify("Informe meio, sentido e o resumo do contato.", NotificationType.Error),
    {com_data} && IsBlank(dtResultado_{s}.SelectedDate),
    Notify("Informe a data do resultado.", NotificationType.Error),
    {parcial} && (IsError(Value(txtQtdRec_{s}.Text)) || IsBlank(Value(txtQtdRec_{s}.Text)) || IsBlank(Value(txtQtdTot_{s}.Text))
        || Value(txtQtdRec_{s}.Text) <= 0 || Value(txtQtdRec_{s}.Text) >= Value(txtQtdTot_{s}.Text)),
    Notify("Entrega parcial: informe a quantidade recebida (maior que zero) e a quantidade pedida (maior que a recebida).", NotificationType.Error),
    IfError(
        {contato},
        Notify("Não foi possível gravar o contato: " & FirstError.Message, NotificationType.Error),
        {passo_comp},
        Notify("Contato gravado, mas o compromisso não foi atualizado: " & FirstError.Message, NotificationType.Warning),
        Notify(If({com_data} && !({res} in ["{RESULTADOS[4]}", "{RESULTADOS[5]}"]), "Contato e compromisso gravados. O marco é atualizado pelo DIL_05 em instantes.", "Contato registrado."), NotificationType.Success);
        {depois}
    )
)"""
    filhos = [
        retangulo(f"ctFundo_{s}", x, y, w, f"Parent.Height - {y + 16}", BorderColor="nfCorAzul", BorderThickness=2, Visible=vis),
        rotulo(f"ctTitulo_{s}", txt("Registrar contato com o fornecedor"), f"{x} + 16", y + 8, f"{w} - 32", 30, 13,
               "nfCorTexto", True, Visible=vis),
    ]
    campos = [
        ("Resultado *", lista_suspensa(f"ddResultado_{s}", "[" + ", ".join(f'"{r}"' for r in RESULTADOS) + "]", 0, 0, 0, Visible=vis)),
        ("Meio *", lista_suspensa(f"ddMeio_{s}", "Choices([@DIL_Comunicacoes].Tipo_Comunicacao)", 0, 0, 0, Visible=vis)),
        ("Sentido *", lista_suspensa(f"ddSentido_{s}", "Choices([@DIL_Comunicacoes].Sentido)", 0, 0, 0, Visible=vis)),
        ("Data", ctl(f"dtResultado_{s}", "Classic/DatePicker", {
            "DefaultDate": f'If({res} = "{RESULTADOS[1]}" && !IsBlank(gblItem.DataVigente), gblItem.DataVigente, Today())',
            "Height": 36, "Size": 10, "BorderColor": "nfCorLinha", "Visible": f"{vis} && {com_data}"})),
    ]
    for i, (t, c) in enumerate(campos):
        yy = y + 46 + i * 44
        pr = c[next(iter(c))]["Properties"]
        pr["X"], pr["Y"], pr["Width"] = f(f"{x} + 140"), f(yy), f(f"{w} - 156")
        texto = rotulo_data if t == "Data" else txt(t)
        filhos += [rotulo(f"ctT{i}_{s}", texto, f"{x} + 16", yy, 120, 36, 9, "nfCorSuave", Visible=pr["Visible"][1:]), c]
        if t == "Data":
            # Entrega parcial: data da chegada + quantidade recebida e pedida na mesma linha.
            pr["Width"] = f(f"If({parcial}, ({w} - 156) * 0.4, {w} - 156)")
            xq = f"{x} + 140 + ({w} - 156) * 0.4 + 8"
            wq = f"({w} - 156) * 0.3 - 8"
            filhos += [
                entrada(f"txtQtdRec_{s}", "Qtd recebida", xq, yy, wq, Format="TextFormat.Number",
                        Visible=f"{vis} && {parcial}"),
                entrada(f"txtQtdTot_{s}", "Qtd pedida", f"{xq} + {wq} + 8", yy, wq, Format="TextFormat.Number",
                        padrao='If(IsBlank(gblItem.QtdPedida), "", Text(gblItem.QtdPedida))',
                        Visible=f"{vis} && {parcial}"),
            ]
    yr = y + 46 + len(campos) * 44
    filhos += [
        entrada(f"txtResumo_{s}", "O que foi tratado (ex.: fornecedor confirmou embarque; pedida antecipação por criticidade)",
                f"{x} + 16", yr, f"{w} - 32", 80, multilinha=True, padrao='Coalesce(locResumoEmail, "")', Visible=vis),
        rotulo(f"ctAjuda_{s}", f'Switch({res}, "{RESULTADOS[1]}", "Atualiza a data do compromisso de entrega; o ganho é calculado contra a remessa SAP.", '
               f'"{RESULTADOS[2]}", "Marca o compromisso como Cumprido: a cobrança para e a ocorrência de atraso é encerrada pelo fluxo.", '
               f'"{RESULTADOS[3]}", "Material recebido fisicamente; fica sinalizado até registrar ""Material entregue"" após a regularização da NF.", '
               f'"{RESULTADOS[4]}", "Registra a NF; a cobrança continua até a chegada do material.", '
               f'"{RESULTADOS[5]}", "Registra o recebimento parcial; o item continua na fila para diligenciar o saldo. '
               f'Para a data do saldo, registre depois ""Nova data negociada"".", "Registra apenas o contato.")',
               f"{x} + 16", yr + 86, f"{w} - 32", 36, 9, "nfCorSuave", Visible=vis),
        botao(f"btnCtSalvar_{s}", txt("Gravar"), f"{x} + 16", yr + 128, 140, 38, salvar, Visible=vis),
        botao(f"btnCtCancelar_{s}", txt("Limpar"), f"{x} + 168", yr + 128, 120, 38,
              f'UpdateContext({{locResumoEmail: ""}}); Reset(txtResumo_{s}); Reset(ddResultado_{s})', primario=False, Visible=vis),
        rotulo(f"ctSoLeitura_{s}", '"Seu perfil (" & nfPerfil & ") é somente leitura. Contatos do item na aba Contatos."',
               x, y + 8, w, 40, 11, "nfCorSuave", Visible=f"{aba} && !nfPodeEditar"),
    ]
    xr = f"{X0} + ({LARG_CONTEUDO}) * 0.56 + 16"
    wr = f"({LARG_CONTEUDO}) * 0.44 - 16"
    ult = [
        retangulo(f"ulFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao"),
        rotulo(f"ulData_{s}", data("ThisItem.Data_Comunicacao", "dd/mm"), 8, 4, 50, 22, 10, "nfCorAzul", True),
        rotulo(f"ulAssunto_{s}", 'Coalesce(ThisItem.Assunto, "")', 60, 4, "Parent.TemplateWidth - 64", 22, 9, "nfCorTexto", True),
        rotulo(f"ulResumo_{s}", 'Coalesce(ThisItem.Resumo, "")', 60, 26, "Parent.TemplateWidth - 64", 30, 9, "nfCorSuave"),
    ]
    modelo = f"ddModelo_{s}.Selected"
    abrir_email = f"""If(
    IsBlank({modelo}),
    Notify("Cadastre um modelo em Administração > Modelos de e-mail.", NotificationType.Warning),
    If(IsBlank(locForn.Email_Principal), Notify("Fornecedor sem e-mail em DIL_Fornecedores: informe o destinatário no e-mail.", NotificationType.Warning));
    Launch(
        "mailto:" & Coalesce(locForn.Email_Principal, "")
            & "?subject=" & EncodeUrl({preencher(f'Coalesce({modelo}.Assunto, "")')})
            & "&body=" & EncodeUrl({preencher(f'Coalesce({modelo}.Corpo, "")')})
    );
    UpdateContext({{locResumoEmail: "E-mail enviado (modelo " & {modelo}.Value & "): " & {preencher(f'Coalesce({modelo}.Assunto, "")')}}});
    Notify("E-mail aberto no seu programa de e-mail. Depois de enviar, grave o contato (meio E-mail).", NotificationType.Information)
)"""
    ym = y + 98
    filhos += [
        retangulo(f"emFundo_{s}", xr, y, wr, 86, BorderColor="nfCorLinha", BorderThickness=1, Visible=vis),
        rotulo(f"emTitulo_{s}", txt("E-mail ao fornecedor (modelo)"), f"{xr} + 12", y + 4, f"{wr} - 24", 26, 11,
               "nfCorTexto", True, Visible=vis),
        lista_suspensa(f"ddModelo_{s}", "Sort(ForAll(colModelos, {Value: Nome_Parametro, Assunto: Valor_Texto, Corpo: Descricao}), Value)",
                       f"{xr} + 12", y + 38, f"{wr} - 182", Visible=vis),
        botao(f"btnEmail_{s}", txt("Abrir e-mail"), f"{xr} + {wr} - 162", y + 38, 150, 36, abrir_email, Visible=vis),
        rotulo(f"ulTitulo_{s}", txt("Últimos contatos deste item"), xr, ym, wr, 28, 11, "nfCorTexto", True, Visible=aba),
        galeria(f"galUltimos_{s}", 'FirstN(Sort(Filter(colDosComun, !StartsWith(Chave_Comunicacao, "COM-SLA-")), ID, SortOrder.Descending), 20)',
                xr, ym + 32, wr, f"Parent.Height - {ym + 48}", 60, ult, Visible=aba),
        rotulo(f"ulVazio_{s}", txt("Nenhum contato registrado ainda."), xr, ym + 36, wr, 28, 9, "nfCorSuave",
               Visible=f"{aba} && IsEmpty(galUltimos_{s}.AllItems)"),
    ]
    return filhos


def aba_compromissos(s, yg, filtro_item):
    """Compromissos do fornecedor no Dossiê. Grava em DIL_Compromissos; o fluxo DIL_05 cria ou
    atualiza o marco MARCO-COMP-<chave> e registra o histórico (o app não duplica esse histórico)."""
    vis = 'locAba = "Compromissos"'
    sel = f"galComp_{s}.Selected"
    wl = f"({LARG_CONTEUDO}) * 0.6"
    xp = f"{X0} + ({LARG_CONTEUDO}) * 0.6 + 16"
    wp = f"({LARG_CONTEUDO}) * 0.4 - 16"
    alt = f"Parent.Height - {yg + 16}"
    reprog = 'locCompModo = "reprogramar"'
    linha = [
        retangulo(f"cpFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        rotulo(f"cpData_{s}", data("ThisItem.Data_Compromisso"), 12, 4, 100, 24, 12, "nfCorAzul", True),
        rotulo(f"cpAnt_{s}", 'If(IsBlank(ThisItem.Data_Compromisso_Anterior), "", "antes " & Text(ThisItem.Data_Compromisso_Anterior, "dd/mm/yyyy"))',
               12, 30, 110, 24, 9, "nfCorSuave"),
        rotulo(f"cpTipo_{s}", 'Coalesce(ThisItem.Tipo_Compromisso.Value, "—") & " · " & Coalesce(ThisItem.Status_Compromisso.Value, "—") & " · origem " & Coalesce(ThisItem.Origem_Compromisso.Value, "—")',
               126, 4, "Parent.TemplateWidth - 136", 24, 10, "nfCorTexto", True),
        rotulo(f"cpObs_{s}", 'Coalesce(ThisItem.Responsavel_Registro_Pessoa.DisplayName, ThisItem.Responsavel_Registro, "—") & "  —  " & Coalesce(ThisItem.Observacao, "")',
               126, 30, "Parent.TemplateWidth - 136", 24, 9, "nfCorSuave"),
    ]
    novo = f"""Patch(
        DIL_Compromissos,
        Defaults(DIL_Compromissos),
        {{
            Chave_Compromisso: "COMP-APP-" & Text(Now(), "yyyymmddhhmmss") & "-" & Text(RandBetween(100, 999)),
            Numero_RC: gblItem.Numero_RC,
            Item_RC: gblItem.Item_RC,
            Numero_PO: gblItem.Numero_PO,
            Item_PO: gblItem.Item_PO,
            Codigo_Fornecedor: gblItem.ItemPO.Codigo_Fornecedor,
            Tipo_Compromisso: ddTipoComp_{s}.Selected,
            Data_Compromisso: dtComp_{s}.SelectedDate,
            Origem_Compromisso: ddOrigemComp_{s}.Selected,
            Status_Compromisso: ddStatusComp_{s}.Selected,
            Data_Registro: Now(),
            Responsavel_Registro: User().FullName,
            Responsavel_Registro_Pessoa: nfPessoaAtual,
            Observacao: txtObsComp_{s}.Text,
            Ativo: true
        }}
    )"""
    altera = f"""Patch(
        DIL_Compromissos,
        LookUp(DIL_Compromissos, ID = {sel}.ID),
        {{
            Data_Compromisso_Anterior: {sel}.Data_Compromisso,
            Data_Compromisso: dtComp_{s}.SelectedDate,
            Status_Compromisso: ddStatusComp_{s}.Selected,
            Observacao: txtObsComp_{s}.Text
        }}
    )"""
    depois = (f'Notify("Compromisso gravado. O marco é atualizado pelo fluxo DIL_05 em instantes; use Atualizar dados.", '
              f'NotificationType.Success);\n' + CARGA +
              f';\nClearCollect(colDosComp, Filter(colComp, {filtro_item}));\nUpdateContext({{locCompModo: "novo"}})')
    salvar = f"""If(
    IsBlank(dtComp_{s}.SelectedDate) || IsBlank(ddStatusComp_{s}.Selected)
        || (!({reprog}) && (IsBlank(ddTipoComp_{s}.Selected) || IsBlank(ddOrigemComp_{s}.Selected))),
    Notify("Preencha tipo, origem, status e data.", NotificationType.Error),
    {reprog} && IsBlank({sel}),
    Notify("Selecione na lista o compromisso a reprogramar.", NotificationType.Error),
    IfError(
        If({reprog}, {altera}, {novo}),
        Notify("Não foi possível gravar: " & FirstError.Message, NotificationType.Error),
        {depois}
    )
)"""
    ed = f"{vis} && nfPodeEditar"
    filhos = [
        galeria(f"galComp_{s}", "Sort(colDosComp, Data_Compromisso, SortOrder.Descending)", X0, yg, wl, alt, 60, linha,
                Visible=vis),
        rotulo(f"cpVazio_{s}", txt("Nenhum compromisso registrado para este item."), X0, yg + 8, wl, 28, 10, "nfCorSuave",
               Visible=f"{vis} && IsEmpty(galComp_{s}.AllItems)"),
        retangulo(f"cpPainel_{s}", xp, yg, wp, alt, BorderColor="nfCorAzul", BorderThickness=1, Visible=ed),
        botao(f"btnCompNovo_{s}", txt("Novo compromisso"), f"{xp} + 16", yg + 12, 160, 32,
              'UpdateContext({locCompModo: "novo"})', primario=False, Visible=ed,
              Fill=f'If({reprog}, nfCorCartao, nfCorAzul)', Color=f'If({reprog}, nfCorAzul, nfCorCartao)'),
        botao(f"btnCompReprog_{s}", txt("Reprogramar selecionado"), f"{xp} + 186", yg + 12, 200, 32,
              'UpdateContext({locCompModo: "reprogramar"})', primario=False, Visible=ed,
              Fill=f'If({reprog}, nfCorAzul, nfCorCartao)', Color=f'If({reprog}, nfCorCartao, nfCorAzul)'),
        rotulo(f"cpModo_{s}", f'If({reprog}, "Nova data para: " & Coalesce({sel}.Tipo_Compromisso.Value, "") & " de " & ' + data(f"{sel}.Data_Compromisso") + ', "Registrar novo compromisso do fornecedor")',
               f"{xp} + 16", yg + 52, f"{wp} - 32", 28, 10, "nfCorTexto", True, Visible=ed),
    ]
    campos = [
        ("Tipo *", lista_suspensa(f"ddTipoComp_{s}", "Choices([@DIL_Compromissos].Tipo_Compromisso)", 0, 0, 0,
                                  Visible=f"{ed} && !({reprog})")),
        ("Origem *", lista_suspensa(f"ddOrigemComp_{s}", "Choices([@DIL_Compromissos].Origem_Compromisso)", 0, 0, 0,
                                    Visible=f"{ed} && !({reprog})")),
        ("Status *", lista_suspensa(f"ddStatusComp_{s}", "Choices([@DIL_Compromissos].Status_Compromisso)", 0, 0, 0,
                                    Default=f'If({reprog}, {sel}.Status_Compromisso.Value, "Aberto")', Visible=ed)),
        ("Data *", ctl(f"dtComp_{s}", "Classic/DatePicker", {
            "DefaultDate": f"If({reprog} && !IsBlank({sel}), {sel}.Data_Compromisso, DateAdd(Today(), 7, TimeUnit.Days))",
            "Height": 36, "Size": 10, "BorderColor": "nfCorLinha", "Visible": ed})),
    ]
    for i, (t, c) in enumerate(campos):
        yy = yg + 88 + i * 46
        pr = c[next(iter(c))]["Properties"]
        pr["X"], pr["Y"], pr["Width"] = f(f"{xp} + 110"), f(yy), f(f"{wp} - 126")
        filhos += [rotulo(f"cpT{i}_{s}", txt(t), f"{xp} + 16", yy, 90, 36, 9, "nfCorSuave", Visible=pr["Visible"][1:]), c]
    yo = yg + 88 + len(campos) * 46
    filhos += [
        entrada(f"txtObsComp_{s}", "Observação (ex.: confirmado por e-mail do fornecedor)", f"{xp} + 16", yo,
                f"{wp} - 32", 70, multilinha=True, padrao=f'If({reprog}, {sel}.Observacao, "")', Visible=ed),
        botao(f"btnCompSalvar_{s}", txt("Gravar compromisso"), f"{xp} + 16", yo + 82, 190, 36, salvar, Visible=ed),
        rotulo(f"cpNota_{s}", txt("O marco correspondente (MARCO-COMP-…) é criado/atualizado pelo fluxo DIL_05, "
                                  "que também registra o histórico."),
               f"{xp} + 16", yo + 126, f"{wp} - 32", 44, 9, "nfCorSuave", Visible=ed),
    ]
    return filhos


def tela_ocorrencias():
    s = "Ocor"
    yf = Y0
    filhos = [
        entrada(f"txtBusca_{s}", "Buscar por PO, RC ou descrição", X0, yf, 300),
        lista_suspensa(f"ddStatus_{s}", opcoes_com_todos("Choices([@DIL_Ocorrencias].Status_Ocorrencia)"), X0 + 312, yf, 170),
        lista_suspensa(f"ddSev_{s}", opcoes_com_todos("Choices([@DIL_Ocorrencias].Severidade)"), X0 + 494, yf, 150),
        botao(f"btnNova_{s}", txt("+ Nova ocorrência"), f"{X0} + {LARG_CONTEUDO} - 200", yf, 200, 36,
              "Set(gblPrefill, Blank()); UpdateContext({locNova: true})", Visible="nfPodeEditar"),
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
    pode = "nfPodeEditar"
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
    on_visible = "UpdateContext({locNova: gblAbrirNova && nfPodeEditar}); Set(gblAbrirNova, false)"
    return tela("ocorrencias", "scrOcorrencias", "Ocorrências", filhos, on_visible)


def tela_acoes():
    s = "Acoes"
    yf = Y0
    abertas = "!(Status_Acao.Value in nfAcaoFechadas)"
    filhos = [
        lista_suspensa(f"ddEscopo_{s}", '["Minhas ações", "Todas as ações"]', X0, yf, 170,
                       Default='If(Lower(nfPerfil) = "diligenciador", "Minhas ações", "Todas as ações")'),
        lista_suspensa(f"ddStatus_{s}", opcoes_com_todos("Choices([@DIL_Acoes].Status_Acao)"), X0 + 182, yf, 160),
        lista_suspensa(f"ddPrio_{s}", opcoes_com_todos("Choices([@DIL_Acoes].Prioridade)"), X0 + 354, yf, 150),
        lista_suspensa(f"ddPrazo_{s}", '["(Todos os prazos)", "Vencidas", "Vencem em 7 dias"]', X0 + 516, yf, 180),
        botao(f"btnNova_{s}", txt("+ Nova ação"), f"{X0} + {LARG_CONTEUDO} - 160", yf, 160, 36,
              "Set(gblOcorSel, Blank()); UpdateContext({locNova: true})", Visible="nfPodeEditar"),
    ]
    itens = f"""Sort(
    Filter(
        colAcoes,
        ddEscopo_{s}.Selected.Value = "Todas as ações" || Lower(Responsavel_Pessoa.Email) = nfEmailUsuario,
        ddStatus_{s}.Selected.Value = "(Todos)" || Status_Acao.Value = ddStatus_{s}.Selected.Value,
        ddPrio_{s}.Selected.Value = "(Todos)" || Prioridade.Value = ddPrio_{s}.Selected.Value,
        ddPrazo_{s}.Selected.Value = "(Todos os prazos)"
            || (ddPrazo_{s}.Selected.Value = "Vencidas" && PrazoData < Now() && {abertas})
            || (ddPrazo_{s}.Selected.Value = "Vencem em 7 dias" && PrazoData >= Now() && PrazoData <= DateAdd(Now(), 7, TimeUnit.Days) && {abertas})
    ),
    PrazoData
)"""
    larg_lista = f"({LARG_CONTEUDO}) * 0.58"
    xd = f"{X0} + ({LARG_CONTEUDO}) * 0.58 + 16"
    wd = f"({LARG_CONTEUDO}) * 0.42 - 16"
    yl = yf + 52
    vencida = "ThisItem.PrazoData < Now() && !(ThisItem.Status_Acao.Value in nfAcaoFechadas)"
    linha = [
        retangulo(f"aFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        retangulo(f"aFaixa_{s}", 0, 0, 4, "Parent.TemplateHeight - 1", f"If({vencida}, nfCorVermelho, nfCorAzul)"),
        rotulo(f"aTit_{s}", 'Coalesce(ThisItem.Tipo_Acao.Value, "—") & "  ·  PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO',
               14, 4, "Parent.TemplateWidth - 150", 24, 10, "nfCorTexto", True),
        rotulo(f"aDesc_{s}", 'Coalesce(ThisItem.Descricao_Acao, "") & "  —  " & Coalesce(ThisItem.Responsavel_Pessoa.DisplayName, ThisItem.Responsavel, "sem responsável")',
               14, 28, "Parent.TemplateWidth - 150", 30, 9, "nfCorSuave"),
        rotulo(f"aPrazo_{s}", '"Prazo " & ' + data("ThisItem.PrazoData"), "Parent.TemplateWidth - 140", 4, 130, 24, 10,
               f"If({vencida}, nfCorVermelho, nfCorTexto)", True, Align="Align.Right"),
        rotulo(f"aStatus_{s}", 'Coalesce(ThisItem.Status_Acao.Value, "—") & " · " & Coalesce(ThisItem.Prioridade.Value, "—")',
               "Parent.TemplateWidth - 160", 30, 150, 24, 9, "nfCorSuave", Align="Align.Right"),
    ]
    filhos.append(galeria(f"galAcoes_{s}", itens, X0, yl, larg_lista, f"Parent.Height - {yl + 16}", 64, linha))

    sel = f"galAcoes_{s}.Selected"
    vis_det = f"!locNova && !IsBlank({sel})"
    pode_editar = (f'nfPodeEditar && (Lower({sel}.Responsavel_Pessoa.Email) = nfEmailUsuario '
                   f'|| Lower(nfPerfil) in ["coordenador", "gestor", "administrador"])')
    det = [
        ("Chave", f"{sel}.Chave_Acao"),
        ("Ocorrência", f'Coalesce({sel}.Chave_Ocorrencia, "—")'),
        ("Responsável", f'Coalesce({sel}.Responsavel_Pessoa.DisplayName, {sel}.Responsavel, "—")'),
        ("Prazo", data(f"{sel}.PrazoData", "dd/mm/yyyy hh:mm")),
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
            Responsavel: ddResp_{s}.Selected.Value,
            Responsavel_Pessoa: ddResp_{s}.Selected.Pessoa,
            Prazo: Text(dtPrazo_{s}.SelectedDate, "yyyy-mm-dd"),
            Status_Acao: {{Value: "Aberta"}},
            Prioridade: ddPrioNova_{s}.Selected,
            Ativo: true
        }}
    )"""
    hist_nova = historico("Ação", "locChave", "Inclusão", "Ação", '""',
                          f'"Responsável=" & ddResp_{s}.Selected.Value & "; Prazo=" & Text(dtPrazo_{s}.SelectedDate, "dd/mm/yyyy")',
                          '"Ação registrada pelo aplicativo."')
    salvar_nova = f"""If(
    IsBlank(ddOcor_{s}.Selected) || IsBlank(ddTipo_{s}.Selected) || IsBlank(ddResp_{s}.Selected.Pessoa)
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
        ("Responsável *", lista_suspensa(
            f"ddResp_{s}", "Sort(ForAll(Choices([@DIL_Acoes].Responsavel_Pessoa) As p, {Value: p.DisplayName, Pessoa: p}), Value)",
            0, 0, 0, AllowEmptySelection=True, Visible=vis_n)),
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
    on_visible = "UpdateContext({locNova: gblAbrirNova && nfPodeEditar}); Set(gblAbrirNova, false)"
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
    mesmo_dia = '!Entregue && Text(DataVigente, "yyyymmdd") = Text(ThisItem.Dia, "yyyymmdd")'
    celula = [
        retangulo(f"calFundo_{s}", 1, 1, "Parent.TemplateWidth - 2", "Parent.TemplateHeight - 2",
                  'If(!IsBlank(locDia) && ThisItem.Dia = locDia, RGBA(209, 224, 250, 1), nfCorCartao)',
                  OnSelect="Select(Parent)"),
        rotulo(f"calNum_{s}", "Day(ThisItem.Dia)", 6, 2, 40, 24, 10,
               'If(Month(ThisItem.Dia) <> Month(locMes), nfCorLinha, ThisItem.Dia = Today(), nfCorAzul, nfCorTexto)',
               True, OnSelect="Select(Parent)"),
        rotulo(f"calQtd_{s}", f'With({{q: CountRows(Filter(colCarteira, {mesmo_dia}))}}, If(q = 0, "", q & If(q = 1, " entrega", " entregas")))',
               6, "Parent.TemplateHeight - 28", "Parent.TemplateWidth - 12", 24, 9,
               f'If(ThisItem.Dia < Today(), nfCorVermelho, nfCorAzul)',
               True, OnSelect="Select(Parent)"),
    ]
    filhos.append(ctl(f"galCalendario_{s}", "Gallery", {
        "Items": "With({ini: DateAdd(locMes, 1 - Weekday(locMes), TimeUnit.Days)}, ForAll(Sequence(42, 0), {Dia: DateAdd(ini, Value, TimeUnit.Days)}))",
        "X": X0, "Y": yc, "Width": larg_cal, "Height": f"Parent.Height - {yc + 16}", "WrapCount": 7,
        "TemplateSize": f"(Parent.Height - {yc + 16}) / 6", "TemplatePadding": 0, "ShowScrollbar": False,
        "OnSelect": "UpdateContext({locDia: ThisItem.Dia})"}, celula, variante="BrowseLayout_Vertical_OneTextVariant_ver5.0"))
    itens = """Sort(
    Filter(
        colCarteira,
        If(
            IsBlank(locDia),
            !Entregue && DataVigente <= DateAdd(Today(), 30, TimeUnit.Days),
            !Entregue && Text(DataVigente, "yyyymmdd") = Text(locDia, "yyyymmdd")
        )
    ),
    DataVigente
)"""
    linha = [
        retangulo(f"agFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao"),
        rotulo(f"agData_{s}", data("ThisItem.DataVigente", "dd/mm"), 10, 0, 56, "Parent.TemplateHeight", 12,
               cor_situacao("ThisItem.Situacao"), True),
        rotulo(f"agDesc_{s}", '"PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO & " · " & Coalesce(ThisItem.ItemPO.Nome_Fornecedor, "—") & Char(10) & ThisItem.Motivo & If(IsBlank(ThisItem.DataNegociada), "  (data SAP)", "  (negociada)")',
               70, 0, "Parent.TemplateWidth - 80", "Parent.TemplateHeight", 9, "nfCorTexto"),
    ]
    filhos += [
        rotulo(f"lblLista_{s}", 'If(IsBlank(locDia), "Atrasados e próximos 30 dias", "Entregas previstas em " & Text(locDia, "dd/mm/yyyy"))',
               xd, yf, f"{wd} - 120", 36, 13, "nfCorTexto", True),
        botao(f"btnLimpar_{s}", txt("Limpar dia"), f"{xd} + {wd} - 110", yf, 110, 36, "UpdateContext({locDia: Blank()})",
              primario=False, Visible="!IsBlank(locDia)"),
        galeria(f"galLista_{s}", itens, xd, yf + 48, wd, f"Parent.Height - {yf + 64}", 52, linha,
                OnSelect="Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
        rotulo(f"lblVazio_{s}", txt("Nenhuma entrega prevista no período."), xd, yf + 56, wd, 28, 10, "nfCorSuave",
               Visible=f"IsEmpty(galLista_{s}.AllItems)"),
    ]
    on_visible = "If(IsBlank(locMes), UpdateContext({locMes: Date(Year(Today()), Month(Today()), 1)}))"
    return tela("agenda", "scrAgenda", "Agenda de entregas", filhos, on_visible)


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


def barras(s, n, titulo, itens, x, y, w, h, visivel=None):
    """Gráfico de barras horizontais simples (Items com colunas Rotulo, Qtd, Max)."""
    v = {"Visible": visivel} if visivel else {}
    linha = [
        rotulo(f"b{n}Rot_{s}", "ThisItem.Rotulo", 0, 0, 170, "Parent.TemplateHeight", 9, "nfCorTexto"),
        retangulo(f"b{n}Barra_{s}", 176, 8, "Max(2, (Parent.TemplateWidth - 230) * ThisItem.Qtd / Max(1, ThisItem.Max))",
                  "Parent.TemplateHeight - 16", "nfCorAzul"),
        rotulo(f"b{n}Qtd_{s}", "ThisItem.Qtd", "Parent.TemplateWidth - 50", 0, 48, "Parent.TemplateHeight", 10,
               "nfCorTexto", True, Align="Align.Right"),
    ]
    return [
        retangulo(f"b{n}Fundo_{s}", x, y, w, h, BorderColor="nfCorLinha", BorderThickness=1, **v),
        rotulo(f"b{n}Titulo_{s}", txt(titulo), f"{x} + 12", f"{y} + 6", f"{w} - 24", 28, 11, "nfCorTexto", True, **v),
        galeria(f"galB{n}_{s}", itens, f"{x} + 12", f"{y} + 40", f"{w} - 24", f"{h} - 50", 32, linha, ShowScrollbar=True, **v),
    ]


def agrupar(origem, expr_rotulo, soma=None):
    medida = f"Sum(Itens, {soma})" if soma else "CountRows(Itens)"
    return (f'With({{t: AddColumns(GroupBy(AddColumns({origem}, Rotulo, {expr_rotulo}), Rotulo, Itens), Qtd, {medida})}}, '
            f'Sort(AddColumns(t, Max, Max(t, Qtd)), Qtd, SortOrder.Descending))')


def tabela_resumo(s, n, chave_expr, titulo_chave, y, vis):
    """Tabela agregada (grupo de compradores ou fornecedor) sobre colGestao, ordenada por atrasados."""
    itens = f"""Sort(
    AddColumns(
        GroupBy(AddColumns(colGestao, Chave, {chave_expr}), Chave, Itens),
        Abertos, CountRows(Filter(Itens, !Entregue)),
        Atrasados, CountRows(Filter(Itens, Situacao = "Atrasado")),
        AtrasoMedio, Average(Filter(Itens, Situacao = "Atrasado"), DateDiff(DataVigente, Today(), TimeUnit.Days)),
        Parciais, CountRows(Filter(Itens, Parcial)),
        ProblemasNF, CountRows(Filter(Itens, ProblemaNF <> "")),
        SemContato, CountRows(Filter(Itens, !Entregue && DiasSemContato > LimiteContato)),
        ValorAtrasado, Sum(Filter(Itens, Situacao = "Atrasado"), ItemPO.Valor_1_SAP),
        Ganho, Sum(Filter(Itens, GanhoDias > 0), GanhoDias)
    ),
    Atrasados, SortOrder.Descending, Abertos, SortOrder.Descending
)"""
    cols = [(titulo_chave, 12, 230), ("Em aberto", 250, 80), ("Atrasados", 334, 80), ("% atraso", 418, 80),
            ("Atraso médio", 502, 90), ("Parciais", 596, 80), ("Probl. NF", 680, 80), ("Sem contato", 764, 90),
            ("Valor atrasado", 858, 120), ("Ganho (d)", 982, 90)]
    cab = [rotulo(f"t{n}Cab{i}_{s}", txt(t), f"{X0} + {x}", y, w, 28, 9, "nfCorSuave", True, Visible=vis)
           for i, (t, x, w) in enumerate(cols)]
    valores = [
        "ThisItem.Chave", "ThisItem.Abertos", "ThisItem.Atrasados",
        'If(ThisItem.Abertos = 0, "—", Text(ThisItem.Atrasados / ThisItem.Abertos, "0%"))',
        'If(ThisItem.Atrasados = 0, "—", Text(ThisItem.AtrasoMedio, "0") & " d")',
        "ThisItem.Parciais", "ThisItem.ProblemasNF", "ThisItem.SemContato",
        '"R$ " & Text(ThisItem.ValorAtrasado, "#,##0")', "ThisItem.Ganho",
    ]
    linha = [retangulo(f"t{n}Fundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao")]
    for i, ((_, x, w), v) in enumerate(zip(cols, valores)):
        cor = 'If(ThisItem.Atrasados > 0, nfCorVermelho, nfCorTexto)' if i in (2, 3) else "nfCorTexto"
        linha.append(rotulo(f"t{n}Col{i}_{s}", v, x, 0, w, "Parent.TemplateHeight", 10 if i else 9, cor, i in (0, 2)))
    return cab + [galeria(f"galT{n}_{s}", itens, X0, y + 30, LARG_CONTEUDO, f"Parent.Height - {y + 30 + 56}", 40, linha,
                          Visible=vis)]


def tela_gestao():
    """Painel do gestor em abas: carteira e prazos, desempenho da diligência, notas fiscais,
    grupos de compradores e fornecedores. Filtros de grupo e fornecedor valem para todas as abas."""
    s = "Gestao"
    recalcular = f"""ClearCollect(
    colGestao,
    Filter(
        colCarteira,
        (ddGrupo_{s}.Selected.Value = "(Todos)" || Grupo = ddGrupo_{s}.Selected.Value)
            && (ddForn_{s}.Selected.Value = "(Todos)" || ItemPO.Nome_Fornecedor = ddForn_{s}.Selected.Value)
    )
);
ClearCollect(
    colGestaoComun,
    Filter(
        colComun,
        StartsWith(Chave_Comunicacao, "COM-APP-") && Data_Comunicacao >= DateAdd(Today(), -30, TimeUnit.Days)
            && Numero_PO in ShowColumns(colGestao, Numero_PO)
    )
)"""
    on_visible = f"""If(
    nfPodeGestao,
    If(IsEmpty(colCarteira), {CARGA.replace(chr(10), chr(10) + "    ")});
    {recalcular};
    UpdateContext({{locAba: Coalesce(locAba, "Carteira e prazos")}})
)"""
    abas = ["Carteira e prazos", "Desempenho da diligência", "Notas fiscais", "Grupos de compradores", "Fornecedores"]
    filhos = [
        lista_suspensa(f"ddGrupo_{s}", 'Ungroup(Table({o: ["(Todos)"]}, {o: Sort(Distinct(colCarteira, Grupo), Value)}), o)',
                       X0, Y0, 220, OnChange=recalcular),
        lista_suspensa(f"ddForn_{s}",
                       'Ungroup(Table({o: ["(Todos)"]}, {o: Sort(Distinct(colCarteira, ItemPO.Nome_Fornecedor), Value)}), o)',
                       X0 + 232, Y0, 320, OnChange=recalcular),
        rotulo(f"lblFiltro_{s}", '"Grupo de compradores e fornecedor  ·  " & CountRows(colGestao) & " itens no filtro"',
               X0 + 564, Y0, f"{LARG_CONTEUDO} - 564", 36, 9, "nfCorSuave"),
    ]
    for i, a in enumerate(abas):
        filhos.append(botao(f"btnAba{i}_{s}", txt(a), f"{X0} + {i * 206}", Y0 + 46, 200, 34,
                            f'UpdateContext({{locAba: "{a}"}})', primario=False,
                            Fill=f'If(locAba = "{a}", nfCorAzul, nfCorCartao)',
                            Color=f'If(locAba = "{a}", nfCorCartao, nfCorAzul)'))
    yk = Y0 + 92
    yb = yk + 104
    w = f"({LARG_CONTEUDO} - 32) / 3"
    h = f"Parent.Height - {yb + 56}"
    xs = [X0, f"{X0} + {w} + 16", f"{X0} + 2 * ({w} + 16)"]
    aberto = "Filter(colGestao, !Entregue)"
    atrasado = 'Filter(colGestao, Situacao = "Atrasado")'
    resp = 'Coalesce(RC.Responsavel_Diligencia.DisplayName, "(sem responsável)")'

    def aba(nome, kpis, graficos, sufixo):
        vis = f'locAba = "{nome}"'
        out = []
        for i, (t, v, c) in enumerate(kpis):
            out += cartao_kpi(f"{s}{sufixo}", i, t, v, c, n=len(kpis), y=yk, visivel=vis)
        for j, (n, titulo, itens) in enumerate(graficos):
            out += barras(s, n, titulo, itens, xs[j], yb, w, h, visivel=vis)
        return out

    filhos += aba("Carteira e prazos", [
        ("Itens em aberto", f"CountRows({aberto})", "nfCorAzul"),
        ("Atrasados", f'CountRows({atrasado}) & "  (" & Text(CountRows({atrasado}) / Max(1, CountRows({aberto})), "0%") & ")"', "nfCorVermelho"),
        ("Valor atrasado (R$)", f'Text(Sum({atrasado}, ItemPO.Valor_1_SAP), "#,##0")', "nfCorVermelho"),
        ("Entregas em 7 dias", 'CountRows(Filter(colGestao, Situacao = "Vence em 7 dias"))', "nfCorLaranja"),
        ("Parcialmente atendidos", "CountRows(Filter(colGestao, Parcial))", "nfCorLaranja"),
        ("Sem data de entrega", 'CountRows(Filter(colGestao, Situacao = "Sem data"))', "nfCorSuave"),
    ], [
        (1, "Atrasados por faixa de atraso", f"""With(
    {{at: {atrasado}}},
    With(
        {{t: Table(
            {{Rotulo: "1 a 7 dias", Qtd: CountRows(Filter(at, DateDiff(DataVigente, Today(), TimeUnit.Days) <= 7))}},
            {{Rotulo: "8 a 15 dias", Qtd: CountRows(Filter(at, DateDiff(DataVigente, Today(), TimeUnit.Days) > 7 && DateDiff(DataVigente, Today(), TimeUnit.Days) <= 15))}},
            {{Rotulo: "16 a 30 dias", Qtd: CountRows(Filter(at, DateDiff(DataVigente, Today(), TimeUnit.Days) > 15 && DateDiff(DataVigente, Today(), TimeUnit.Days) <= 30))}},
            {{Rotulo: "31 a 60 dias", Qtd: CountRows(Filter(at, DateDiff(DataVigente, Today(), TimeUnit.Days) > 30 && DateDiff(DataVigente, Today(), TimeUnit.Days) <= 60))}},
            {{Rotulo: "Mais de 60 dias", Qtd: CountRows(Filter(at, DateDiff(DataVigente, Today(), TimeUnit.Days) > 60))}}
        )}},
        AddColumns(t, Max, Max(t, Qtd))
    )
)"""),
        (2, "Carteira por situação de entrega", agrupar("colGestao", "Situacao")),
        (3, "Atendimento SAP (entrada de mercadoria)", agrupar("colGestao", "AtendEM")),
    ], "A")
    filhos += aba("Desempenho da diligência", [
        ("Contatos registrados (30 dias)", "CountRows(colGestaoComun)", "nfCorAzul"),
        ("Cobertura de contato no prazo", f'Text(CountRows(Filter({aberto}, DiasSemContato <= LimiteContato)) / Max(1, CountRows({aberto})), "0%")', "nfCorVerde"),
        ("Atrasados sem contato no prazo", f"CountRows(Filter({atrasado}, DiasSemContato > LimiteContato))", "nfCorVermelho"),
        ("Itens com data negociada", f"CountRows(Filter({aberto}, !IsBlank(DataNegociada)))", "nfCorAzul"),
        ("Itens antecipados", "CountRows(Filter(colGestao, GanhoDias > 0))", "nfCorVerde"),
        ("Ganho negociado (dias)", "Sum(Filter(colGestao, GanhoDias > 0), GanhoDias)", "nfCorVerde"),
    ], [
        (4, "Contatos por diligenciador (30 dias)", agrupar("colGestaoComun", 'Coalesce(Responsavel_Pessoa.DisplayName, Responsavel, "(sem nome)")')),
        (5, "Atrasados por responsável da RC", agrupar(atrasado, resp)),
        (6, "Ganho negociado (dias) por responsável", agrupar("Filter(colGestao, GanhoDias > 0)", resp, soma="GanhoDias")),
    ], "B")
    filhos += aba("Notas fiscais", [
        ("Recebido, fatura pendente", 'CountRows(Filter(colGestao, ProblemaNF = "Mercadoria recebida, fatura pendente"))', "nfCorLaranja"),
        ("NF lançada sem mercadoria", 'CountRows(Filter(colGestao, ProblemaNF = "NF lançada sem entrada de mercadoria"))', "nfCorLaranja"),
        ("Divergência informada", 'CountRows(Filter(colGestao, ProblemaNF = "Divergência de NF informada"))', "nfCorVermelho"),
        ("NF emitida, material sem entrada", 'CountRows(Filter(colGestao, StartsWith(SituacaoFisica, "NF emitida")))', "nfCorAzul"),
        ("Itens de PO bloqueado", f'CountRows(Filter({aberto}, ItemPO.Status_Liberacao_PO = "Bloqueado"))', "nfCorSuave"),
    ], [
        (7, "Problemas de NF por fornecedor", agrupar('Filter(colGestao, ProblemaNF <> "")', 'Coalesce(ItemPO.Nome_Fornecedor, "(sem fornecedor)")')),
    ], "C")
    vis_nf = 'locAba = "Notas fiscais"'
    linha_nf = [
        retangulo(f"nfFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1", "nfCorCartao", OnSelect="Select(Parent)"),
        rotulo(f"nfProb_{s}", "ThisItem.ProblemaNF", 12, 2, "Parent.TemplateWidth - 24", 22, 10, "nfCorLaranja", True, OnSelect="Select(Parent)"),
        rotulo(f"nfItem_{s}", '"PO " & ThisItem.Numero_PO & "/" & ThisItem.Item_PO & " · " & Coalesce(ThisItem.ItemPO.Nome_Fornecedor, "—") & " · grupo " & ThisItem.Grupo & " · EF " & ThisItem.AtendEF & " / EM " & ThisItem.AtendEM',
               12, 24, "Parent.TemplateWidth - 24", 22, 9, "nfCorTexto", OnSelect="Select(Parent)"),
    ]
    filhos += [
        rotulo(f"nfTitulo_{s}", txt("Itens com problema de NF (clique para diligenciar)"), f"{xs[1]}", yb, f"2 * {w} + 16", 28, 11,
               "nfCorTexto", True, Visible=vis_nf),
        galeria(f"galNF_{s}", 'Sort(Filter(colGestao, ProblemaNF <> ""), ProblemaNF)', f"{xs[1]}", f"{yb} + 32", f"2 * {w} + 16",
                f"{h} - 32", 50, linha_nf, Visible=vis_nf,
                OnSelect="Set(gblItem, ThisItem); Navigate(scrDossie, ScreenTransition.None)"),
    ]
    filhos += tabela_resumo(s, 1, "Grupo", "Grupo de compradores", yk, 'locAba = "Grupos de compradores"')
    filhos += tabela_resumo(s, 2, 'Coalesce(ItemPO.Nome_Fornecedor, "(sem fornecedor)")', "Fornecedor", yk,
                            'locAba = "Fornecedores"')
    filhos.append(rotulo(f"lblBI_{s}", txt(
        "Situação SAP: entrada de mercadoria (EM) completa = entregue; EF/EM classificados pela tabela da Administração > "
        "Status SAP. Ganho = remessa SAP − data negociada. Valor = coluna Valor_1_SAP. Escopo: itens ativos carregados no app."),
        X0, "Parent.Height - 48", LARG_CONTEUDO, 40, 9, "nfCorSuave"))
    filhos += bloqueio(s, "!nfPodeGestao", "Acesso restrito aos perfis Coordenador, Gestor, Auditor e Administrador.")
    return tela("gestao", "scrGestao", "Visão da gestão", filhos, on_visible, apos_atualizar=";\n" + recalcular)


def tela_admin():
    s = "Admin"
    on_visible = """If(
    !nfPodeAdmin,
    false,
    Concurrent(
        ClearCollect(colExec, FirstN(Sort(DIL_Integracao_Execucao, ID, SortOrder.Descending), 30)),
        ClearCollect(colConfig, DIL_Configuracao),
        ClearCollect(colSLA, DIL_SLA),
        ClearCollect(colHistRecente, FirstN(Sort(DIL_Historico, ID, SortOrder.Descending), 100))
    );
    UpdateContext({locAba: Coalesce(locAba, "Integrações")})
)"""
    abas = ["Integrações", "Parâmetros e perfis", "Regras SLA", "Modelos de e-mail", "Status SAP", "Auditoria"]
    filhos = []
    for i, a in enumerate(abas):
        filhos.append(botao(f"btnAba{i}_{s}", txt(a), f"{X0} + {i * 182}", Y0, 176, 34, f'UpdateContext({{locAba: "{a}"}})',
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
        galeria(f"galAud_{s}", "colHistRecente", X0, yg, LARG_CONTEUDO, alt, 52, [
            fundo("au"),
            rotulo(f"auData_{s}", data("ThisItem.Data_Evento", "dd/mm/yyyy hh:mm"), 12, 0, 120, "Parent.TemplateHeight", 9, "nfCorTexto"),
            rotulo(f"auTipo_{s}", 'Coalesce(ThisItem.Origem_Evento.Value, "—") & Char(10) & Coalesce(ThisItem.Tipo_Evento.Value, "—")',
                   136, 0, 150, "Parent.TemplateHeight", 9, "nfCorAzul", True),
            rotulo(f"auDesc_{s}", 'ThisItem.Chave_Entidade & Char(10) & Coalesce(ThisItem.Observacao, "")', 292, 0,
                   "Parent.TemplateWidth - 300", "Parent.TemplateHeight", 9, "nfCorTexto"),
        ], Visible='locAba = "Auditoria"'),
    ]
    filhos += admin_modelos(s, yg, alt)
    filhos += admin_status_sap(s, yg, alt)
    filhos += bloqueio(s, "!nfPodeAdmin", "Acesso restrito aos perfis Gestor e Administrador.")
    return tela("admin", "scrAdmin", "Administração e configurações", filhos, on_visible)


def linhas_fx(*linhas):
    """Texto de várias linhas em Power Fx (Char(10) entre as linhas)."""
    return " & Char(10) & ".join(txt(l) for l in linhas)


# Modelos criados pelo botão "Criar modelos padrão" (só os que ainda não existem, pelo nome).
MODELOS_PADRAO = [
    ("Cobrança de atraso", "Pedido {PO} item {ITEM_PO} – entrega em atraso",
     ["Prezado(a) {CONTATO},", "",
      "O item {ITEM_PO} do pedido {PO} ({MATERIAL} – {DESCRICAO}) tinha entrega prevista para {DATA_PREVISTA} "
      "e está em atraso há {DIAS_ATRASO} dia(s).", "",
      "Solicitamos, por favor, a nova data de entrega confirmada e o motivo do atraso.", "",
      "Atenciosamente,", "{DILIGENCIADOR}"]),
    ("Confirmação de entrega", "Pedido {PO} item {ITEM_PO} – confirmação da data de entrega",
     ["Prezado(a) {CONTATO},", "",
      "Pedimos a confirmação da entrega do item {ITEM_PO} do pedido {PO} ({MATERIAL} – {DESCRICAO}), "
      "prevista para {DATA_PREVISTA}.", "",
      "Caso haja qualquer risco para essa data, por favor nos informe.", "",
      "Atenciosamente,", "{DILIGENCIADOR}"]),
    ("Pedido de antecipação", "Pedido {PO} item {ITEM_PO} – solicitação de antecipação",
     ["Prezado(a) {CONTATO},", "",
      "O item {ITEM_PO} do pedido {PO} ({MATERIAL} – {DESCRICAO}) é prioritário para nós. "
      "A entrega está prevista para {DATA_PREVISTA}.", "",
      "Verifique, por favor, a possibilidade de antecipar a entrega e nos informe a melhor data possível.", "",
      "Atenciosamente,", "{DILIGENCIADOR}"]),
    ("Saldo de entrega parcial", "Pedido {PO} item {ITEM_PO} – saldo pendente de entrega",
     ["Prezado(a) {CONTATO},", "",
      "Recebemos parcialmente o item {ITEM_PO} do pedido {PO} ({MATERIAL} – {DESCRICAO}): {SALDO}.", "",
      "Solicitamos a data de entrega do saldo pendente.", "",
      "Atenciosamente,", "{DILIGENCIADOR}"]),
    ("Divergência de nota fiscal", "Pedido {PO} item {ITEM_PO} – divergência na nota fiscal",
     ["Prezado(a) {CONTATO},", "",
      "O material do item {ITEM_PO} do pedido {PO} ({MATERIAL} – {DESCRICAO}) foi recebido, mas a nota fiscal "
      "apresenta divergência que impede a entrada no sistema.", "",
      "Solicitamos a correção (carta de correção ou nova NF) para regularizarmos o recebimento.", "",
      "Atenciosamente,", "{DILIGENCIADOR}"]),
]


def admin_modelos(s, yg, alt):
    """Modelos de e-mail em DIL_Configuracao (Categoria Modelo_Email; Nome_Parametro = nome do modelo,
    Valor_Texto = assunto, Descricao = corpo, Ativo). Usados no botão "Abrir e-mail" da diligência."""
    vis = 'locAba = "Modelos de e-mail"'
    modelos = 'Filter(colConfig, Lower(Trim(Categoria)) = "modelo_email")'
    sel = f"galModelos_{s}.Selected"
    novo = "locModeloNovo"
    wl = f"({LARG_CONTEUDO}) * 0.34"
    xe = f"{X0} + ({LARG_CONTEUDO}) * 0.34 + 16"
    we = f"({LARG_CONTEUDO}) * 0.66 - 16"
    recarregar = ('ClearCollect(colConfig, DIL_Configuracao);\n'
                  'ClearCollect(colModelos, Filter(colConfig, Lower(Trim(Categoria)) = "modelo_email" && Ativo = true))')
    gravar_modelo = f"""If(
    IsBlank(Trim(txtModNome_{s}.Text)) || IsBlank(Trim(txtModAssunto_{s}.Text)) || IsBlank(Trim(txtModCorpo_{s}.Text)),
    Notify("Preencha nome, assunto e corpo do modelo.", NotificationType.Error),
    {novo} && !IsBlank(LookUp({modelos}, Lower(Trim(Nome_Parametro)) = Lower(Trim(txtModNome_{s}.Text)))),
    Notify("Já existe um modelo com esse nome.", NotificationType.Error),
    !{novo} && IsBlank({sel}),
    Notify("Selecione um modelo ou clique em Novo modelo.", NotificationType.Error),
    IfError(
        Patch(
            DIL_Configuracao,
            If({novo}, Defaults(DIL_Configuracao), LookUp(DIL_Configuracao, ID = {sel}.ID)),
            {{
                Chave_Configuracao: If({novo}, "CFG-MODELO-" & Text(Now(), "yyyymmddhhmmss"), {sel}.Chave_Configuracao),
                Categoria: "Modelo_Email",
                Nome_Parametro: Trim(txtModNome_{s}.Text),
                Valor_Texto: txtModAssunto_{s}.Text,
                Descricao: txtModCorpo_{s}.Text,
                Ativo: ddModAtivo_{s}.Selected.Value = "Sim"
            }}
        ),
        Notify("Não foi possível gravar o modelo: " & FirstError.Message, NotificationType.Error),
        Notify("Modelo gravado.", NotificationType.Success);
        {recarregar};
        UpdateContext({{{novo}: false}})
    )
)"""
    padrao = "Table(" + ", ".join(
        f'{{Nome: {txt(n)}, Assunto: {txt(a)}, Corpo: {linhas_fx(*c)}}}' for n, a, c in MODELOS_PADRAO) + ")"
    criar_padrao = f"""IfError(
    ForAll(
        Filter({padrao} As m, IsBlank(LookUp({modelos}, Lower(Trim(Nome_Parametro)) = Lower(m.Nome)))) As p,
        Patch(
            DIL_Configuracao,
            Defaults(DIL_Configuracao),
            {{
                Chave_Configuracao: "CFG-MODELO-" & Text(Now(), "yyyymmddhhmmss") & "-" & Left(p.Nome, 12),
                Categoria: "Modelo_Email",
                Nome_Parametro: p.Nome,
                Valor_Texto: p.Assunto,
                Descricao: p.Corpo,
                Ativo: true
            }}
        )
    ),
    Notify("Não foi possível criar os modelos: " & FirstError.Message, NotificationType.Error),
    Notify("Modelos padrão criados (os já existentes foram mantidos).", NotificationType.Success);
    {recarregar}
)"""
    linha = [
        retangulo(f"moFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected && !locModeloNovo, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        rotulo(f"moNome_{s}", 'ThisItem.Nome_Parametro & If(ThisItem.Ativo, "", "  (inativo)")', 12, 2,
               "Parent.TemplateWidth - 24", 24, 10, "If(ThisItem.Ativo, nfCorTexto, nfCorSuave)", True, OnSelect="Select(Parent)"),
        rotulo(f"moAssunto_{s}", 'Coalesce(ThisItem.Valor_Texto, "")', 12, 26, "Parent.TemplateWidth - 24", 22, 9,
               "nfCorSuave", OnSelect="Select(Parent)"),
    ]
    variaveis = ", ".join(k for k, _ in VARIAVEIS_MODELO)
    filhos = [
        botao(f"btnModNovo_{s}", txt("Novo modelo"), X0, yg, 150, 34, f"UpdateContext({{{novo}: true}})", Visible=vis),
        botao(f"btnModPadrao_{s}", txt("Criar modelos padrão"), X0 + 160, yg, 200, 34, criar_padrao, primario=False,
              Visible=vis),
        galeria(f"galModelos_{s}", f"Sort({modelos}, Nome_Parametro)", X0, yg + 44, wl, f"{alt} - 44", 52, linha,
                Visible=vis, OnSelect=f"UpdateContext({{{novo}: false}})"),
        rotulo(f"moVazio_{s}", txt("Nenhum modelo cadastrado. Use \"Criar modelos padrão\" ou \"Novo modelo\"."),
               X0, yg + 52, wl, 44, 10, "nfCorSuave", Visible=f"{vis} && IsEmpty(galModelos_{s}.AllItems)"),
        retangulo(f"moPainel_{s}", xe, yg, we, alt, BorderColor="nfCorAzul", BorderThickness=1, Visible=vis),
        rotulo(f"moModo_{s}", f'If({novo}, "Novo modelo", "Editar: " & Coalesce({sel}.Nome_Parametro, "(selecione à esquerda)"))',
               f"{xe} + 16", yg + 8, f"{we} - 32", 28, 12, "nfCorTexto", True, Visible=vis),
    ]
    campos = [
        ("Nome *", entrada(f"txtModNome_{s}", "Ex.: Cobrança de atraso", 0, 0, 0,
                           padrao=f'If({novo}, "", {sel}.Nome_Parametro)', Visible=vis), 36),
        ("Assunto *", entrada(f"txtModAssunto_{s}", "Ex.: Pedido {PO} item {ITEM_PO} – entrega em atraso", 0, 0, 0,
                              padrao=f'If({novo}, "", {sel}.Valor_Texto)', Visible=vis), 36),
        ("Corpo *", entrada(f"txtModCorpo_{s}", "Texto do e-mail com as variáveis abaixo", 0, 0, 0, multilinha=True,
                            padrao=f'If({novo}, "", {sel}.Descricao)', Visible=vis), 200),
        ("Ativo", lista_suspensa(f"ddModAtivo_{s}", '["Sim", "Não"]', 0, 0, 0,
                                 Default=f'If({novo} || {sel}.Ativo, "Sim", "Não")', Visible=vis), 36),
    ]
    yy = yg + 44
    for i, (t, c, hh) in enumerate(campos):
        pr = c[next(iter(c))]["Properties"]
        pr["X"], pr["Y"], pr["Width"], pr["Height"] = f(f"{xe} + 110"), f(yy), f(f"{we} - 126"), f(hh)
        filhos += [rotulo(f"moT{i}_{s}", txt(t), f"{xe} + 16", yy, 90, 36, 9, "nfCorSuave", Visible=vis), c]
        yy += hh + 8
    filhos += [
        botao(f"btnModGravar_{s}", txt("Gravar modelo"), f"{xe} + 110", yy, 170, 36, gravar_modelo, Visible=vis),
        rotulo(f"moVars_{s}", txt("Variáveis: " + variaveis + ". O e-mail abre no programa de e-mail do usuário "
                                  "(Diligenciar item > Abrir e-mail); o app não envia e-mails."),
               f"{xe} + 16", yy + 44, f"{we} - 32", 60, 9, "nfCorSuave", Visible=vis),
        rotulo(f"moDil09_{s}", txt("E-mails automáticos de escalonamento (DIL_09 → DIL_10) continuam com o texto fixo do "
                                   "DIL_09 até a alteração planejada do DIL_09 ser aprovada (docs/Melhorias_v10.md)."),
               f"{xe} + 16", yy + 104, f"{we} - 32", 44, 9, "nfCorLaranja", Visible=vis),
    ]
    return filhos


def admin_status_sap(s, yg, alt):
    """Classificação dos códigos Status_EM/Status_EF do SAP (DIL_Configuracao, Categoria Status_SAP;
    Nome_Parametro = código, Valor_Texto = Completo, Parcial ou Não atendido)."""
    vis = 'locAba = "Status SAP"'
    sel = f"galStatus_{s}.Selected"
    wl = f"({LARG_CONTEUDO}) * 0.6"
    xe = f"{X0} + ({LARG_CONTEUDO}) * 0.6 + 16"
    we = f"({LARG_CONTEUDO}) * 0.4 - 16"
    codigos = """AddColumns(
    Filter(
        Distinct(Ungroup(Table({t: ForAll(colItensPO, {Cod: Trim(Status_EM)})}, {t: ForAll(colItensPO, {Cod: Trim(Status_EF)})}), t), Cod),
        !IsBlank(Value)
    ),
    QtdEM, CountRows(Filter(colItensPO, Trim(Status_EM) = Value)),
    QtdEF, CountRows(Filter(colItensPO, Trim(Status_EF) = Value)),
    Configurado, !IsBlank(LookUp(colStatusSAP, Codigo = Value))
)"""
    gravar = f"""If(
    IsBlank(Trim(txtStCod_{s}.Text)),
    Notify("Informe o código.", NotificationType.Error),
    With(
        {{existente: LookUp(colConfig, Lower(Trim(Categoria)) = "status_sap" && Trim(Nome_Parametro) = Trim(txtStCod_{s}.Text))}},
        IfError(
            Patch(
                DIL_Configuracao,
                If(IsBlank(existente), Defaults(DIL_Configuracao), LookUp(DIL_Configuracao, ID = existente.ID)),
                {{
                    Chave_Configuracao: Coalesce(existente.Chave_Configuracao, "CFG-STATUS-SAP-" & Trim(txtStCod_{s}.Text)),
                    Categoria: "Status_SAP",
                    Nome_Parametro: Trim(txtStCod_{s}.Text),
                    Valor_Texto: ddStClasse_{s}.Selected.Value,
                    Descricao: txtStDesc_{s}.Text,
                    Ativo: true
                }}
            ),
            Notify("Não foi possível gravar: " & FirstError.Message, NotificationType.Error),
            ClearCollect(colConfig, DIL_Configuracao);
            {CARGA};
            Notify("Classificação gravada e carteira recalculada.", NotificationType.Success)
        )
    )
)"""
    linha = [
        retangulo(f"stFundo_{s}", 0, 0, "Parent.TemplateWidth", "Parent.TemplateHeight - 1",
                  "If(ThisItem.IsSelected, nfCorFundo, nfCorCartao)", OnSelect="Select(Parent)"),
        rotulo(f"stCod_{s}", '"Código " & ThisItem.Value', 12, 0, 120, "Parent.TemplateHeight", 11, "nfCorTexto", True,
               OnSelect="Select(Parent)"),
        rotulo(f"stClasse_{s}", classe_sap("ThisItem.Value") + ' & If(ThisItem.Configurado, "  (tabela)", "  (padrão do app)")',
               136, 0, 240, "Parent.TemplateHeight", 10,
               'If(ThisItem.Configurado, nfCorVerde, nfCorLaranja)', True, OnSelect="Select(Parent)"),
        rotulo(f"stQtd_{s}", '"EM: " & ThisItem.QtdEM & " itens  ·  EF: " & ThisItem.QtdEF & " itens"', 380, 0,
               "Parent.TemplateWidth - 390", "Parent.TemplateHeight", 9, "nfCorSuave", OnSelect="Select(Parent)"),
    ]
    filhos = [
        rotulo(f"stInfo_{s}", txt("Códigos de status de entrada de mercadoria (EM) e de fatura (EF) encontrados em "
                                  "DIL_Itens_Pedido. Confirme a classificação de cada um: \"(padrão do app)\" = 4 Completo, "
                                  "3 Parcial, demais Não atendido."),
               X0, yg, wl, 44, 9, "nfCorSuave", Visible=vis),
        galeria(f"galStatus_{s}", f"Sort({codigos}, Value)", X0, yg + 48, wl, f"{alt} - 48", 44, linha, Visible=vis),
        retangulo(f"stPainel_{s}", xe, yg, we, 330, BorderColor="nfCorAzul", BorderThickness=1, Visible=vis),
        rotulo(f"stTitulo_{s}", txt("Classificar código"), f"{xe} + 16", yg + 8, f"{we} - 32", 28, 12, "nfCorTexto", True,
               Visible=vis),
    ]
    campos = [
        ("Código *", entrada(f"txtStCod_{s}", "Ex.: 3", 0, 0, 0, padrao=f'Coalesce({sel}.Value, "")', Visible=vis)),
        ("Classe *", lista_suspensa(f"ddStClasse_{s}", "nfClassesSAP", 0, 0, 0,
                                    Default=classe_sap(f'Coalesce({sel}.Value, "")'), Visible=vis)),
        ("Descrição", entrada(f"txtStDesc_{s}", "Ex.: Parcialmente atendido", 0, 0, 0,
                              padrao=f'Coalesce(LookUp(colConfig, Lower(Trim(Categoria)) = "status_sap" && Trim(Nome_Parametro) = {sel}.Value, Descricao), "")',
                              Visible=vis)),
    ]
    for i, (t, c) in enumerate(campos):
        yy = yg + 48 + i * 46
        pr = c[next(iter(c))]["Properties"]
        pr["X"], pr["Y"], pr["Width"] = f(f"{xe} + 110"), f(yy), f(f"{we} - 126")
        filhos += [rotulo(f"stT{i}_{s}", txt(t), f"{xe} + 16", yy, 90, 36, 9, "nfCorSuave", Visible=vis), c]
    yb = yg + 48 + len(campos) * 46
    filhos += [
        botao(f"btnStGravar_{s}", txt("Gravar classificação"), f"{xe} + 110", yb, 200, 36, gravar, Visible=vis),
        rotulo(f"stNota_{s}", txt("Completo na entrada de mercadoria = entregue (sai da cobrança). Parcial = continua na "
                                  "fila para diligenciar o saldo. A gravação recalcula a carteira."),
               f"{xe} + 16", yb + 44, f"{we} - 32", 60, 9, "nfCorSuave", Visible=vis),
    ]
    return filhos


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
