import assert from 'node:assert/strict';
import test from 'node:test';

import { renderScheduleImpact } from '../../resources/js/checksheet-impact.js';

class FakeClassList {
    values = new Set();

    remove(value) {
        this.values.delete(value);
    }
}

class FakeElement {
    constructor(tagName) {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.className = '';
        this.classList = new FakeClassList();
        this.textContent = '';
    }

    append(...nodes) {
        this.children.push(...nodes);
    }

    replaceChildren(...nodes) {
        this.children = nodes;
    }

    createTHead() {
        return this.appendSection('thead');
    }

    createTBody() {
        return this.appendSection('tbody');
    }

    insertRow() {
        const row = new FakeElement('tr');
        this.append(row);

        return row;
    }

    insertCell() {
        const cell = new FakeElement('td');
        this.append(cell);

        return cell;
    }

    appendSection(tagName) {
        const section = new FakeElement(tagName);
        this.append(section);

        return section;
    }
}

globalThis.document = {
    createElement: (tagName) => new FakeElement(tagName),
};

test('impact dates render hostile response values as text', () => {
    const container = new FakeElement('div');
    const hostileValue = '<img src=x onerror=alert(1)>';

    renderScheduleImpact(container, { created: [hostileValue] });

    const dateCell = container.children[0].children[1].children[0].children[1];

    // Nilai hostile harus tetap menjadi text node, bukan markup yang dieksekusi.
    assert.equal(dateCell.textContent, hostileValue);
    assert.equal(dateCell.children.length, 0);
});
