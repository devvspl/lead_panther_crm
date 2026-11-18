const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const template = fs.readFileSync('resources/views/livewire/shared/notification-bell.blade.php', 'utf8');
function dropdown() {
    const source = template.match(/x-data="([\s\S]*?)" @keydown/)[1]
        .replace(/@js\([^\n]*\)/, "'test-channel'").replaceAll('&amp;', '&');
    return vm.runInNewContext('(' + source + ')', { $wire: { entangle: () => ({ live: false }) } });
}
test('outside clicks close immediately without waiting for a server response', () => {
    const state = dropdown(); state.dropdownOpen = true;
    state.$el = { contains: () => false };
    state.closeOutside({ target: {} });
    assert.equal(state.dropdownOpen, false);
    assert.match(template, /@click.window.capture="closeOutside\(\$event\)"/);
    assert.match(template, /x-show="dropdownOpen"/);
});
test('interacting inside the notification panel or bell does not close it', () => {
    const state = dropdown(); state.dropdownOpen = true;
    state.$el = { contains: () => true };
    state.closeOutside({ target: {} });
    assert.equal(state.dropdownOpen, true);
});
test('closed dropdown ignores unrelated page clicks', () => {
    const state = dropdown();
    state.closeOutside({ target: {} });
    assert.equal(state.dropdownOpen, false);
});
