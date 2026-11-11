/* Browser messages and local routes for the current page language. */
const LabI18n = (() => {
    const catalog = JSON.parse(document.getElementById('lab-i18n').textContent);
    function interpolate(text, values) {
        return text.replace(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g, (match, key) =>
            Object.hasOwn(values, key) ? String(values[key]) : match
        );
    }
    function t(key, values = {}) {
        return interpolate(catalog.messages[key] ?? key, values);
    }
    function html(key, values = {}) {
        return t(key, values).replace(/[&<>"']/g, char =>
            ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[char]
        );
    }
    function pageUrl(page, values = {}, locale = catalog.locale) {
        const params = new URLSearchParams(values);
        params.delete('lang');
        const url = new URL('/' + locale + '/' + page, location.origin);
        if (page === 'public') {
            for (const [scope, key] of Object.entries(catalog.publicRoutes)) {
                const value = params.get(key) || '';
                const id = scope === 'case_file' ? value.replace(/\.md$/, '') : value;
                const valid = scope === 'model'
                    ? /^~?[A-Za-z0-9_-]+\/[A-Za-z0-9_.:-]+$/.test(id)
                    : /^[A-Za-z0-9_-]+$/.test(id);
                if (!valid) continue;
                url.pathname += '/' + scope + '/' + id.split('/').map(encodeURIComponent).join('/');
                params.delete(key);
                break;
            }
        }
        url.search = params;
        return url;
    }
    function publicParams() {
        const params = new URLSearchParams(location.search);
        const [, locale, page, scope, ...segments] = location.pathname.replace(/\/$/, '').split('/');
        const key = catalog.publicRoutes[scope];
        if (locale === catalog.locale && page === 'public' && key && segments.length) {
            const id = segments.map(decodeURIComponent).join('/');
            params.set(key, scope === 'case_file' ? id + '.md' : id);
        }
        return params;
    }
    function replacePublic(params) {
        history.replaceState(null, '', pageUrl('public', params));
        for (const link of document.querySelectorAll('.language-switch a')) {
            link.href = pageUrl('public', params, link.dataset.labLanguage);
        }
    }
    return {
        locale: catalog.locale,
        t,
        html,
        pageUrl,
        publicParams,
        replacePublic,
        mcpSetup(endpoint) {
            return interpolate(catalog.mcpSetup, {endpoint,config:JSON.stringify({mcpServers:{prisonlab:{serverUrl:endpoint}}},null,2)});
        },
        api(path = '/api.php') {
            const url = new URL(path, location.origin);
            url.searchParams.set('lang', catalog.locale);
            return url;
        },
        login() {
            return '/auth.php?action=login&lang=' + encodeURIComponent(catalog.locale);
        }
    };
})();
