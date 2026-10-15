// Rodada 4 do checkout: aviso do aceite, foco do resumo de erros, rolagem do título. Roda com: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const v = require('../../assets/js/wcai-checkout-block.js');

const SOURCE = fs.readFileSync(path.join(__dirname, '../../assets/js/wcai-checkout-block.js'), 'utf8');

test('consentHintVisible: nada antes da tentativa; um aviso depois; some ao aceitar', () => {
    assert.equal(v.consentHintVisible(false, false), false);
    assert.equal(v.consentHintVisible(false, true), true);
    assert.equal(v.consentHintVisible(true, true), false);
    assert.equal(v.consentHintVisible(true, false), false);
});

test('consentHintVisible: só true estrito conta como aceite/tentativa', () => {
    assert.equal(v.consentHintVisible('true', true), true);
    assert.equal(v.consentHintVisible(false, 'yes'), false);
});

test('focusField rola o campo ao centro e foca sem 2º salto', () => {
    const calls = [];
    const el = {
        scrollIntoView: (o) => calls.push(['scroll', o]),
        focus: (o) => calls.push(['focus', o])
    };

    v.focusField(el);

    assert.deepEqual(calls, [
        ['scroll', { behavior: 'smooth', block: 'center' }],
        ['focus', { preventScroll: true }]
    ]);
});

test('focusField ignora campo ausente', () => {
    assert.doesNotThrow(() => v.focusField(null));
});

test('headingScrollDelta: 0 se o título está visível; senão leva abaixo do cabeçalho', () => {
    assert.equal(v.headingScrollDelta(120, 150, 800, 32), 0);
    assert.equal(v.headingScrollDelta(-200, -170, 800, 32), -240);
    assert.equal(v.headingScrollDelta(900, 930, 800, 32), 860);
    assert.equal(v.headingScrollDelta(10, 40, 800, 32), -30);
});

test('countryHintVisible: aviso do país só na etapa 2', () => {
    assert.equal(v.countryHintVisible(2, 'AR', 'PR'), true);
    assert.equal(v.countryHintVisible(3, 'AR', 'PR'), false);
    assert.equal(v.countryHintVisible(4, 'AR', 'PR'), false);
    assert.equal(v.countryHintVisible(2, 'BR', 'PR'), false);
});

test('CSS: termos nativos somem só na etapa 4 com a caixa de aceite e sem checkbox nativo', () => {
    assert.ok(SOURCE.includes('body.wcai-checkout-step-4.wcai-consent-box .wc-block-checkout__terms:not(:has(input[type="checkbox"])){display:none!important}'));
    assert.ok(SOURCE.includes("classList.toggle('wcai-consent-box'"));
});

test('CSS: campos e botão de complemento do Woo com 44px / 16px', () => {
    assert.ok(SOURCE.includes('.wc-block-components-text-input input,body[class*="wcai-checkout-step-"] .wc-block-components-address-form__address_2-toggle{min-height:44px;font-size:16px'));
});

test('botão Finalizar pedido nunca usa aria-disabled nem estilo cinza', () => {
    assert.ok(!SOURCE.includes('aria-disabled'));
    assert.ok(!SOURCE.includes('#6b6b6b'));
});

test('há um só aviso de aceite (sem aviso extra junto ao rodapé)', () => {
    assert.equal(SOURCE.split("setAttribute('data-wcai-consent-hint'").length - 1, 1);
});

test('itens do resumo de erros são botões que focam o campo', () => {
    assert.ok(SOURCE.includes("document.createElement('button')"));
    assert.ok(/link\.addEventListener\('click', function \(\) \{\s*focusField\(entry\.el\);/.test(SOURCE));
});
