# Melhorias v10 — status de entrega SAP, entrega parcial, gestão e modelos de e-mail

Data: 23/10/2026 · Situação: **candidata** (`Sistema_Diligenciamento_COMPLETO_v10.msapp`), validada offline;
abertura no Studio e testes no tenant pendentes.

## 1. Problema relatado

O app considerava apenas a data de remessa (ou a data negociada) para marcar atraso. Itens que já chegaram
continuavam como "Atrasado", porque o status de entrada de mercadoria/fatura do SAP não era usado.

## 2. O que existe nas listas (evidência)

| Dado | Onde | Observação |
|---|---|---|
| Status de entrada de fatura (EF) | `DIL_Importacao_SAP.Status_EF_SAP` (código) e `Descricao_Status_EF_SAP` (texto); copiado pelo DIL_02 para `DIL_Itens_Pedido.Status_EF` (**só o código**) | Nos dados de teste: `4` = Completamente atendido, `2` = Não atendido |
| Status de entrada de mercadoria (EM) | idem com `Status_EM_SAP` / `Descricao_Status_EM_SAP` → `DIL_Itens_Pedido.Status_EM` | idem |
| Parcialmente atendido | **não aparece nos dados de teste** | código assumido `3` (padrão SAP) — confirmar em *Administração > Status SAP* |
| Quantidade pedida / entregue | **não existe** em nenhuma lista nem no relatório importado | ver §5 |
| Grupo de compradores | `DIL_Pedidos.Grupo_Compradores` (por pedido) | o app passa a usar `DIL_Pedidos` (15ª fonte, só leitura) |
| PO bloqueado | `DIL_Itens_Pedido.Status_Liberacao_PO` (`Liberado`/`Bloqueado`) | usado na gestão |
| Texto dos e-mails automáticos | DIL_09 monta `Assunto`/`Resumo` da `COM-SLA-*`; DIL_10 apenas envia esses campos | modelos dos automáticos = mudança no **DIL_09** (o DIL_10 não muda) |

## 3. Regras adotadas na v10 (sem alterar listas nem fluxos)

**Classificação dos códigos EF/EM**: tabela em `DIL_Configuracao` (Categoria `Status_SAP`, Nome_Parametro = código,
Valor_Texto = `Completo`/`Parcial`/`Não atendido`), editável em *Administração > Status SAP*. Sem linha na tabela:
`4` → Completo, `3` → Parcial, demais → Não atendido (também aceita a descrição por extenso).

| Situação SAP | Tratamento no app |
|---|---|
| **EM Completo** | **Entregue** — sai da cobrança, da agenda e dos atrasados (independe da data). |
| EM ou EF **Parcial** | Continua na fila como **Parcialmente atendido: diligenciar o saldo**; se a data passou, fica "Atrasado … cobrar o saldo". |
| EM Completo e EF Não atendido/Parcial | Entregue, mas sinalizado **"Mercadoria recebida, fatura pendente"** (problema de nota). |
| EF Completo e EM Não atendido/Parcial | **Não** conta como entregue: "NF lançada sem entrada de mercadoria" — confirmar chegada do material. |
| Ambos Não atendido | Regra de datas (atrasado / vence em 7 dias / em dia). |

> Decisão a confirmar: a v10 usa a **entrada de mercadoria (EM)** para dizer que o material chegou. Se a NF lançada
> (EF completo) também deve encerrar a cobrança, é uma linha a mudar no gerador.

Registros do diligenciador continuam valendo: "Material entregue" e "Chegou com divergência na NF" encerram a cobrança
mesmo sem EM no SAP (caso da nota com divergência que impede a entrada).

**Entrega parcial informada pelo diligenciador** (novo resultado no *Registrar contato*): data da chegada,
quantidade recebida e quantidade pedida. Fica gravado no Assunto do contato
(`[Entrega parcial] dd/mm/aaaa — recebido 5 de 10 — <meio>`) e o app mostra "recebido 5 de 10 — faltam 5"
na fila, na diligência e no e-mail de saldo. Para a data do saldo, registrar em seguida "Nova data negociada".

## 4. Visão da gestão (nova)

Filtros por **grupo de compradores** e **fornecedor** valem para todas as abas.

