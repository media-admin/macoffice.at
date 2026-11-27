/**
 * Reparaturkostenrechner ([shortcode_service_pricelist]) ohne Seitenneuladen.
 *
 * - Die Auswahl-Links funktionieren auch ohne JavaScript (normale Seitenaufrufe mit ?cat=…&device=…).
 * - Mit JavaScript wird der Inhalt per AJAX (admin-ajax.php, Aktion macoffice_sp) nachgeladen und nicht ersetzt, sondern
 *   abgeglichen: unveränderte Abschnitte bleiben stehen, neue blenden ein, nicht mehr passende klappen weg. Die Auswahl
 *   wird sofort markiert, die Antwort beim Darüberfahren vorgeladen. Die Scrollposition bleibt erhalten.
 * - Die Adresse wird per History-API aktualisiert (Zurück-Button, Teilen).
 * - Gerät und Thema samt Preis werden in das Reparatur-Anfrageformular (Contact Form 7, Felder "device", "repair" und
 *   als erste Zeile der "error-description") eingetragen.
 */

const PARAMS = ['cat', 'cat2', 'cat3', 'device', 'repair'];
const ANIMATED = new Set(['result', 'instructions']);
const LOADING_DELAY = 200; // ms, erst dann Ladehinweis (schnelle Antworten flackern nicht)
const LEAVE_MS = 220;

export default class ServicePrices {
  constructor() {
    this.root = document.querySelector('.service-prices[data-endpoint]');
    if (!this.root) return;

    this.body = this.root.querySelector('.service-prices__body');
    this.endpoint = this.root.dataset.endpoint;
    this.base = this.root.dataset.base || window.location.href.split('?')[0];
    this.requests = new Map(); // Abfrage -> Promise<html>
    this.current = 0; // laufende Ladung (nur die letzte zählt)

    this.root.addEventListener('click', (e) => this.onClick(e));
    ['pointerenter', 'focusin'].forEach((type) => this.root.addEventListener(type, (e) => this.onHover(e), true));
    window.addEventListener('popstate', () => this.load(window.location.href, { push: false }));

    // Formular nach dem Absenden (Contact Form 7 leert die Felder) erneut befüllen
    ['wpcf7mailsent', 'wpcf7submit', 'wpcf7reset'].forEach((type) =>
      document.addEventListener(type, () => window.setTimeout(() => this.syncForm(), 50))
    );

    this.syncForm();
  }

  // ── Ereignisse ─────────────────────────────────────────────────────────────

  linkFrom(e) {
    const link = e.target.closest?.('a.service-prices__option, a.service-prices__reset');
    return link && this.root.contains(link) ? link : null;
  }

  onClick(e) {
    const link = this.linkFrom(e);
    if (!link) return;
    // Neuer Tab/Fenster etc. nicht abfangen
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    e.preventDefault();
    const reset = link.classList.contains('service-prices__reset');

    if (!reset) {
      // Sofort markieren: fühlt sich unmittelbar an, die Antwort bestätigt nur noch
      const section = link.closest('.service-prices__section');
      section?.querySelectorAll('a.service-prices__option').forEach((a) => this.markSelected(a, a === link));
    }
    this.load(link.href, { push: true, reset });
  }

  onHover(e) {
    const link = this.linkFrom(e);
    if (link && !link.classList.contains('service-prices__reset')) this.fetchHtml(this.queryFrom(link.href));
  }

  // ── Laden ──────────────────────────────────────────────────────────────────

  queryFrom(href) {
    const url = new URL(href, window.location.href);
    const query = new URLSearchParams();
    PARAMS.forEach((key) => {
      const value = url.searchParams.get(key);
      if (value && /^\d+$/.test(value)) query.set(key, value);
    });
    return query;
  }

