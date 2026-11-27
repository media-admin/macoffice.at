/**
 * Reparaturkostenrechner ([shortcode_service_pricelist]) ohne Seitenneuladen.
 *
 * Die Auswahl-Links funktionieren auch ohne JavaScript (normale Seitenaufrufe mit ?cat=…&device=…). Mit JavaScript wird
 * stattdessen der Inhalt per AJAX (admin-ajax.php, Aktion macoffice_sp) ersetzt, die Adresse per History-API
 * aktualisiert (Zurück-Button und Teilen funktionieren) und die Scrollposition bleibt erhalten.
 */

const PARAMS = ['cat', 'cat2', 'cat3', 'device', 'repair'];

export default class ServicePrices {
  constructor() {
    this.root = document.querySelector('.service-prices[data-endpoint]');
    if (!this.root) return;

    this.body = this.root.querySelector('.service-prices__body');
    this.endpoint = this.root.dataset.endpoint;
    this.base = this.root.dataset.base || window.location.href.split('?')[0];
    this.controller = null;
    this.cache = new Map();

    this.root.addEventListener('click', (e) => this.onClick(e));
    window.addEventListener('popstate', () => this.load(window.location.href, { push: false }));
  }

  onClick(e) {
    const link = e.target.closest('a.service-prices__option');
    if (!link || !this.root.contains(link)) return;
    // Neuer Tab/Fenster etc. nicht abfangen
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    e.preventDefault();
    this.load(link.href, { push: true });
  }

  queryFrom(href) {
    const url = new URL(href, window.location.href);
    const query = new URLSearchParams();
    PARAMS.forEach((key) => {
      const value = url.searchParams.get(key);
      if (value && /^\d+$/.test(value)) query.set(key, value);
    });
    return query;
  }

  async load(href, { push = true } = {}) {
    const query = this.queryFrom(href);
    const queryString = query.toString();

    if (this.controller) this.controller.abort();
    this.controller = new AbortController();

    this.root.classList.add('is-loading');
    this.root.setAttribute('aria-busy', 'true');

    try {
      let html = this.cache.get(queryString);
      if (html === undefined) {
        const request = new URLSearchParams({ action: 'macoffice_sp', base: this.base });
        query.forEach((value, key) => request.set(key, value));

        const response = await fetch(`${this.endpoint}?${request.toString()}`, {
          signal: this.controller.signal,
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const json = await response.json();
        if (!json || !json.success || typeof json.data?.html !== 'string') throw new Error('Unerwartete Antwort');
        html = json.data.html;
        this.cache.set(queryString, html);
      }

      const focusedHref = document.activeElement?.closest?.('a.service-prices__option')?.getAttribute('href');
      this.body.innerHTML = html;

      if (push) {
        const target = window.location.pathname + (queryString ? `?${queryString}` : '');
        if (target !== window.location.pathname + window.location.search) {
          window.history.pushState({ servicePrices: true }, '', target);
        }
      }

      this.restoreFocus(focusedHref);
      this.revealNewContent();
    } catch (err) {
      if (err.name === 'AbortError') return;
      // Notlösung: normale Navigation, der Rechner funktioniert auch so
      console.error('[ServicePrices]', err);
      window.location.href = href;
      return;
    } finally {
      this.root.classList.remove('is-loading');
      this.root.removeAttribute('aria-busy');
    }
  }

  /** Der angeklickte Link wurde neu erzeugt: Fokus auf die gewählte Option zurücksetzen, ohne zu scrollen. */
  restoreFocus(previousHref) {
    if (!previousHref) return;
    const same = Array.from(this.body.querySelectorAll('a.service-prices__option.is-selected')).find(
      (a) => a.getAttribute('href') === previousHref
    );
    if (same) same.focus({ preventScroll: true });
  }

  /**
   * Die Seite bleibt, wo sie ist. Nur wenn das neu erschienene Ende (Auswahl bzw. Preis) unterhalb des sichtbaren
   * Bereichs liegt, wird gerade so weit gescrollt, dass es sichtbar ist.
   */
  revealNewContent() {
    const target = this.body.querySelector('.service-prices__result')
      || Array.from(this.body.querySelectorAll('.service-prices__section')).pop();
    if (!target) return;

    const rect = target.getBoundingClientRect();
    if (rect.bottom <= window.innerHeight && rect.top >= 0) return;

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    target.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
  }
}
