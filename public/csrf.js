(function () {
    let csrfToken = '';
    let tokenPromise = null;

    async function loadToken() {
        if (tokenPromise) return tokenPromise;
        tokenPromise = (async () => {
            try {
                const response = await nativeFetch('../api/csrf.php', { credentials: 'same-origin' });
                const data = await response.json();
                csrfToken = data.csrfToken || '';
            } catch (error) {
                console.error('Unable to initialize CSRF protection', error);
                csrfToken = '';
            }
            return csrfToken;
        })();
        const token = await tokenPromise;
        tokenPromise = null;
        return token;
    }

    async function ensureToken() {
        if (!csrfToken) {
            await loadToken();
        }
        return csrfToken;
    }

    const nativeFetch = window.fetch.bind(window);
    window.fetch = async function (resource, options) {
        const fetchOptions = options ? { ...options } : {};
        const method = (fetchOptions.method || 'GET').toUpperCase();
        const url = typeof resource === 'string' ? resource : (resource.url || '');

        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && url.includes('/api/')) {
            const token = await ensureToken();
            fetchOptions.credentials = 'same-origin';
            fetchOptions.headers = new Headers(fetchOptions.headers || {});
            fetchOptions.headers.set('X-CSRF-Token', token);
        }
        return nativeFetch(resource, fetchOptions);
    };

    window.getCsrfToken = async function () {
        return ensureToken();
    };

    loadToken();
})();
