/**
 * Uji regresi hostile-input level DOM untuk wizard renderer di resources/js/app.js.
 *
 * FIDELITAS HARNESS — baca sebelum mengklaim "production module load":
 *
 * Harness ini adalah SOURCE-TEXT APPROXIMATION, bukan native ESM module load.
 * Node.js ESM resolver memerlukan ekstensi file eksplisit; import './bootstrap' (tanpa .js)
 * dan import './pages/calendar' gagal di luar Vite/bundler. Oleh karena itu:
 *
 *   1. Empat import statements (bootstrap, calendar, checksheet-impact, safe-html) di-strip
 *      dari raw source text app.js menggunakan regex sebelum eksekusi.
 *      Akibatnya: Node ESM module machinery TIDAK dipanggil untuk file-file tersebut.
 *
 *   2. safe-html.js dan checksheet-impact.js: source implementation dibaca dari production;
 *      export declaration dinormalisasi (token `export` dihapus dari `export const …`)
 *      agar binding tersedia di script context tanpa ESM export machinery.
 *      Implementasi fungsi (escapeHtml, renderScheduleImpact) tidak diubah.
 *
 *   3. bootstrap.js (axios setup) dan pages/calendar.js (FullCalendar) di-remove sepenuhnya.
 *      Keduanya telah diverifikasi tidak mengandung renderMachineList, renderParts,
 *      renderStandards, renderReview, atau escapeHtml (grep menghasilkan zero result).
 *      Sisi efek inisialisasi mereka (axios global, FullCalendar init) tidak berjalan.
 *
 *   4. Implementasi renderer closure di app.js (renderMachineList, renderParts,
 *      renderStandards, renderReview, blok location-options) tidak dimodifikasi —
 *      dikonfirmasi: stripping 4 import lines mengubah hanya 5 baris header; tidak ada
 *      karakter dalam closure body yang berubah (String.prototype.slice comparison = true).
 *
 * KONSEKUENSI:
 *   - Coverage claim yang valid: renderer closures di app.js (escapeHtml calls, template
 *     literals) dieksekusi dari production source — harness membuktikan bahwa hostile string
 *     di-escape sebelum masuk DOM.
 *   - Coverage claim yang TIDAK valid: ESM import resolution, axios setup, FullCalendar,
 *     atau apapun yang bergantung pada bootstrap.js / calendar.js.
 *   - "Production module load" adalah klaim yang salah; harness ini adalah
 *     "source-text renderer-path approximation."
 *
 * Jika diperlukan true ESM module load, harness harus menggunakan custom loader yang
 * me-resolve ekstensi bare import (misalnya Vite-aware test runner seperti Vitest), atau
 * menguji terhadap bundled output — tapi bundled output adalah minified dan tidak dapat
 * di-assert per named function.
 *
 * REGRESSION COVERAGE ADDED AGAINST ALREADY-REMEDIATED SOURCE; NO PRODUCTION RED EXPECTED.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

// ---------------------------------------------------------------------------
// Matriks payload hostile (set representatif OWASP)
// ---------------------------------------------------------------------------
const PAYLOAD_IMG    = '<img src=x onerror=alert(1)>';         // A — injeksi elemen
const PAYLOAD_SCRIPT = '<script>alert(1)<\/script>';            // B — markup script
const PAYLOAD_DQ     = '"><img src=x onerror=alert(1)>';        // C — breakout kutip ganda
const PAYLOAD_SQ     = "'><svg onload=alert(1)>";               // D — breakout kutip tunggal
const PAYLOAD_ENTITY = 'A&B < C > D';                          // E — teks sensitif entity

// ---------------------------------------------------------------------------
// Bangun script gabungan yang dieksekusi pada setiap jsdom context pengujian.
//
// Keterbatasan (lihat atas): ini adalah source-text approximation.
// Empat import declaration di-strip agar script berjalan di vm context.
// safe-html.js dan checksheet-impact.js: source implementation dibaca dari production;
// export declaration dinormalisasi (`export const` → `const`) untuk kompatibilitas
// script context — implementasi fungsi tidak diubah.
// bootstrap.js dan pages/calendar.js di-remove sepenuhnya — keduanya telah dikonfirmasi
// tidak mengandung logika renderer atau pemanggilan escapeHtml (grep zero result).
// ---------------------------------------------------------------------------
const safeHtmlSrc = readFileSync(
    new URL('../../resources/js/safe-html.js', import.meta.url),
    'utf8',
).replace(/^export\s+const\s+/m, 'const ');

const impactSrc = readFileSync(
    new URL('../../resources/js/checksheet-impact.js', import.meta.url),
    'utf8',
).replace(/^export\s+const\s+/gm, 'const ');

const appRaw = readFileSync(
    new URL('../../resources/js/app.js', import.meta.url),
    'utf8',
);

// Strip hanya 4 import declaration — tidak ada mutasi lain pada source app.js.
// Dikonfirmasi: implementasi renderer closure tidak berubah setelah strip ini.
const appSrc = appRaw
    .replace(/^import\s+'\.\/bootstrap';?\s*/m,                                '')
    .replace(/^import\s+'\.\/pages\/calendar';?\s*/m,                           '')
    .replace(/^import\s*\{[^}]+\}\s*from\s*'\.\/checksheet-impact';?\s*/m,     '')
    .replace(/^import\s*\{[^}]+\}\s*from\s*'\.\/safe-html';?\s*/m,             '');

