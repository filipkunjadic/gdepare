export function createPayerTagSelection(initialTags = [], initialPayerId = null) {
    let selectedTags = [...initialTags];
    let selectedPayerId = initialPayerId;
    let automaticTags = new Set();
    let skipped = 0;
    const key = name => name.trim().toLocaleLowerCase();

    function update(tags) {
        selectedTags = [...tags];
        skipped = 0;
        const selectedKeys = new Set(tags.map(key));
        automaticTags = new Set([...automaticTags].filter(name => selectedKeys.has(name)));
    }

    function select(payer) {
        const payerId = payer?.id ?? null;
        if (payerId === selectedPayerId) return { tags: [...selectedTags], skipped };

        selectedPayerId = payerId;
        selectedTags = selectedTags.filter(name => !automaticTags.has(key(name)));
        automaticTags = new Set();
        skipped = 0;
        const selectedKeys = new Set(selectedTags.map(key));

        for (const tag of payer?.default_tags ?? []) {
            const name = tag.name.trim();
            const normalizedName = key(name);
            if (!name || selectedKeys.has(normalizedName)) continue;
            if (selectedTags.length >= 20) {
                skipped++;
                continue;
            }
            selectedTags.push(name);
            selectedKeys.add(normalizedName);
            automaticTags.add(normalizedName);
        }

        return { tags: [...selectedTags], skipped };
    }

    return { select, update };
}
