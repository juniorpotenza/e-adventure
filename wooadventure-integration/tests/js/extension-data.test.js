// Regressão: extensions['wcai-checkout'] deve ser objeto. Roda com: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const v = require('../../assets/js/wcai-checkout-block.js');

// Imita a action da store do WooCommerce: setExtensionData(namespace, objeto, replace).
function fakeStore() {
    const extensionData = {};
    return {
        extensionData,
        setExtensionData(ns, data, replace) {
            extensionData[ns] = replace ? data : Object.assign({}, extensionData[ns], data);
        }
    };
}

test('pushExtensionData grava objeto em extensions[wcai-checkout]', () => {
    const store = fakeStore();
    v.pushExtensionData(store, '{"items":[]}');
    const ns = store.extensionData['wcai-checkout'];
    assert.equal(typeof ns, 'object');
    assert.equal(Array.isArray(ns), false);
    assert.deepEqual(ns, { data: '{"items":[]}' });
});

test('pushExtensionData repetido mantém objeto', () => {
    const store = fakeStore();
    v.pushExtensionData(store, 'a');
    v.pushExtensionData(store, 'b');
    assert.deepEqual(store.extensionData['wcai-checkout'], { data: 'b' });
});
