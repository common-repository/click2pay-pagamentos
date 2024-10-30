=== Click2pay para WooCommerce | PIX, Cartão de Crédito e Boleto Bancário ===
Contributors: click2paybrasil, amgnando
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: gateway, pix, cartão de crédito, boleto, assinaturas, subscriptions
Tested up to: 6.6
Stable tag: 1.3.0
Requires PHP: 7.4

Ofereça a seus clientes pagamentos via Pix, assinatura recorrente, cartão de crédito ou boleto bancário, com as melhores tarifas!

== Description ==

### Módulo de integração da Click2Pay com o WooCommerce.

Ofereça a seus clientes pagamentos via Pix, assinatura recorrente, cartão de crédito ou boleto bancário, com as melhores tarifas!

### RECURSOS

- Pix com aprovação automátca na tela do QR Code.
- Cartão de Crédito com opção de compra com 1 Clique.
- Assinatura: Cobre seus clientes utilizando a recorrência através da integração com os plugins WooCommerce Subscriptions.
- Assinaturas através do plugin gratuito YITH.
- Boleto Bancário com versão mobile friendly.

### REQUISITOS & COMPATIBILIDADE

- Requer conta na Click2Pay
- Requer WooCommerce 4.0.0 ou posterior para funcionar.
- Requer versão do PHP maior ou igual a 7.1.
- Requer utilização do plugin Brazillian Market, para utilização de campos como CPF e/ou Data de Aniversário. O CPF é obrigatório nas transações, já o campo de data de aniversário é obrigatório apenas para os clientes que utilizam o serviço de anti fraude em transações com cartões de crédito.

### SUPORTE TÉCNICO

- Atendimento de segunda a sexta-feira em horário comercial através do e-mail suporte@click2pay.com.br


== Installation ==
- Abra a conta da sua empresa na Click2Pay (https://click2pay.com.br);
- Após o credenciamento na Click2Pay você receberá suas credenciais (Client ID, Client Secret e Public Key);
- Envie os arquivos do plugin para a pasta wp-content/plugins, ou instale usando o instalador de plugins do WordPress.
- Após a instalação e ativação do plugin acesse o menu WooCommerce > Configurações > Finalizar Compra > Click2Pay
- Habilite os métodos de pagamento que deseja (Pix, Cartão de Crédito ou Boleto bancário)
- O passo a passo detalhado da configuração está disponível no link: https://click2pay.readme.io/reference/wordpress-woocommerce

== Frequently Asked Questions ==

= Como criar uma conta =

Abra a conta da sua empresa na Click2Pay no link a seguir: https://click2pay.com.br

== Screenshots ==

== Changelog ==

= 1.3.0 =

Release date:

- Exibir boleto/Pix em páginas de obrigado personalizadas
- Corrigir mensagens de aviso do PHP 8.3

= 1.2.0 =

Release date: 2023-12-14

- Corrigir problema que em alguns casos a renovação não acontecia por falta de token

= 1.1.5 =

Release date: 2023-10-27

- Corrigir renovação de assinaturas após atualização HPOS

= 1.1.4 =

Release date: 2023-10-10

- Compatibilidade com WooCommerce HPOS
- Melhorar validação dos endereços em cenários específicos

= 1.1.3 =

Release date: 2023-06-16

- Prevenir erro fatal quando há erro na troca da forma de pagamento

= 1.1.2 =

Release date: 2023-03-30

- Corrigir mensagem de erro quando há um problema na conta Click2pay
- Deixar status como "Malsucedido" quando há problemas no pagamento com cartão

= 1.1.1 =

Release date: 2023-03-30

- Mover pagamento Pix para Página de obrigado (order-received)

= 1.1.0 =

Release date: 2023-03-19

- Integração com Checkout Field Editor for WooCommerce (Pro)
- Integração com WooCommerce Subscriptions

= 1.0.0 =

Release date: 2023-02-10

- Primeira versão.
