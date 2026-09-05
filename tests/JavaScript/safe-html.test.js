import assert from 'node:assert/strict';
import test from 'node:test';

import { escapeHtml } from '../../resources/js/safe-html.js';

test('wizard master values are escaped before HTML interpolation', () => {
    const hostileValue = '"/><script>alert(1)</script>&';

    // Semua field dinamis memakai helper yang sama sebelum masuk template HTML.
    assert.equal(escapeHtml(hostileValue), '&quot;/&gt;&lt;script&gt;alert(1)&lt;/script&gt;&amp;');
});