| Aba | Indicadores |
|---|---|
| Carteira e prazos | itens em aberto; atrasados (qtd e % do aberto); valor atrasado (R$, `Valor_1_SAP`); entregas em 7 dias; parcialmente atendidos; sem data · gráficos: atraso por faixa (1–7, 8–15, 16–30, 31–60, >60 dias), carteira por situação, atendimento SAP (EM) |
| Desempenho da diligência | contatos registrados em 30 dias; cobertura de contato no prazo (% dos itens em aberto contatados dentro do limite da prioridade); atrasados sem contato no prazo; itens com data negociada; itens antecipados; ganho negociado (dias) · gráficos: contatos por diligenciador, atrasados por responsável, ganho por responsável |
| Notas fiscais | recebido com fatura pendente; NF lançada sem mercadoria; divergência informada; NF emitida sem entrada; itens de PO bloqueado · gráfico por fornecedor e lista clicável dos itens |
| Grupos de compradores | tabela por grupo: em aberto, atrasados, % atraso, atraso médio, parciais, problemas de NF, sem contato, valor atrasado, ganho |
| Fornecedores | mesma tabela por fornecedor (ranking por atrasados) |

Limites: os indicadores são uma foto da carteira ativa carregada (até 2.000 linhas por lista). Indicadores de
**pontualidade histórica** (entregas no prazo, lead time) precisam da data de entrada da mercadoria, que o relatório
SAP atual não traz (ver §5).

## 5. Pendências que dependem de aprovação (nada foi alterado)

### 5.1 Quantidades do SAP (para "comprei 10, recebi 5" automático)

1. Incluir no relatório SAP exportado as colunas de **quantidade do pedido** e **quantidade entregue** (ou "a fornecer")
   e, se possível, a **data da última entrada de mercadoria**.
2. Criar colunas **novas** (sem alterar as existentes) em `DIL_Importacao_SAP` e `DIL_Itens_Pedido`.
3. Mapear no DIL_01 (Excel → importação) e no DIL_02 (importação → item), com backup e teste de 3 linhas.
4. O app passa a calcular o saldo direto do SAP; o registro manual continua como alternativa.

Preciso dos nomes exatos das colunas no relatório para preparar o passo a passo.

### 5.2 Modelos para os e-mails automáticos (DIL_09)

Hoje o assunto/corpo do escalonamento são fixos no DIL_09. Plano (o DIL_10 **não** muda):

1. Exportar o DIL_09 (backup).
2. Antes do laço `Aplicar_a_cada_Escalonamento`, adicionar *Obter itens* em `DIL_Configuracao` com filtro
   `field_1 eq 'Modelo_Email' and field_2 eq 'Escalonamento SLA' and field_6 eq 1`, *Contagem superior* 1
   (nome sugerido `Obter_Modelo_SLA`).
3. Em *Criar item* (`COM-SLA-*`), trocar `field_9`/`field_10` por: se não houver modelo, a expressão atual; se houver,
   `Valor_Texto`/`Descricao` do modelo com `replace` de `{RC}`, `{PO}`, `{CHAVE}` e `{PRIORIDADE}`.
   O assunto deve caber em 255 caracteres.
4. Cadastrar o modelo "Escalonamento SLA" em *Administração > Modelos de e-mail*.
5. Teste de regressão R-02 (um e-mail por escalonamento, sem duplicar) e rollback = importar o backup.

## 6. Modelos de e-mail para o diligenciador (já na v10)

*Administração > Modelos de e-mail* grava em `DIL_Configuracao` (Categoria `Modelo_Email`, Nome_Parametro = nome,
Valor_Texto = assunto, Descricao = corpo, Ativo). O botão **Criar modelos padrão** cria: Cobrança de atraso,
Confirmação de entrega, Pedido de antecipação, Saldo de entrega parcial e Divergência de nota fiscal.

Em *Diligenciar item*, **Abrir e-mail** preenche o modelo com o item (variáveis `{PO}`, `{ITEM_PO}`, `{RC}`,
`{ITEM_RC}`, `{MATERIAL}`, `{DESCRICAO}`, `{FORNECEDOR}`, `{CONTATO}`, `{DATA_REMESSA}`, `{DATA_NEGOCIADA}`,
`{DATA_PREVISTA}`, `{DIAS_ATRASO}`, `{SALDO}`, `{GRUPO_COMPRADORES}`, `{DILIGENCIADOR}`) e abre no programa de
e-mail do próprio usuário (`mailto:`). O app **não envia** e-mail (o envio automático continua exclusivo do DIL_10).
Depois de enviar, o diligenciador grava o contato; o resumo já vem preenchido.

Alterações de configuração feitas pelo app não geram registro no `DIL_Historico` (o valor de escolha `Entidade`
para configuração não está confirmado); a lista guarda autor e data da modificação.
