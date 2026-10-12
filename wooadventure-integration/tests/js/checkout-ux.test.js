// UX do wizard de checkout: máscara, rascunho, resumo de revisão, aceite e etapas. Roda com: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const v = require('../../assets/js/wcai-checkout-block.js');

const NOW = new Date(2026, 5, 15); // 15/06/2026

test('dateMask insere as barras ao digitar 8 dígitos seguidos', () => {
    assert.equal(v.dateMask('15051990'), '15/05/1990');
    assert.equal(v.dateMask('1505'), '15/05');
    assert.equal(v.dateMask('15'), '15');
    assert.equal(v.dateMask(''), '');
});

test('dateMask corta em 8 dígitos e ignora letras', () => {
    assert.equal(v.dateMask('15/05/19901505'), '15/05/1990');
    assert.equal(v.dateMask('1a5b0c5d1990'), '15/05/1990');
});

test('sanitizeDraft recupera data duplicada e CPF duplicado', () => {
    assert.equal(v.sanitizeDraft('date', '1505199015051990'), '15/05/1990');
    assert.equal(v.sanitizeDraft('cpf', '5299822472552998224725'), '529.982.247-25');
});

test('sanitizeDraft mantém valor já limpo (idempotente)', () => {
    assert.equal(v.sanitizeDraft('date', '15/05/1990'), '15/05/1990');
    assert.equal(v.sanitizeDraft('cpf', '529.982.247-25'), '529.982.247-25');
    assert.equal(v.sanitizeDraft('date', v.sanitizeDraft('date', '15051990')), '15/05/1990');
});

test('sanitizeDraft descarta rascunho de data que não vira data real', () => {
    assert.equal(v.sanitizeDraft('date', '31/02/1990'), '');
    assert.equal(v.sanitizeDraft('date', '15/05'), '');
    assert.equal(v.sanitizeDraft('date', 'abc'), '');
    assert.equal(v.sanitizeDraft('date', ''), '');
    assert.equal(v.sanitizeDraft('cpf', 'abc'), '');
});

test('maskCpfForReview mostra só 3 primeiros e 2 últimos dígitos', () => {
    assert.equal(v.maskCpfForReview('529.982.247-25'), '529.***.***-25');
    assert.equal(v.maskCpfForReview('52998224725'), '529.***.***-25');
    assert.equal(v.maskCpfForReview('123'), '');
    assert.equal(v.maskCpfForReview(undefined), '');
});

test('participantSummary lista titular primeiro e depois os adicionais com idade e CPF mascarado', () => {
    const items = [{
        additional_participants: [
            { type: 'adult', name: 'Ana Souza', cpf: '111.444.777-35', birthdate: '16/06/1990' },
            { type: 'child', name: 'Leo Souza', cpf: '', birthdate: '15/06/2015' }
        ]
    }];
    const holder = { name: 'Junior Potenza', cpf: '529.982.247-25', birthdate: '15/05/1990' };
    const rows = v.participantSummary(items, holder, NOW);

    assert.equal(rows.length, 3);
    assert.deepEqual(rows[0], { role: 'Titular', name: 'Junior Potenza', age: 36, cpf: '529.***.***-25', category: 'Adulto' });
    assert.deepEqual(rows[1], { role: 'Participante 2', name: 'Ana Souza', age: 35, cpf: '111.***.***-35', category: 'Adulto' });
    assert.deepEqual(rows[2], { role: 'Participante 3', name: 'Leo Souza', age: 11, cpf: '', category: 'Criança' });
});

test('participantSummary tolera titular vazio e data inválida (idade null)', () => {
    const rows = v.participantSummary([{ additional_participants: [{ name: 'X', birthdate: '31/02/2000' }] }], null, NOW);

    assert.equal(rows[0].name, '');
    assert.equal(rows[0].age, null);
    assert.equal(rows[1].age, null);
});

test('consentErrors bloqueia até o aceite ser exatamente true', () => {
    assert.deepEqual(Object.keys(v.consentErrors(false)), ['wcai-consent']);
    assert.equal(v.consentErrors(false)['wcai-consent'].hidden, true);
    assert.deepEqual(Object.keys(v.consentErrors('true')), ['wcai-consent']);
    assert.deepEqual(Object.keys(v.consentErrors(undefined)), ['wcai-consent']);
    assert.deepEqual(v.consentErrors(true), {});
});

test('buildPayload envia consent como booleano estrito', () => {
    const items = [{ key: 'k', product_id: 1, variation_id: 0, departure_id: 3, adults: 1, children: 0, additional_participants: [], name: 'x' }];

    assert.equal(v.buildPayload(items, true).consent, true);
    assert.equal(v.buildPayload(items, false).consent, false);
    assert.equal(v.buildPayload(items, 'yes').consent, false);
    assert.equal(v.buildPayload(items, true).items[0].name, undefined);
});

test('formatTotal formata moeda da Store API', () => {
    const base = { currency_minor_unit: 2, currency_prefix: 'R$', currency_decimal_separator: ',', currency_thousand_separator: '.' };

    assert.equal(v.formatTotal(Object.assign({ total_price: '10000' }, base)), 'R$ 100,00');
    assert.equal(v.formatTotal(Object.assign({ total_price: '123456789' }, base)), 'R$ 1.234.567,89');
    assert.equal(v.formatTotal(Object.assign({ total_price: '5' }, base)), 'R$ 0,05');
    assert.equal(v.formatTotal(null), '');
});

test('suspectCountry avisa só quando o estado é UF do Brasil e o país não é BR', () => {
    assert.equal(v.suspectCountry('BZ', 'SP'), true);
    assert.equal(v.suspectCountry('BR', 'SP'), false);
    assert.equal(v.suspectCountry('US', 'CA'), false);
    assert.equal(v.suspectCountry('', 'SP'), false);
});

test('stageGateCss esconde campos nativos por classe em cada etapa, sem depender de data-block-name', () => {
    const css = v.stageGateCss();
    const rule = (step) => css.split('}').filter((r) => r.indexOf('body.wcai-checkout-step-' + step + ' ') === 0)[0];

    assert.match(rule(1), /\.wc-block-checkout__contact-fields/);
    assert.match(rule(1), /\.wc-block-checkout__payment-method/);
    assert.match(rule(1), /\.wc-block-checkout__actions/);
    // etapa 2: titular visível, pagamento escondido
    assert.doesNotMatch(rule(2), /contact-fields/);
    assert.doesNotMatch(rule(2), /billing-fields/);
    assert.match(rule(2), /\.wc-block-checkout__payment-method/);
    // etapa 3: nada nativo
    assert.match(rule(3), /\.wc-block-checkout__contact-fields/);
    assert.match(rule(3), /\.wc-block-checkout__terms/);
    // etapa 4: pagamento, termos e ações visíveis; titular escondido
    assert.match(rule(4), /\.wc-block-checkout__contact-fields/);
    assert.doesNotMatch(rule(4), /payment-method|checkout__terms|checkout__actions/);
});
