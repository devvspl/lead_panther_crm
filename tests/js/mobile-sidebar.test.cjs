const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Exercise the actual Alpine layout state, including nested SVG/button targets.
const layout = fs.readFileSync('resources/views/layouts/app.blade.php', 'utf8');
function sidebar(desktop = false) {
    return vm.runInNewContext('(' + layout.match(/<body\s+x-data="([\s\S]*?)"\s+x-bind:class/)[1] + ')', {
        localStorage: { getItem: () => null },
        window: { matchMedia: () => ({ matches: desktop }) },
    });
}
test('mobile outside click closes the sidebar even when a page control stops bubbling', () => {
    const state = sidebar();
    state.sidebarOpen = true;
    state.sidebarTooltip.show = true;
    state.closeMobileSidebarOutside({ target: { closest: () => null } });
    assert.equal(state.sidebarOpen, false);
    assert.equal(state.sidebarTooltip.show, false);
    assert.match(layout, /x-on:click.window.capture="closeMobileSidebarOutside\(\$event\)"/);
});
test('inside taps and nested toggle targets do not dismiss the mobile sidebar', () => {
    for (const target of ['#sidebar-main', '[data-mobile-sidebar-toggle]']) {
        const state = sidebar(); state.sidebarOpen = true;
        state.closeMobileSidebarOutside({ target: { closest: selector => selector.includes(target) ? {} : null } });
        assert.equal(state.sidebarOpen, true);
    }
});
test('desktop outside clicks preserve sidebar state', () => {
    const state = sidebar(true); state.sidebarOpen = true;
    state.closeMobileSidebarOutside({ target: { closest: () => null } });
    assert.equal(state.sidebarOpen, true);
});
