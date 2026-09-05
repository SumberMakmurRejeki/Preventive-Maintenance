const impactGroups = [
    ['created', 'Dibuat', 'text-emerald-700'],
    ['removed', 'Dihapus', 'text-red-700'],
    ['retained', 'Dipertahankan', 'text-slate-700'],
    ['protected', 'Dilindungi', 'text-amber-700'],
    ['conflict', 'Konflik', 'text-red-700'],
];

const formatImpactDate = (value) => {
    const [year, month, day] = String(value).split('-');

    return year && month && day ? `${day}/${month}/${year}` : String(value);
};

/**
 * Render impact jadwal dengan DOM API agar data response tidak menjadi HTML.
 *
 * @param {HTMLElement} container
 * @param {Record<string, unknown>} impact
 */
export const renderScheduleImpact = (container, impact = {}) => {
    // Buat seluruh node sebagai teks agar nilai API hostile tidak dieksekusi browser.
    const table = document.createElement('table');
    table.className = 'w-full text-sm';

    const head = table.createTHead();
    const headRow = head.insertRow();
    const impactHead = headRow.insertCell();
    impactHead.className = 'px-4 py-2 text-left';
    impactHead.textContent = 'Dampak';
    const dateHead = headRow.insertCell();
    dateHead.className = 'px-4 py-2 text-left';
    dateHead.textContent = 'Tanggal';

    const body = table.createTBody();
    for (const [key, label, color] of impactGroups) {
        const dates = Array.isArray(impact[key]) ? impact[key] : [];
        const row = body.insertRow();
        row.className = 'border-t border-[var(--color-prime-border)]';

        const labelCell = row.insertCell();
        labelCell.className = `px-4 py-2 text-left font-semibold ${color}`;
        labelCell.textContent = label;

        const dateCell = row.insertCell();
        dateCell.className = 'px-4 py-2';
        dateCell.textContent = dates.map(formatImpactDate).join(', ') || '-';
    }

    container.replaceChildren(table);
    container.classList.remove('hidden');
};