  fetchHtml(query) {
    const key = query.toString();
    if (!this.requests.has(key)) {
      const request = new URLSearchParams({ action: 'macoffice_sp', base: this.base });
      query.forEach((value, name) => request.set(name, value));

      const promise = fetch(`${this.endpoint}?${request.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      })
        .then((response) => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          return response.json();
        })
        .then((json) => {
          if (!json || !json.success || typeof json.data?.html !== 'string') throw new Error('Unerwartete Antwort');
          return json.data.html;
        });
      promise.catch(() => this.requests.delete(key)); // Fehler nicht zwischenspeichern
      this.requests.set(key, promise);
    }
    return this.requests.get(key);
  }

  async load(href, { push = true, reset = false } = {}) {
    const query = this.queryFrom(href);
    const queryString = query.toString();
    const ticket = ++this.current;

    const timer = window.setTimeout(() => {
      if (ticket === this.current) {
        this.root.classList.add('is-loading');
        this.root.setAttribute('aria-busy', 'true');
      }
    }, LOADING_DELAY);

    try {
      const html = await this.fetchHtml(query);
      if (ticket !== this.current) return; // inzwischen wurde etwas anderes gewählt

      this.reconcile(html);

      if (push) {
        const target = window.location.pathname + (queryString ? `?${queryString}` : '');
        if (target !== window.location.pathname + window.location.search) {
          window.history.pushState({ servicePrices: true }, '', target);
        }
      }

      this.syncForm();
      window.requestAnimationFrame(() => this.reveal(reset));
    } catch (err) {
      if (ticket !== this.current) return;
      // Notlösung: normale Navigation, der Rechner funktioniert auch so
      console.error('[ServicePrices]', err);
      window.location.href = href;
    } finally {
      window.clearTimeout(timer);
      if (ticket === this.current) {
        this.root.classList.remove('is-loading');
        this.root.removeAttribute('aria-busy');
      }
    }
  }

  // ── Abgleich statt Austausch ───────────────────────────────────────────────

  keyOf(el, index) {
    if (el.id) return `id:${el.id}`;
    const c = el.classList;
    if (c.contains('service-prices__head')) return 'head';
    if (c.contains('service-prices__summary')) return 'summary';
    if (c.contains('service-prices__result')) return 'result';
    if (c.contains('repair-instructions')) return 'instructions';
    const section = el.className.match(/service-prices__section--(\w+)/);
    if (section) return `section:${section[1]}`;
    return `${el.tagName}:${index}`;
  }

  /** Abschnitte mit gleichen Einträgen unterscheiden sich nur in der Markierung. */
  signature(el) {
    return Array.from(el.querySelectorAll('a.service-prices__option'))
      .map((a) => `${a.getAttribute('href')}|${a.textContent}`)
      .join('\n');
  }

  markSelected(link, on) {
    link.classList.toggle('is-selected', on);
    if (on) link.setAttribute('aria-current', 'true');
    else link.removeAttribute('aria-current');
  }

  reconcile(html) {
    const template = document.createElement('template');
    template.innerHTML = html;

    const existing = new Map();
    Array.from(this.body.children).forEach((el, i) => {
      if (!el.classList.contains('is-leaving')) existing.set(this.keyOf(el, i), el);
    });

    const used = new Set();
    let previous = null;

    Array.from(template.content.children).forEach((incoming, i) => {
      const key = this.keyOf(incoming, i);
      let node = existing.get(key);
      used.add(key);

      if (!node) {
        node = incoming;
        previous ? previous.after(node) : this.body.prepend(node);
        if (key.startsWith('section:') || ANIMATED.has(key)) this.enter(node);
      } else if (node.outerHTML !== incoming.outerHTML) {
        node = this.update(node, incoming, key);
      }
      previous = node;
    });

    existing.forEach((node, key) => {
      if (!used.has(key)) this.leave(node, key);
    });
  }

  update(node, incoming, key) {
    // Gleiche Einträge: nur Markierung ändern (die Schaltflächen animieren selbst)
    if (key.startsWith('section:') && this.signature(node) === this.signature(incoming)) {
      const next = new Map(Array.from(incoming.querySelectorAll('a.service-prices__option')).map((a) => [a.getAttribute('href'), a]));
      node.querySelectorAll('a.service-prices__option').forEach((a) => {
        const match = next.get(a.getAttribute('href'));
        if (match) this.markSelected(a, match.classList.contains('is-selected'));
      });
      return node;
    }

    // Kopfzeile: Inhalt tauschen, Element (und Layout) bleibt
    if (key === 'head') {
      node.innerHTML = incoming.innerHTML;
      return node;
    }

    node.replaceWith(incoming);
    if (key.startsWith('section:') || ANIMATED.has(key)) this.enter(incoming);
    return incoming;
  }

  enter(el) {
    el.classList.add('sp-enter');
    const done = () => el.classList.remove('sp-enter');
    el.addEventListener('animationend', done, { once: true });
    window.setTimeout(done, 600);
  }

  leave(node, key) {
    if (!(key.startsWith('section:') || ANIMATED.has(key)) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      node.remove();
      return;
    }
    node.style.height = `${node.offsetHeight}px`;
    void node.offsetHeight; // Reflow, damit die Höhe animiert wird
    node.classList.add('is-leaving');
    window.setTimeout(() => node.remove(), LEAVE_MS + 40);
  }

  /**
   * Die Seite bleibt, wo sie ist. Nur wenn das neu erschienene Ende (Auswahl bzw. Preis) unterhalb des sichtbaren
   * Bereichs liegt, wird gerade so weit gescrollt, dass es sichtbar ist. Nach "Zurücksetzen" wird der Anfang des
   * Rechners sichtbar gemacht, falls er oberhalb liegt.
   */
  reveal(reset) {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const behavior = reduce ? 'auto' : 'smooth';

    if (reset) {
      if (this.root.getBoundingClientRect().top < 0) this.root.scrollIntoView({ block: 'start', behavior });
      return;
    }

    const target = this.body.querySelector('.service-prices__result')
      || Array.from(this.body.querySelectorAll('.service-prices__section:not(.is-leaving)')).pop();
    if (!target) return;

    const rect = target.getBoundingClientRect();
    if (rect.bottom <= window.innerHeight && rect.top >= 0) return;
    target.scrollIntoView({ block: 'nearest', behavior });
  }

  // ── Anfrageformular ────────────────────────────────────────────────────────

  /**
   * Trägt Gerät und Thema (inkl. Preis) in das Reparatur-Anfrageformular ein. Das Feld "device" ist für Kunden
   * änderbar: wurde es von Hand geändert, bleibt der Text stehen. Das versteckte Feld "repair" wird immer gesetzt.
   */
  syncForm() {
    const summary = this.body.querySelector('.service-prices__summary');
    const device = summary?.dataset.device || '';
    const repair = summary?.dataset.repair || '';

    document.querySelectorAll('form.wpcf7-form').forEach((form) => {
      if (!form.querySelector('[name="device"]') || !form.querySelector('[name="repair"]')) return;
      this.setField(form.querySelector('[name="device"]'), device);
      this.setField(form.querySelector('[name="repair"]'), repair, true);
      const description = form.querySelector('[name="error-description"]');
      if (description) this.setDescription(description, repair ? `Thema: ${repair}` : '');
    });
  }

  setField(input, value, force = false) {
    const lastAuto = input.dataset.spAuto;
    if (!force && input.value !== '' && input.value !== lastAuto) return; // vom Kunden geändert
    if (input.value === value) return;

    input.value = value;
    if (value) input.dataset.spAuto = value;
    else delete input.dataset.spAuto;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  /**
   * Fehlerbeschreibung: Die erste Zeile "Thema: … – € … inkl. USt." wird automatisch gesetzt bzw. ersetzt. Was der
   * Kunde selbst schreibt, bleibt darunter unverändert erhalten.
   */
  setDescription(textarea, line) {
    const last = textarea.dataset.spAuto || '';
    let rest = textarea.value;

    if (last && rest.startsWith(last)) rest = rest.slice(last.length).replace(/^\n\n?/, '');
    const next = line ? (rest ? `${line}\n\n${rest}` : `${line}\n\n`) : rest;
    if (next === textarea.value) return;

    textarea.value = next;
    if (line) textarea.dataset.spAuto = line;
    else delete textarea.dataset.spAuto;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  }
}
