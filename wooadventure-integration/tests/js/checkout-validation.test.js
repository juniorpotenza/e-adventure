// T5 - validação cliente do checkout. Roda com: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const v = require('../../assets/js/wcai-checkout-block.js');

const NOW = new Date(2026, 5, 15); // 15/06/2026

test('isValidCpf aceita CPF com dígitos verificadores corretos', () => {
    assert.equal(v.isValidCpf('529.982.247-25'), true);
    assert.equal(v.isValidCpf('52998224725'), true);
});

test('isValidCpf recusa dígito errado, repetido e tamanho errado', () => {
    assert.equal(v.isValidCpf('529.982.247-24'), false);
    assert.equal(v.isValidCpf('111.111.111-11'), false);
    assert.equal(v.isValidCpf('123'), false);
    assert.equal(v.isValidCpf(''), false);
});

test('parseDate recusa data inexistente e formato errado', () => {
    assert.ok(v.parseDate('29/02/2024'));
    assert.equal(v.parseDate('31/02/2024'), null);
    assert.equal(v.parseDate('29/02/2025'), null);
    assert.equal(v.parseDate('2024-02-10'), null);
    assert.equal(v.parseDate('1/1/2000'), null);
});

test('isMinAge respeita a fronteira de 7 anos', () => {
    assert.equal(v.isMinAge('15/06/2019', 7, NOW), true); // faz 7 hoje
    assert.equal(v.isMinAge('16/06/2019', 7, NOW), false); // faz 7 amanhã
    assert.equal(v.isMinAge('15/06/2027', 7, NOW), false); // futuro
    assert.equal(v.isMinAge('31/02/2000', 7, NOW), false);
});

test('birthMessage separa vazio, data inválida e menor de idade', () => {
    assert.match(v.birthMessage('', 'do titular', NOW), /Informe/);
    assert.match(v.birthMessage('31/02/2000', 'do titular', NOW), /inválida/);
    assert.match(v.birthMessage('16/06/2019', 'do titular', NOW), /idade mínima de 7/);
    assert.equal(v.birthMessage('01/01/1990', 'do titular', NOW), '');
});

test('cpfMessage separa vazio de inválido', () => {
    assert.match(v.cpfMessage('', 'do titular'), /Informe/);
    assert.match(v.cpfMessage('111.111.111-11', 'do titular'), /inválido/);
    assert.equal(v.cpfMessage('529.982.247-25', 'do titular'), '');
});

test('collectAdditionalErrors devolve um erro por campo inválido, com id do campo', () => {
    const items = [{
        adults: 2,
        children: 0,
        additional_participants: [{ type: 'adult', name: '', cpf: '111.111.111-11', birthdate: '16/06/2019' }]
    }];
    const errors = v.collectAdditionalErrors(items, NOW);

    assert.deepEqual(Object.keys(errors).sort(), ['wcai-birth-0-0', 'wcai-cpf-0-0', 'wcai-name-0-0']);
    assert.equal(errors['wcai-cpf-0-0'].hidden, true);
});

test('collectAdditionalErrors não acusa participante válido', () => {
    const items = [{
        adults: 2,
        children: 0,
        additional_participants: [{ type: 'adult', name: 'Ana', cpf: '529.982.247-25', birthdate: '01/01/1990' }]
    }];

    assert.deepEqual(v.collectAdditionalErrors(items, NOW), {});
});
