const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Exercise the actual Alpine layout state, including nested SVG/button targets.
const layout = fs.readFileSync('resources/views/layouts/app.blade.php', 'utf8');
const sidebarTemplate = fs.readFileSync('resources/views/components/ui/sidebar.blade.php', 'utf8');
const topbar = fs.readFileSync('resources/views/components/ui/topbar.blade.php', 'utf8');
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

test('both navigation sections close the mobile drawer when a link is selected', () => {
    const links = [...sidebarTemplate.matchAll(/<a\s[\s\S]*?class="group[\s\S]*?>/g)];
    assert.equal(links.length, 2);
    for (const [link] of links) {
        const handler = link.match(/x-on:click="([^"]+)"/)[1];
        const state = sidebar(); state.sidebarOpen = true; state.sidebarTooltip.show = true;
        vm.runInNewContext('(function(){with(this){' + handler + '}}).call(state)', {state});
        assert.equal(state.sidebarOpen, false);
        assert.equal(state.sidebarTooltip.show, false);
    }
});

test('navigation controls are rebuilt with the new body instead of retaining stale Alpine state', () => {
    for (const template of [sidebarTemplate, topbar]) {
        assert.doesNotMatch(template, /@persist\(|wire:persist=/);
    }
    assert.match(sidebarTemplate, /<script data-navigate-once>/);
    assert.match(layout, /x-on:livewire:navigating.window="sidebarOpen = false; hideSidebarTooltip\(\)"/);
    assert.match(layout, /x-on:livewire:navigated.window="sidebarOpen = false; hideSidebarTooltip\(\)"/);
    // Simulate a link click, replacing the page body, then reopening and tapping outside.
    const previous = sidebar(); previous.sidebarOpen = true; previous.closeMobileSidebar();
    assert.equal(previous.sidebarOpen, false);
    const current = sidebar(); current.sidebarOpen = true;
    current.closeMobileSidebarOutside({target:{closest:()=>null}});
    assert.equal(current.sidebarOpen, false);
});