/**
 * Script gabungan: safe-html (export decl dinormalisasi) → impact (export decl dinormalisasi)
 * → app.js (import declaration di-strip).
 * Implementasi renderer closure dibaca dari production source, tidak dimodifikasi.
 */
const FULL_SCRIPT = `${safeHtmlSrc}\n${impactSrc}\n${appSrc}`;

// ---------------------------------------------------------------------------
// Helper: bangun lingkungan jsdom minimal yang berisi skeleton DOM wizard
// yang dibutuhkan blok DOMContentLoaded, lalu jalankan event tersebut agar
// semua renderer closure (renderMachineList, renderParts, renderStandards,
// renderReview, blok location-options) dieksekusi terhadap seed data yang diberikan.
//
// Mengembalikan jsdom `document` untuk assertion selanjutnya.
// ---------------------------------------------------------------------------
function buildWizardDom({ seedMachines = [], seedPayload = {} } = {}) {
    // Escape kutip tunggal pada JSON agar bisa ditempatkan di atribut HTML berkutip tunggal
    const escapeSQ = (json) => json.replace(/'/g, '&#39;');

    const machinesAttr = escapeSQ(JSON.stringify(seedMachines));
    const payloadAttr  = escapeSQ(JSON.stringify(seedPayload));

    const html = `<!DOCTYPE html>
<html>
<head><meta name="csrf-token" content="test-csrf"></head>
<body>
  <section
    data-checksheet-wizard
    data-edit-id=""
    data-preview-url=""
    data-apply-url=""
    data-seed-machines='${machinesAttr}'
    data-seed-payload='${payloadAttr}'>
    <form data-checksheet-form>
      <input type="hidden" data-checksheet-payload>
      <div data-stepper-progress></div>
      <div data-step-panel="1"></div>
      <div data-step-panel="2" class="hidden">
        <input data-machine-search>
        <select data-machine-location-filter>
          <option value="">Semua Lokasi</option>
        </select>
        <div data-machine-list></div>
      </div>
      <div data-step-panel="3" class="hidden">
        <div data-parts-builder></div>
      </div>
      <div data-step-panel="4" class="hidden">
        <div data-standards-builder></div>
      </div>
      <div data-step-panel="5" class="hidden">
        <select data-schedule-frequency></select>
        <div data-weekly-wrap class="hidden">
          <div data-weekly-days></div>
        </div>
        <label data-monthly-wrap class="hidden">
          <input data-monthly-day>
        </label>
        <input data-schedule-operational>
        <div data-schedule-preview></div>
      </div>
      <div data-step-panel="6" class="hidden">
        <div data-review-box></div>
        <div data-schedule-status class="hidden"></div>
        <div data-schedule-impact class="hidden"></div>
        <button data-apply-schedule class="hidden"></button>
        <div data-apply-status class="hidden"></div>
      </div>
    </form>
    <button data-step-back></button>
    <button data-step-next></button>
    <button data-step-submit class="hidden"></button>
  </section>
</body>
</html>`;

    const dom = new JSDOM(html);
    const { window } = dom;

    // Stub browser API minimal yang dibutuhkan jalur inisialisasi app.js
    // yang tidak terkait dengan renderer wizard yang diuji.
    Object.defineProperty(window, 'localStorage', {
        value: { getItem: () => null, setItem: () => {} },
        configurable: true,
    });
    window.alert   = () => {};   // redam alert validasi
    window.confirm = () => false;
    window.fetch   = () => Promise.resolve({
        ok: true,
        headers: { get: () => 'application/json' },
        json: () => Promise.resolve({}),
    });
    window.atob = (s) => Buffer.from(s, 'base64').toString('binary');

    // Eksekusi source production di dalam jsdom Window context
    vm.runInContext(FULL_SCRIPT, vm.createContext(window));

    // Jalankan DOMContentLoaded — memicu seluruh inisialisasi renderer
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

    return window.document;
}

// ---------------------------------------------------------------------------
// Assertion keamanan bersama: tidak ada elemen/handler eksekutabel yang dibuat penyerang
// ---------------------------------------------------------------------------
function assertNoExecutableNodes(container, label) {
    assert.equal(
        container.querySelectorAll('img').length, 0,
        `${label}: payload hostile tidak boleh membuat elemen IMG`,
    );
    assert.equal(
        container.querySelectorAll('script').length, 0,
        `${label}: payload hostile tidak boleh membuat elemen SCRIPT`,
    );
    assert.equal(
        container.querySelectorAll('svg').length, 0,
        `${label}: payload hostile tidak boleh membuat elemen SVG`,
    );

    // Tidak ada atribut event-handler yang disuntikkan pada descendant manapun
    for (const el of container.querySelectorAll('*')) {
        assert.equal(
            el.getAttribute('onerror'), null,
            `${label}: atribut onerror tidak boleh ada`,
        );
        assert.equal(
            el.getAttribute('onload'), null,
            `${label}: atribut onload tidak boleh ada`,
        );
    }
}

// ===========================================================================
// UJI 1 — renderMachineList
// Payload hostile: machine code (A), machine name (B), location (C, D, E)
// Verifikasi: tidak ada DOM eksekutabel, semantik checkbox/value tetap utuh
// ===========================================================================
test('renderMachineList — payload hostile pada code/name/location tidak membuat DOM eksekutabel', () => {
    const machines = [
        // Mesin hostile utama: code=A, name=B, location=C
        { id: 1, code: PAYLOAD_IMG,    name: PAYLOAD_SCRIPT, location: PAYLOAD_DQ },
        // Mesin kedua: code=D, name=E, location=aman (mencakup payload tersisa)
        { id: 2, code: PAYLOAD_SQ,     name: PAYLOAD_ENTITY, location: 'Safe Location' },
    ];

    const doc = buildWizardDom({ seedMachines: machines });
    const machineList = doc.querySelector('[data-machine-list]');

    assert.ok(machineList, 'container machineList harus ada');

    // Tidak ada node eksekutabel dari code/name/location hostile
    assertNoExecutableNodes(machineList, 'renderMachineList');

    // Struktur checkbox aplikasi tetap utuh (dua mesin → dua checkbox)
    const checkboxes = machineList.querySelectorAll('input[type="checkbox"][data-machine-checkbox]');
    assert.equal(checkboxes.length, 2, 'dua checkbox mesin harus dirender');

    // Nilai checkbox menyimpan ID mesin dengan aman (numerik — bukan konteks string hostile)
    assert.equal(checkboxes[0].getAttribute('value'), '1');
    assert.equal(checkboxes[1].getAttribute('value'), '2');

    // Teks hostile hadir sebagai text content inert di dalam container
    // (membuktikan konten tidak di-drop diam-diam)
    const text = machineList.textContent;
    assert.ok(
        text.includes('alert(1)') || text.includes('&B'),
        'konten hostile harus muncul sebagai teks inert, tidak di-drop diam-diam',
    );
});

// ===========================================================================
// UJI 2 — location options
// Payload hostile: nilai location (C = breakout kutip ganda), label (D, E)
// Verifikasi: struktur OPTION tetap utuh, tidak ada node breakout, semantik value terjaga
// ===========================================================================
test('location options — label/value location hostile tidak merusak struktur OPTION', () => {
    const machines = [
        { id: 1, code: 'M1', name: 'Machine 1', location: PAYLOAD_DQ    },  // C
        { id: 2, code: 'M2', name: 'Machine 2', location: PAYLOAD_SQ    },  // D
        { id: 3, code: 'M3', name: 'Machine 3', location: PAYLOAD_ENTITY }, // E
    ];

    const doc = buildWizardDom({ seedMachines: machines });
    const select = doc.querySelector('[data-machine-location-filter]');

    assert.ok(select, 'select filter lokasi harus ada');

    // Tidak ada node eksekutabel di dalam atau di samping select
    assertNoExecutableNodes(select.parentElement, 'location options');

    // Jumlah OPTION yang benar: "Semua Lokasi" + 3 lokasi unik
    const options = select.querySelectorAll('option');
    assert.equal(options.length, 4, '4 option: kosong + 3 lokasi hostile');

    // SELECT itu sendiri harus tetap satu elemen SELECT — breakout kutip
    // tidak boleh menyuntikkan elemen saudara di luar SELECT
    const siblings = Array.from(select.parentElement.children).filter(
        (el) => el !== select,
    );
    const injectedExecutable = siblings.some(
        (el) => ['IMG', 'SCRIPT', 'SVG'].includes(el.tagName),
    );
    assert.equal(injectedExecutable, false, 'tidak boleh ada saudara eksekutabel yang disuntikkan di samping SELECT');
});

// ===========================================================================
// UJI 3 — renderParts
// Payload hostile: nama part (A), deskripsi part (B), code/name mesin (C/D)
// Verifikasi: tidak ada DOM eksekutabel, input[data-part-name] ada, integritas atribut terjaga
// ===========================================================================
test('renderParts — nama/deskripsi part hostile tidak membuat DOM eksekutabel', () => {
    const machines = [
        { id: 1, code: PAYLOAD_DQ, name: PAYLOAD_SQ, location: 'Loc' },
    ];
    const seedPayload = {
        selected_machine_ids: [1],
        parts: {
            1: [
                { id: 'p1', name: PAYLOAD_IMG,    description: PAYLOAD_SCRIPT },
                { id: 'p2', name: PAYLOAD_ENTITY, description: PAYLOAD_DQ     },
            ],
        },
        standards: {},
        schedule: { frequency_type: '', weekly_days: [], monthly_day: null, operational_from: '' },
    };

    const doc = buildWizardDom({ seedMachines: machines, seedPayload });
    const partsBuilder = doc.querySelector('[data-parts-builder]');

    assert.ok(partsBuilder, 'container partsBuilder harus ada');

    // Tidak ada node eksekutabel
    assertNoExecutableNodes(partsBuilder, 'renderParts');

    // Input field part aplikasi harus dirender (satu per part)
    const partNameInputs = partsBuilder.querySelectorAll('input[data-part-name]');
    assert.equal(partNameInputs.length, 2, 'dua input part-name harus dirender');

    // Atribut value input pertama berisi string hostile yang di-escape,
    // bukan injeksi eksekutabel (jsdom menegakkan keamanan atribut)
    const firstInputValue = partNameInputs[0].value;
    assert.ok(
        firstInputValue.includes('img') || firstInputValue.includes('alert'),
        'nama part hostile harus direpresentasikan di value input sebagai teks inert',
    );

    // Atribut data-add-part tidak boleh merusak tombol — nilainya adalah ID mesin (numerik)
    const addPartBtn = partsBuilder.querySelector('[data-add-part]');
    assert.ok(addPartBtn, 'tombol Tambah Part harus ada');
    assert.equal(addPartBtn.getAttribute('data-add-part'), '1');
});

// ===========================================================================
// UJI 4 — renderStandards
// Payload hostile: nama standard (B), unit (C breakout kutip), action option (A),
// part.id sebagai nilai data-attribute (numerik aman), code mesin (D/E)
// Verifikasi: tidak ada DOM eksekutabel, integritas kutip atribut, input standard dirender
// ===========================================================================
test('renderStandards — nama/unit/option standard hostile tidak membuat DOM eksekutabel', () => {
    const machines = [
        { id: 1, code: PAYLOAD_ENTITY, name: 'Machine 1', location: 'Loc' },
    ];
    const seedPayload = {
        selected_machine_ids: [1],
        parts: {
            1: [{ id: 'part-hostile', name: 'Part 1', description: '' }],
        },
        standards: {
            'part-hostile': [
                {
                    name:           PAYLOAD_SCRIPT,  // B
                    input_type:     'action',
                    action_options: [PAYLOAD_IMG],    // A — teks option
                    unit:           PAYLOAD_DQ,       // C — konteks atribut
                    is_required:    true,
                    is_active:      true,
                },
                {
                    name:           PAYLOAD_SQ,       // D
                    input_type:     'text',
                    action_options: [],
                    unit:           PAYLOAD_ENTITY,   // E
                    is_required:    true,
                    is_active:      true,
                },
            ],
        },
        schedule: { frequency_type: '', weekly_days: [], monthly_day: null, operational_from: '' },
    };

    const doc = buildWizardDom({ seedMachines: machines, seedPayload });
    const standardsBuilder = doc.querySelector('[data-standards-builder]');

    assert.ok(standardsBuilder, 'container standardsBuilder harus ada');

    // Tidak ada node eksekutabel
    assertNoExecutableNodes(standardsBuilder, 'renderStandards');

    // Input nama standard harus dirender
    const stdNameInputs = standardsBuilder.querySelectorAll('input[data-standard-name]');
    assert.equal(stdNameInputs.length, 2, 'dua input standard-name harus dirender');

    // Nilai atribut data-add-standard (part.id) harus ada dan strukturnya utuh
    const addStdBtn = standardsBuilder.querySelector('[data-add-standard]');
    assert.ok(addStdBtn, 'tombol Tambah Standard harus ada');
    assert.equal(
        addStdBtn.getAttribute('data-add-standard'),
        'part-hostile',
        'atribut data-add-standard harus menyimpan part ID tanpa kerusakan',
    );

    // Breakout kutip pada field unit tidak boleh membuat node yang disuntikkan di samping input unit
    // Input unit hanya ada untuk input_type 'number'; untuk 'action'/'text' field unit tetap dirender —
    // verifikasi tidak ada konten eksekutabel apapun
    const allInputs = standardsBuilder.querySelectorAll('input');
    assert.ok(allInputs.length > 0, 'input standard harus dirender');
    // Konfirmasi tidak ada elemen eksekutabel yang disuntikkan di seluruh subtree
    assert.equal(standardsBuilder.querySelectorAll('img').length, 0);
    assert.equal(standardsBuilder.querySelectorAll('script').length, 0);
});

// ===========================================================================
// UJI 5 — renderReview
// Payload hostile: code mesin sebagai machineNames (A, B), field schedule (C)
// Review dirender saat langkah maju ke 6.
// Verifikasi: tidak ada DOM eksekutabel, struktur ringkasan review ada, nilai inert
// ===========================================================================
test('renderReview — code mesin dan nilai schedule hostile tidak membuat DOM eksekutabel', () => {
    const machines = [
        { id: 1, code: PAYLOAD_IMG,    name: 'Machine 1', location: 'Loc' }, // A di machineNames
        { id: 2, code: PAYLOAD_SCRIPT, name: 'Machine 2', location: 'Loc' }, // B
    ];
    const seedPayload = {
        selected_machine_ids: [1, 2],
        parts: {
            1: [{ id: 'p1', name: 'Part 1', description: '' }],
            2: [{ id: 'p2', name: 'Part 2', description: '' }],
        },
        standards: {
            p1: [{ name: 'Std 1', input_type: 'text', action_options: [], is_required: true, is_active: true }],
            p2: [{ name: 'Std 2', input_type: 'text', action_options: [], is_required: true, is_active: true }],
        },
        schedule: {
            // frequency_type adalah teks bebas yang dirender melalui escapeHtml — payload hostile valid di sini
            frequency_type:   PAYLOAD_DQ,     // C — breakout kutip ganda di text sink
            weekly_days:      [],
            monthly_day:      null,
            // operational_from dimasukkan ke new Date() — harus string ISO date yang valid;
            // trust boundary field ini adalah format tanggal, bukan teks bebas
            operational_from: '2026-01-01',
        },
    };

    const doc = buildWizardDom({ seedMachines: machines, seedPayload });

    // Maju ke langkah 6 (5 klik tombol next dari langkah 1)
    const nextBtn = doc.querySelector('[data-step-next]');
    assert.ok(nextBtn, 'tombol step-next harus ada');
    for (let i = 0; i < 5; i++) {
        nextBtn.click();
    }

    const reviewBox = doc.querySelector('[data-review-box]');
    assert.ok(reviewBox, 'reviewBox harus ada');

    // Tidak ada node eksekutabel
    assertNoExecutableNodes(reviewBox, 'renderReview');

    // reviewBox harus berisi konten aktual (tidak kosong/hanya spasi)
    assert.ok(reviewBox.innerHTML.trim().length > 0, 'reviewBox harus berisi konten review');

    // Teks code mesin hostile harus ada sebagai konten inert (tidak di-drop diam-diam)
    const text = reviewBox.textContent;
    assert.ok(
        text.includes('alert(1)') || text.includes('img') || text.includes('script'),
        'code mesin hostile harus muncul sebagai teks inert di review',
    );

    // Struktur reviewBox harus memiliki grid/paragraf ringkasan yang diharapkan
    // (membuktikan layout aplikasi tidak rusak akibat input hostile)
    const paragraphs = reviewBox.querySelectorAll('p');
    assert.ok(paragraphs.length >= 4, 'reviewBox harus berisi paragraf ringkasan');
});

// ===========================================================================
// UJI 6 — runServerPreview status=zero → tombol apply tampil + aktif (bug-fix TASK-002)
//
// Regresi: sebelum perbaikan, cabang zero tidak memanggil classList.remove('hidden')
// maupun removeAttribute('disabled'), sehingga tombol apply tersembunyi meskipun
// service mengembalikan token yang valid (kasus checksheet tanpa jadwal aktif).
// Pengujian ini memastikan tombol visible + enabled setelah preview zero + token.
// ===========================================================================
test('runServerPreview status=zero + token → tombol apply tampil dan aktif', async () => {
    const machines = [
        { id: 2, code: 'M-CS-P2', name: 'Mesin P2', location: 'Lokasi A' },
    ];
    const seedPayload = {
        selected_machine_ids: [2],
        parts: {
            2: [{ id: 'p2-1', name: 'Pompa P2', description: '' }],
        },
        standards: {
            'p2-1': [{ name: 'Cek Tekanan', input_type: 'number', action_options: [], is_required: true, is_active: true }],
        },
        schedule: {
            frequency_type: 'monthly',
            weekly_days: [],
            monthly_day: 15,
            operational_from: '2026-09-01',
        },
    };

    const escapeSQ = (json) => json.replace(/'/g, '&#39;');
    const machinesAttr  = escapeSQ(JSON.stringify(machines));
    const payloadAttr   = escapeSQ(JSON.stringify(seedPayload));

    // Bangun DOM wizard dengan data-edit-id dan data-preview-url yang valid agar
    // runServerPreview tidak short-circuit pada guard `if (! editId || ! previewUrl)`.
    const html = `<!DOCTYPE html>
<html>
<head><meta name="csrf-token" content="test-csrf"></head>
<body>
  <section
    data-checksheet-wizard
    data-edit-id="3"
    data-preview-url="/pm/master-checksheet/3/preview-schedule-update"
    data-apply-url="/pm/master-checksheet/3/apply-schedule-update"
    data-seed-machines='${machinesAttr}'
    data-seed-payload='${payloadAttr}'>
    <form data-checksheet-form>
      <input type="hidden" data-checksheet-payload>
      <div data-stepper-progress></div>
      <div data-step-panel="1"></div>
      <div data-step-panel="2" class="hidden">
        <input data-machine-search>
        <select data-machine-location-filter><option value="">Semua</option></select>
        <div data-machine-list></div>
      </div>
      <div data-step-panel="3" class="hidden"><div data-parts-builder></div></div>
      <div data-step-panel="4" class="hidden"><div data-standards-builder></div></div>
      <div data-step-panel="5" class="hidden">
        <select data-schedule-frequency></select>
        <div data-weekly-wrap class="hidden"><div data-weekly-days></div></div>
        <label data-monthly-wrap class="hidden"><input data-monthly-day></label>
        <input data-schedule-operational>
        <div data-schedule-preview></div>
      </div>
      <div data-step-panel="6" class="hidden">
        <div data-review-box></div>
        <div data-schedule-status class="hidden"></div>
        <div data-schedule-impact class="hidden"></div>
        <button data-apply-schedule class="hidden" disabled="disabled"></button>
        <div data-apply-status class="hidden"></div>
      </div>
    </form>
    <button data-step-back></button>
    <button data-step-next></button>
    <button data-step-submit class="hidden"></button>
  </section>
</body>
</html>`;

    const { JSDOM } = await import('jsdom');
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');

    const safeHtmlSrc = readFileSync(new URL('../../resources/js/safe-html.js', import.meta.url), 'utf8')
        .replace(/^export\s+const\s+/m, 'const ');
    const impactSrc = readFileSync(new URL('../../resources/js/checksheet-impact.js', import.meta.url), 'utf8')
        .replace(/^export\s+const\s+/gm, 'const ');
    const appRaw = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const appSrc = appRaw
        .replace(/^import\s+'\.\/bootstrap';?\s*/m, '')
        .replace(/^import\s+'\.\/pages\/calendar';?\s*/m, '')
        .replace(/^import\s*\{[^}]+\}\s*from\s*'\.\/checksheet-impact';?\s*/m, '')
        .replace(/^import\s*\{[^}]+\}\s*from\s*'\.\/safe-html';?\s*/m, '');

    const dom  = new JSDOM(html);
    const { window } = dom;

    // Stub browser API
    Object.defineProperty(window, 'localStorage', {
        value: { getItem: () => null, setItem: () => {} },
        configurable: true,
    });
    window.alert   = () => {};
    window.confirm = () => false;
    window.atob    = (s) => Buffer.from(s, 'base64').toString('binary');

    // Stub fetch — kembalikan status=zero + token yang valid (kasus inisialisasi jadwal baru)
    let previewCallCount = 0;
    window.fetch = () => {
        previewCallCount++;
        return Promise.resolve({
            ok: true,
            status: 200,
            headers: { get: () => 'application/json' },
            json: () => Promise.resolve({
                status: 'zero',
                token: 'fingerprint-abc123',
                impact: { created: [], removed: [], retained: [], protected: [], conflict: [] },
                counts: { created: 0, removed: 0, retained: 0, protected: 0, conflict: 0 },
            }),
        });
    };

    vm.runInContext(`${safeHtmlSrc}\n${impactSrc}\n${appSrc}`, vm.createContext(window));
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

    // Maju ke langkah 6 — memicu runServerPreview() (async)
    const nextBtn = window.document.querySelector('[data-step-next]');
    assert.ok(nextBtn, 'tombol step-next harus ada');
    for (let i = 0; i < 5; i++) {
        nextBtn.click();
    }

    // Tunggu seluruh microtask/promise chain dari fetch mock terselesaikan
    await new Promise((resolve) => setTimeout(resolve, 0));

    // Verifikasi: fetch preview dipanggil tepat satu kali
    assert.equal(previewCallCount, 1, 'fetch preview harus dipanggil saat step 6 dibuka');

    const applyBtn = window.document.querySelector('[data-apply-schedule]');
    assert.ok(applyBtn, 'tombol data-apply-schedule harus ada di DOM');

    // BUG-FIX ASSERTION: tombol apply harus TIDAK tersembunyi dan TIDAK disabled
    // setelah status=zero + token. Sebelum perbaikan keduanya masih true.
    assert.equal(
        applyBtn.classList.contains('hidden'), false,
        'tombol apply harus tidak tersembunyi setelah status=zero + token valid',
    );
    assert.equal(
        applyBtn.hasAttribute('disabled'), false,
        'tombol apply harus tidak disabled setelah status=zero + token valid',
    );

    // Pesan status harus menginformasikan pengguna bahwa tombol tetap aktif
    const scheduleStatus = window.document.querySelector('[data-schedule-status]');
    assert.ok(
        scheduleStatus?.textContent?.includes('inisialisasi'),
        'pesan status harus menyebut inisialisasi jadwal baru saat status=zero',
    );
});
