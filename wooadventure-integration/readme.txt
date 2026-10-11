=== WooAdventure Integration ===
Contributors: juniorpotenza
Requires at least: 5.3
Tested up to: 6.8
Requires PHP: 7.3
Stable tag: 2.22.0
License: Proprietary

Plugin de ecoturismo para WooCommerce: saídas, reservas, participantes, termos assinados, check-in e integração com a seguradora Roca.

== Description ==

O plugin gerencia a operação de passeios sobre o WooCommerce.

* Saídas com data, capacidade e guia.
* Reservas com bloqueio temporário (15 minutos) e controle de vagas.
* Cadastro de participantes no checkout (bloco e clássico).
* Documentos jurídicos versionados, aceite e assinatura.
* Agenda, scanner de check-in offline e manifesto.
* Envio de dados à API da seguradora Roca.
* Exportação e anonimização de dados pessoais (LGPD).

== Requirements ==

* WordPress 5.3 ou superior.
* PHP 7.3 ou superior.
* WooCommerce 8.9 ou superior.

Os mínimos de WordPress e PHP vêm das funções usadas no código (wp_timezone, wp_date, operador ??, random_bytes, setcookie com array de opções). O mínimo do WooCommerce vem do cabeçalho do plugin.

== Installation ==

1. Envie a pasta wooadventure-integration para wp-content/plugins/.
2. Ative o plugin em Plugins.
3. Abra WooCommerce > Adventure Config e preencha as configurações.
4. Cadastre as saídas e os documentos jurídicos.

== Changelog ==

= 2.22.0 =
* Segurança: participantes só editáveis pelo dono do pedido (IDOR); pedido travado recusa edição; CPF e idade revalidados no servidor.
* Segurança: assinatura exige order_key do pedido; erro idêntico para vínculo inválido; limite de tentativas antes da consulta.
* Segurança: assinaturas em diretório privado; cookies HMAC com expiração; tickets só para reserva confirmada.
* Reservas: reserva em espera (hold) de 15 minutos; confirmação com nova checagem de vagas; expiração por cron a cada 5 minutos.
* Scanner: escape de HTML, manifesto por guia, scripts com versão fixa e SRI.
* LGPD: responsável legal obrigatório para menores; aceite com cópia imutável do texto; UNIQUE contra aceite duplicado; exportador e eliminador de dados pessoais; opção de apagar dados ao desinstalar (desligada por padrão).
* Acessibilidade: erros por campo no checkout, calendário com teclado, modal da conta com foco preso, scanner com alvos de 44px.
* Correções: data de nascimento futura é recusada; agenda operacional mostra só reservas confirmadas.
* Infra: migração por passos versionados; testes PHPUnit e Node; CI no GitHub Actions; cabeçalho e text domain.

= 2.21.0 =
* Versão anterior à correção.
