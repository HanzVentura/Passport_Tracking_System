(function () {
    const saveQueues = new Map();

    async function readResponse(response) {
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to save application draft.');
        }
        return result;
    }

    async function load(type) {
        const response = await fetch(`../api/application-draft.php?type=${encodeURIComponent(type)}`);
        return readResponse(response);
    }

    function save(type, data) {
        const previous = saveQueues.get(type) || Promise.resolve();
        const operation = previous.then(async function () {
            const response = await fetch('../api/application-draft.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save', type: type, data: data })
            });
            return readResponse(response);
        });
        saveQueues.set(type, operation.catch(function () {}));
        return operation;
    }

    async function clear(type) {
        const previous = saveQueues.get(type) || Promise.resolve();
        await previous;
        const response = await fetch('../api/application-draft.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', type: type })
        });
        return readResponse(response);
    }

    window.ApplicationDraft = { load: load, save: save, clear: clear };
})();
