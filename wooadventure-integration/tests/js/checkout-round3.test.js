// Rodada 3 do checkout: CPF duplicado, endereço, rascunho, textos de erro. Roda com: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const v = require('../../assets/js/wcai-checkout-block.js');

const NOW = new Date(2026, 5, 15);
const HOLDER = '529.982.247-25';
const OTHER = '111.444.777-35';

function item(people) {
    return { key: 'k1', quantity: people.length + 1, departure_id: '7', adults: people.length + 1, children: 0, additional_participants: people };
}

function person(cpf, extra) {
    return Object.assign({ type: 'adult', name: 'Fulano', cpf: cpf, birthdate: '01/01/1990' }, extra || {});
}

test('duplicateCpfIds marca participante com o CPF do titular (só dígitos comparados)', () => {
    assert.deepEqual(v.duplicateCpfIds([item([person('52998224725')])], HOLDER), { 'wcai-cpf-0-0': true });
});

test('duplicateCpfIds marca só o segundo de dois participantes iguais', () => {
    const ids = v.duplicateCpfIds([item([person(OTHER), person('111.444.777-35')])], HOLDER);
    assert.deepEqual(ids, { 'wcai-cpf-0-1': true });
});

test('duplicateCpfIds ignora CPF inválido, vazio e CPFs distintos', () => {
    assert.deepEqual(v.duplicateCpfIds([item([person(''), person('123'), person(OTHER)])], HOLDER), {});
});

test('duplicateCpfIds sem CPF do titular só compara os participantes entre si', () => {
    assert.deepEqual(v.duplicateCpfIds([item([person(HOLDER), person(OTHER)])], ''), {});
});

test('collectAdditionalErrors devolve a mensagem de CPF repetido no campo duplicado', () => {
    const errors = v.collectAdditionalErrors([item([person(HOLDER)])], NOW, HOLDER);
    assert.equal(errors['wcai-cpf-0-0'].message, 'Este CPF já foi usado por outro participante.');
    assert.deepEqual(v.collectAdditionalErrors([item([person(OTHER)])], NOW, HOLDER), {});
});

test('birthMessage: data futura tem texto próprio; menor de 7 mantém a idade mínima', () => {
    assert.equal(v.birthMessage('01/01/2999', 'do titular', NOW), 'A data de nascimento não pode ser futura.');
    assert.equal(v.birthMessage('16/06/2026', 'do titular', NOW), 'A data de nascimento não pode ser futura.');
    assert.match(v.birthMessage('16/06/2024', 'do participante 2', NOW), /idade mínima de 7/);
});

test('cleanAddressFormat remove {number} e {neighborhood} sem deixar chaves nem vírgulas soltas', () => {
    const format = '{name}\n{address_1}, {number}\n{address_2}\n{neighborhood}\n{city}\n{state}\n{postcode}\n{country}';
    const clean = v.cleanAddressFormat(format);
    assert.equal(clean, '{name}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}');
    assert.ok(!/\{(number|neighborhood)\}/.test(clean));
});

test('cleanAddressFormat não mexe em formato que só tem tokens do Woo', () => {
    const format = '{name}\n{company}\n{address_1}\n{address_2}\n{city}, {state_code} {postcode}\n{country}';
    assert.equal(v.cleanAddressFormat(format), format);
    assert.equal(v.cleanAddressFormat(''), '');
});

test('holderHint usa a contagem real da reserva', () => {
    assert.equal(v.holderHint(2, 0), 'Você é o primeiro participante: 2 adultos = você + 1 acompanhante.');
    assert.equal(v.holderHint(2, 1), 'Você é o primeiro participante: 2 adultos + 1 criança = você + 2 acompanhantes.');
    assert.equal(v.holderHint(1, 0), 'Você é o único participante desta reserva.');
});

test('rascunho: buildDraft e applyDraft restauram participantes e etapa', () => {
    const saved = item([person(OTHER, { name: 'Ana', type: 'child' })]);
    const raw = v.buildDraft([saved], 3);
    const fresh = item([{ type: 'adult' }]);

    assert.equal(v.applyDraft([fresh], raw), 3);
    assert.deepEqual(fresh.additional_participants[0], { type: 'child', name: 'Ana', cpf: OTHER, birthdate: '01/01/1990' });
});

test('rascunho: applyDraft sanitiza lixo, recusa JSON inválido e etapa fora de 1-4', () => {
    const fresh = item([{ type: 'adult' }]);
    const dirty = JSON.stringify({ step: 9, items: { k1: [{ type: 'hacker', name: 'x'.repeat(500), cpf: '52998224725999', birthdate: '99/99/9999' }] } });

    assert.equal(v.applyDraft([fresh], dirty), null);
    assert.equal(fresh.additional_participants[0].type, 'adult');
    assert.equal(fresh.additional_participants[0].name.length, 120);
    assert.equal(fresh.additional_participants[0].cpf, '529.982.247-25');
    assert.equal(fresh.additional_participants[0].birthdate, '');
    assert.equal(v.applyDraft([fresh], '{nao-json'), null);
    assert.equal(v.applyDraft([fresh], null), null);
});

test('rascunho: draftKey muda com o carrinho', () => {
    const a = item([person(OTHER)]);
    const b = Object.assign({}, a, { quantity: 3 });
    assert.notEqual(v.draftKey([a]), v.draftKey([b]));
    assert.equal(v.draftKey([a]), v.draftKey([a]));
    assert.match(v.draftKey([a]), /^wcai-draft:/);
});
