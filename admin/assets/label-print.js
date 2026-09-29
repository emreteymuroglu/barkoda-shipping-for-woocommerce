/*
 * Opens the browser print dialog once the label document has finished loading.
 * The delay gives the barcode SVG and the optional logo time to paint, so the
 * printed sheet is never missing them.
 */
window.addEventListener('load', function () {
	setTimeout(function () { window.print(); }, 300);
});
