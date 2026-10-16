# Roadmap de implementação

Legenda: [x] feito. [~] parcial. [ ] pendente.

## 2.4 — Fundação operacional

- [x] Cadastro administrativo de saídas.
- [x] Role Guia de Aventura e capabilities operacionais.
- [~] Log de auditoria sem dados pessoais sensíveis. Nem todas as ações gravam evento.
- [x] Migração por passos versionados.

## 2.5 — Reservas e capacidade

- [x] Tabela de reservas por saída.
- [x] Controle de vagas com bloqueio temporário (hold) de 15 minutos e expiração por cron.
- [x] Checkout com saída selecionada e validação de capacidade.
- [ ] Lista de espera.

## 2.6 — Dossiê e identidade jurídica do participante

- [x] Participante canônico vinculado à reserva.
- [x] Documento jurídico versionado com hash.
- [x] Aceite jurídico ligado a Participant + Reservation + versão exata, com cópia do texto.
- [x] Assinatura ligada à identidade canônica, com prova de posse (order_key) e arquivo privado.
- [x] Responsável por menor no servidor. O formulário externo deve enviar `responsavel_nome` e `responsavel_cpf`.
- [ ] Ficha pré-atividade.
- [~] Portal do participante. Existe apenas em Minha Conta.

## 2.7 — Operação de campo

- [x] Scanner com acesso mínimo.
- [~] Manifesto por saída. Falta a ficha completa.
- [ ] Checklist.
- [ ] Incidentes.
- [ ] Equipamentos.
- [ ] Relatório final.

## 2.8 — Integrações e inteligência

- [~] API de seguro. Não há nova tentativa após falha.
- [ ] Fila de seguro com retentativas e reconciliação.
- [ ] Comunicação automatizada.
- [ ] Relatórios.
- [ ] Parceiros e comissões.
