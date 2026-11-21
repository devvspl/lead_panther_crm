# Frontend, reusable components and theme

## Rendering architecture

Livewire3 PHP components under app/Livewire pair with resources/views/livewire templates. Full-page renders use layouts.app; Volt auth pages under livewire/pages/auth use layouts.guest. Routes may instead render a page embedding a component. Dynamic behavior is largely Alpine inside Blade plus Livewire, not a React/Vue SPA.

Vite builds resources/css/app.css and resources/js/app.js. app.js imports bootstrap, Flatpickr and its CSS; bootstrap configures Axios and optional Echo/Reverb. Livewire supplies its client runtime; avoid adding a second Alpine runtime without checking conflicts. Public browser-push scripts/service worker have their own registration lifecycle.

## Theme source of truth

ThemeService::getUserTheme reads per-user general_settings keys theme_* over DEFAULT_LIGHT. Layout emits CSS variables for colors/sidebar/header/font/spacing-radius; Tailwind semantic tokens map to --lp-*-rgb variables. theme-primary and lp-primary variable families coexist; check shared layout/CSS bridges before new styles. Use ink, muted, canvas, surface, border, primary, success/warning/danger and existing theme icons.

Theme customization persists via ThemeSettingsController. Source supports custom colors; the service defines DEFAULT_LIGHT rather than a complete documented dark-mode contract. Dark/custom theme behavior needs visual verification, not an assumption that every hardcoded class adapts.

## Component inventory

| Component/trait | Reuse and important contract |
|---|---|
| ui.advanced-table + HasAdvancedTable | Search, column filters, sorting, selection, pagination, visibility and per-user settings |
| ui.themed-select | Themed button/listbox over hidden native select; options or slot; Livewire model, selected/disabled states, searchable popup |
| ui.dropdown | Shared menu surface; use existing placement/close behavior |
| ui.modal / ui.confirmation-modal | Named open-modal / close-modal events, scrollable viewport and confirmation patterns |
| ui.offcanvas | Drawer shell; inspect overlay/scroll/close behavior before page-specific fixes |
| ui.sidebar / ui.topbar | NavigationConfig menu data and responsive app shell |
| ui.button / card / badge / toast | Consistent theme variants, state and messaging |
| ui.date-range-picker | Shared range UI; avoid independent date parser/filter semantics |
| ui.export-button | Existing export workflow UI |
| ui.password-input | Existing password/secret entry presentation |
| ui.theme-customizer | Theme settings UI |
| ui.audit-json / activity-timeline | Audit/activity presentation with redaction |
| ui.notification-item / notification-table-cell / browser-push | Notification/push presentation |
| ui.client-directory-actions / client-directory-credits | Client row menu/credit display |
| ui.team-member-actions / team-switch / assignment-member-control | Team/member actions and assignment controls |
| ui.lead-archive-menu / archived-lead-actions | Super Admin archive/restore/purge presentation |
| ui.delivery-statistics / delivered-lead-action | Delivery status and lead access |

All above live in resources/views/components/ui. Table action renderers can use view components/closures; inspect column definitions in owning Livewire class.

## Themed Select details

It derives slot content safely when rendered as a component or included view, maintains hidden select for form/Livewire binding, and teleports fixed listbox panel to body to avoid table clipping. Keyboard navigation, selected/disabled options, search, scrolling and validation state are implemented. Stable wire:key/popup keys and model binding matter when filtering/reordering rows. Do not replace its DOM with unrelated browser select styling or initialize duplicate popups after Livewire navigation.

## Tables, filtering and export

HasAdvancedTable uses WithPagination, per-page15 default, search/status/columnFilters, selectedRows/selectAll and persisted visibleColumns. Identifier derives from component class unless overridden. Components supply tableColumns and actual query filtering; the trait does not enforce tenant/security correctness by itself. OrganizationManager additionally implements column ordering/preferences.

The shared advanced table removes financial columns for lower roles. BaseStyledExport also filters financial headings/keys and source presentation remains separately protected. Correct source query and explicit field whitelist still required; do not trust display-only hiding to protect JSON/exports.

## Responsive behavior and verification targets

App layout controls sidebar overlay, overflow and navigation; it contains broad overflow/scrollbar CSS, so missing scroll behavior can be global. Table horizontal scrolling should stay inside table container. Modals/drawers must keep headings/actions and validation accessible within viewport. Popovers must clear table overflow and support outside click/Escape.

No browser responsive audit was performed for this documentation task. For UI changes test mobile/tablet/laptop/desktop, narrow nested modals, keyboard focus, long labels, custom theme, Livewire navigation, filters resetting page, stable row keys and all role-specific views. Native browser select hidden under the themed control is intentional.
