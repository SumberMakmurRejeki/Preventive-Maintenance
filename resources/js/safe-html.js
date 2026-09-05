const htmlEscapes = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/**
 * Escape nilai dinamis sebelum dipasang ke template HTML.
 *
 * @param {unknown} value
 * @returns {string}
 */
export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => htmlEscapes[character]);
