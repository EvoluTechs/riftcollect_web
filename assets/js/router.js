export class Router {
  constructor(rootEl) {
    this.rootEl = rootEl;
    this.routes = {};
    window.addEventListener('hashchange', () => this.render());
  }
  register(path, renderFn) {
    this.routes[path] = renderFn;
  }
  start() {
    this.render();
  }
  render() {
    const hash = location.hash || '#/';
    const path = hash.split('?')[0];
    let renderFn = this.routes[path] || null;
    let ctx = { path };
    if (!renderFn) {
      // Basic wildcard support: register pattern ending with '/*'
      const keys = Object.keys(this.routes);
      for (const k of keys) {
        if (k.endsWith('/*')) {
          const base = k.slice(0, -2);
          if (path === base || path.startsWith(base + '/')) {
            renderFn = this.routes[k];
            const rest = path.length > base.length ? path.slice(base.length + 1) : '';
            ctx = { path, splat: rest, parts: rest ? rest.split('/') : [] };
            break;
          }
        }
      }
    }
    if (!renderFn) renderFn = this.routes['#/404'];
    if (renderFn) renderFn(this.rootEl, ctx);
  }
}
