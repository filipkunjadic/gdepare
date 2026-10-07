import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/utils/tagColors.js', import.meta.url), 'utf8');
const { tagColors } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('badge foreground contrasts with bright, dark, and saturated backgrounds', () => {
    for (const color of ['#ffffff', '#ffff00', '#00ff00', '#ff0000', '#767676']) {
        assert.equal(tagColors(color).foreground, '#000000', color);
        assert.equal(tagColors(color).darkText, true);
        assert.equal(tagColors(color).background, color);
    }
    for (const color of ['#000000', '#0000ff', '#757575']) {
        assert.equal(tagColors(color).foreground, '#ffffff', color);
        assert.equal(tagColors(color).darkText, false);
    }
});

test('missing or invalid tag colors use the default and valid uppercase hex is preserved', () => {
    for (const color of [null, undefined, '', 'red', '#fff', 'url(example)']) {
        assert.equal(tagColors(color).background, '#e2e8f0');
        assert.equal(tagColors(color).foreground, '#000000');
    }
    assert.equal(tagColors('#ABCDEF').background, '#ABCDEF');
    assert.equal(tagColors('#ABCDEF').foreground, '#000000');
});
