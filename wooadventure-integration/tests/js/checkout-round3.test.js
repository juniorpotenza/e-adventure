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

const T0 = 1700000000000;
const MIN = 60 * 1000;

function fakeStore(entries) {
    const map = new Map(Object.entries(entries || {}));
    return {
        get length() { return map.size; },
        key: (i) => Array.from(map.keys())[i] || null,
        getItem: (k) => (map.has(k) ? map.get(k) : null),
        removeItem: (k) => { map.delete(k); },
        keys: () => Array.from(map.keys())
    };
}

test('rascunho: guarda só nome, categoria e etapa (nunca CPF nem nascimento)', () => {
    const raw = v.buildDraft([item([person(OTHER, { name: 'Ana', type: 'child' })])], 3, T0);

    assert.deepEqual(JSON.parse(raw), { v: 2, ts: T0, step: 3, items: { k1: [{ type: 'child', name: 'Ana' }] } });
    assert.ok(!raw.includes('111') && !raw.includes('1990') && !/cpf|birth/i.test(raw));
});

test('rascunho: applyDraft restaura nome, categoria e etapa; CPF e nascimento ficam vazios e liga o aviso', () => {
    const raw = v.buildDraft([item([person(OTHER, { name: 'Ana', type: 'child' })])], 3, T0);
    const fresh = item([{ type: 'adult', name: '', cpf: '', birthdate: '' }]);

    assert.deepEqual(v.applyDraft([fresh], raw, T0 + MIN), { step: 3, notice: true });
    assert.deepEqual(fresh.additional_participants[0], { type: 'child', name: 'Ana', cpf: '', birthdate: '' });
});

test('rascunho: sem aviso quando CPF e nascimento já estão preenchidos ou não há participantes', () => {
    const raw = v.buildDraft([item([person(OTHER)])], 2, T0);
    const filled = item([person(OTHER)]);

    assert.equal(v.applyDraft([filled], raw, T0).notice, false);
    assert.equal(v.applyDraft([item([])], v.buildDraft([item([])], 2, T0), T0).notice, false);
});

test('rascunho: applyDraft ignora CPF e nascimento embutidos em rascunho adulterado', () => {
    const fresh = item([{ type: 'adult', name: '', cpf: '', birthdate: '' }]);
    const raw = JSON.stringify({ v: 2, ts: T0, step: 2, items: { k1: [{ type: 'adult', name: 'Bia', cpf: OTHER, birthdate: '01/01/1990' }] } });

    v.applyDraft([fresh], raw, T0);
    assert.equal(fresh.additional_participants[0].cpf, '');
    assert.equal(fresh.additional_participants[0].birthdate, '');
});

test('rascunho: applyDraft sanitiza categoria e nome, aceita etapa inválida como null e recusa lixo', () => {
    const fresh = item([{ type: 'adult' }]);
    const dirty = JSON.stringify({ v: 2, ts: T0, step: 9, items: { k1: [{ type: 'hacker', name: 'x'.repeat(500) }] } });

    assert.equal(v.applyDraft([fresh], dirty, T0).step, null);
    assert.equal(fresh.additional_participants[0].type, 'adult');
    assert.equal(fresh.additional_participants[0].name.length, 120);
    assert.equal(v.applyDraft([fresh], '{nao-json', T0), null);
    assert.equal(v.applyDraft([fresh], null, T0), null);
});

test('rascunho: expira após 30 minutos e recusa versão antiga ou data futura', () => {
    const raw = v.buildDraft([item([person(OTHER)])], 2, T0);

    assert.notEqual(v.applyDraft([item([{}])], raw, T0 + 30 * MIN), null);
    assert.equal(v.applyDraft([item([{}])], raw, T0 + 30 * MIN + 1), null);
    assert.equal(v.applyDraft([item([{}])], raw, T0 - 1), null);
    assert.equal(v.applyDraft([item([{}])], JSON.stringify({ step: 2, items: { k1: [{ name: 'Ana' }] } }), T0), null);
});

test('rascunho: sweepDrafts apaga chave v1 (com CPF) e v2 vencida; mantém v2 válida e chaves alheias', () => {
    const fresh = v.buildDraft([item([])], 1, T0);
    const store = fakeStore({
        'wcai-draft:abc': JSON.stringify({ step: 2, items: { k1: [{ cpf: OTHER, birthdate: '01/01/1990' }] } }),
        'wcai_draft_v2:old': v.buildDraft([item([])], 1, T0 - 31 * MIN),
        'wcai_draft_v2:broken': '{x',
        'wcai_draft_v2:ok': fresh,
        'outra-chave': '1'
    });

    v.sweepDrafts(store, T0);
    assert.deepEqual(store.keys().sort(), ['outra-chave', 'wcai_draft_v2:ok']);
});

test('rascunho: draftKey muda com o carrinho e usa o prefixo v2', () => {
    const a = item([person(OTHER)]);
    const b = Object.assign({}, a, { quantity: 3 });
    assert.notEqual(v.draftKey([a]), v.draftKey([b]));
    assert.equal(v.draftKey([a]), v.draftKey([a]));
    assert.match(v.draftKey([a]), /^wcai_draft_v2:/);
});
