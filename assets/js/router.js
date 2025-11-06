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
    const renderFn = this.routes[path] || this.routes['#/404'];
    if (renderFn) renderFn(this.rootEl);
  }
}
