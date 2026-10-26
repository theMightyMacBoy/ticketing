<script>
    (function () {
        var stored = null;

        try {
            stored = localStorage.getItem('laravel-ticket.theme');
        } catch (error) {}

        if (stored === 'dark' || stored === 'light') {
            document.documentElement.setAttribute('data-theme', stored);
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-theme-toggle]');

            if (!button) {
                return;
            }

            var current = document.documentElement.getAttribute('data-theme');

            if (current !== 'dark' && current !== 'light') {
                current = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);

            try {
                localStorage.setItem('laravel-ticket.theme', next);
            } catch (error) {}
        });
    }());
</script>
<style>
    :root {
        --ink: #1c1915;
        --ink-soft: #3c3832;
        --muted: #5e574e;
        --line: #ddd6c8;
        --line-strong: #c9c0b0;
        --paper: #f3f0ea;
        --sheet: #faf8f4;
        --rail: #221f1b;
        --rail-text: #d8d1c5;
        --rail-faint: #aaa292;
        --rail-edge: transparent;
        --rail-current: #2c2823;
        --rail-line: #4a453c;
        --rail-line-hover: #d8d1c5;
        --mark: #f6f1e6;
        --brass: #a68456;
        --wash: #ebe6dc;
        --note: #f6f1e4;
        --note-line: #e3d7bf;
        --ok: #234233;
        --ok-wash: #e7efe9;
        --ok-line: #2f5d45;
        --danger: #8f2d3a;
        --danger-ink: #6d2430;
        --danger-wash: #f8ecec;
        --button-hover: #322e28;
        --placeholder: #8a8276;
        --selection: #e7d7b6;
        --flag: #6d4a12;
        --state-new: #3c3832;
        --state-pending: #8a5a12;
        --state-progress: #2d4a6e;
        --state-resolved: #2f5d45;
        --mono: ui-monospace, "Cascadia Mono", "Segoe UI Mono", "Liberation Mono", Menlo, Consolas, monospace;
        --sans: "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif;
        color-scheme: light;
    }

    html[data-theme="dark"] {
        --ink: #f4f0e8;
        --ink-soft: #ddd6c8;
        --muted: #b7ad9f;
        --line: #3f382f;
        --line-strong: #574e43;
        --paper: #1c1916;
        --sheet: #26231e;
        --rail: #12100e;
        --rail-text: #d8d1c5;
        --rail-faint: #9c9488;
        --rail-edge: #3f382f;
        --rail-current: #2a261f;
        --rail-line: #4a453c;
        --rail-line-hover: #d8d1c5;
        --mark: #f6f1e6;
        --brass: #c6a56e;
        --wash: #2e2a24;
        --note: #2a2418;
        --note-line: #6b5433;
        --ok: #d5ebdd;
        --ok-wash: #1a2820;
        --ok-line: #7dba96;
        --danger: #f3b7be;
        --danger-ink: #f3b7be;
        --danger-wash: #3a2226;
        --button-hover: #e6dfd2;
        --placeholder: #9a9184;
        --selection: #5a4630;
        --flag: #e4c48a;
        --state-new: #ddd6c8;
        --state-pending: #e2b56a;
        --state-progress: #a9c4e4;
        --state-resolved: #8fc4a6;
        color-scheme: dark;
    }

    @media (prefers-color-scheme: dark) {
        html:not([data-theme="light"]) {
            --ink: #f4f0e8;
            --ink-soft: #ddd6c8;
            --muted: #b7ad9f;
            --line: #3f382f;
            --line-strong: #574e43;
            --paper: #1c1916;
            --sheet: #26231e;
            --rail: #12100e;
            --rail-text: #d8d1c5;
            --rail-faint: #9c9488;
            --rail-edge: #3f382f;
            --rail-current: #2a261f;
            --rail-line: #4a453c;
            --rail-line-hover: #d8d1c5;
            --mark: #f6f1e6;
            --brass: #c6a56e;
            --wash: #2e2a24;
            --note: #2a2418;
            --note-line: #6b5433;
            --ok: #d5ebdd;
            --ok-wash: #1a2820;
            --ok-line: #7dba96;
            --danger: #f3b7be;
            --danger-ink: #f3b7be;
            --danger-wash: #3a2226;
            --button-hover: #e6dfd2;
            --placeholder: #9a9184;
            --selection: #5a4630;
            --flag: #e4c48a;
            --state-new: #ddd6c8;
            --state-pending: #e2b56a;
            --state-progress: #a9c4e4;
            --state-resolved: #8fc4a6;
            color-scheme: dark;
        }
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        color: var(--ink);
        background: var(--paper);
        font-family: var(--sans);
        font-size: 14px;
        line-height: 1.45;
    }

    ::selection { background: var(--selection); color: var(--ink); }

    :focus-visible {
        outline: 2px solid var(--ink);
        outline-offset: 2px;
    }

    .skip {
        position: absolute;
        left: 12px;
        top: -40px;
        background: var(--sheet);
        color: var(--ink);
        padding: 6px 10px;
        z-index: 2;
    }

    .skip:focus { top: 12px; }

    .shell {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 216px minmax(0, 1fr);
    }

    .rail {
        background: var(--rail);
        color: var(--rail-text);
        display: flex;
        flex-direction: column;
        gap: 22px;
        padding: 22px 14px 16px;
        position: sticky;
        top: 0;
        height: 100vh;
        border-right: 1px solid var(--rail-edge);
    }

    .mark {
        color: var(--mark);
        text-decoration: none;
        font-weight: 600;
        font-size: 18px;
        letter-spacing: -0.04em;
        padding: 0 12px;
    }

    .rail-kicker {
        margin: 2px 0 0;
        padding: 0 12px;
        color: var(--rail-faint);
        font-size: 12px;
    }

    .rail-nav { display: grid; gap: 2px; }

    .rail-label {
        margin: 12px 12px 4px;
        color: var(--rail-faint);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .rail-nav a {
        color: var(--rail-text);
        text-decoration: none;
        padding: 6px 12px;
        border-left: 2px solid transparent;
        white-space: nowrap;
    }

    .rail-team a { white-space: normal; line-height: 1.3; }

    .rail-nav a:hover { color: var(--mark); }

    .rail-nav a.is-current {
        color: var(--mark);
        border-left-color: var(--brass);
        background: var(--rail-current);
    }

    .rail a:focus-visible,
    .rail button:focus-visible { outline-color: var(--mark); }

    .rail-foot { margin-top: auto; padding: 0 12px; }

    .who,
    .who-mail { margin: 0; }

    .who { color: var(--mark); font-weight: 600; }

    .who-mail {
        color: var(--rail-faint);
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .rail-foot form { margin-top: 10px; }

    .rail button {
        background: transparent;
        color: var(--rail-text);
        border-color: var(--rail-line);
        margin: 0;
        padding: 5px 10px;
    }

    .rail button:hover { color: var(--mark); border-color: var(--rail-line-hover); }

    .rail .theme-toggle { margin-top: 10px; }

    .stage {
        padding: 28px 32px 72px;
        min-width: 0;
    }

    .page-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px 28px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .kicker {
        margin: 0 0 4px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    h1 {
        margin: 0;
        font-size: 26px;
        line-height: 1.15;
        font-weight: 600;
        letter-spacing: -0.03em;
    }

    h2 {
        margin: 0 0 10px;
        font-size: 15px;
        font-weight: 600;
        letter-spacing: -0.015em;
    }

    p { margin: 0; }

    .lede,
    .count,
    .hint { color: var(--muted); }

    .lede { margin-top: 6px; max-width: 42rem; }

    .count { margin-top: 4px; font-size: 13px; }

    .hint { margin: 0 0 10px; font-size: 13px; }

    .flash {
        margin: 0 0 16px;
        padding: 8px 12px;
        background: var(--ok-wash);
        color: var(--ok);
        border-left: 3px solid var(--ok-line);
    }

    .flash.is-error {
        background: var(--danger-wash);
        color: var(--danger-ink);
        border-left-color: var(--danger);
    }

    .flash ul { margin: 0; padding-left: 18px; }

    .finder { display: grid; gap: 4px; justify-items: start; }

    .finder label,
    .form-stack > label {
        display: grid;
        gap: 4px;
        color: var(--ink-soft);
        font-size: 13px;
    }

    .form-stack > button { justify-self: start; }

    .gate-panel .form-stack > button { justify-self: stretch; }

    .finder-row,
    .row-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .finder-row { margin-top: 4px; }

    .finder input { width: 260px; }

    input,
    select,
    textarea {
        width: 100%;
        padding: 8px 10px;
        color: var(--ink);
        background: var(--sheet);
        border: 1px solid var(--line-strong);
        border-radius: 2px;
        font: inherit;
    }

    textarea { min-height: 96px; resize: vertical; }

    ::placeholder { color: var(--placeholder); }

    button,
    a.button {
        appearance: none;
        display: inline-block;
        margin: 0;
        padding: 7px 12px;
        background: var(--ink);
        color: var(--sheet);
        border: 1px solid var(--ink);
        border-radius: 2px;
        font: inherit;
        font-size: 13px;
        line-height: 1.3;
        text-decoration: none;
        cursor: pointer;
    }

    button:hover,
    a.button:hover { background: var(--button-hover); }

    button.quiet,
    a.button.quiet {
        background: transparent;
        color: var(--ink);
        border-color: var(--line-strong);
    }

    button.quiet:hover,
    a.button.quiet:hover { background: var(--wash); }

    button.danger {
        background: transparent;
        color: var(--danger);
        border-color: var(--danger);
    }

    button.danger:hover { background: var(--danger-wash); }

    .form-stack {
        display: grid;
        gap: 12px;
        align-content: start;
    }

    .form-stack.narrow { max-width: 420px; margin-top: 28px; }

    label.check {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 8px;
        color: var(--ink);
        font-size: 14px;
    }

    label.check input { width: auto; }

    .table-wrap { overflow-x: auto; }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        padding: 8px 10px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        text-align: left;
        border-bottom: 1px solid var(--line-strong);
    }

    td {
        padding: 11px 10px;
        text-align: left;
        vertical-align: middle;
        border-bottom: 1px solid var(--line);
    }

    tbody tr:hover td { background: var(--wash); }

    .mono {
        font-family: var(--mono);
        font-size: 12.5px;
        font-variant-numeric: tabular-nums;
    }

    a.mono,
    a.title { color: inherit; text-decoration: none; }

    a.mono { color: var(--muted); }

    a.title { font-weight: 600; }

    a.mono:hover,
    a.title:hover,
    .pager a:hover,
    .jump a:hover,
    .inline-link:hover { text-decoration: underline; }

    .title-line {
        display: flex;
        gap: 8px;
        align-items: baseline;
        flex-wrap: wrap;
    }

    .flag {
        color: var(--flag);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .state { color: var(--ink-soft); white-space: nowrap; }

    .state::before {
        content: "";
        display: inline-block;
        width: 7px;
        height: 7px;
        margin-right: 6px;
        background: currentColor;
        vertical-align: 1px;
    }

    .state-new { color: var(--state-new); }
    .state-open,
    .state-pending { color: var(--state-pending); }
    .state-in_progress { color: var(--state-progress); }
    .state-resolved { color: var(--state-resolved); }
    .state-closed,
    .state-cancelled,
    .state-archived { color: var(--muted); }

    .priority-high { color: var(--flag); font-weight: 600; }
    .priority-critical { color: var(--danger); font-weight: 600; }

    .muted { color: var(--muted); }
    .error-inline { color: var(--danger); }

    .pager {
        display: flex;
        gap: 16px;
        margin-top: 14px;
    }

    .pager a,
    .inline-link,
    .jump a { color: var(--ink); }

    .ticket-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 292px;
        gap: 28px;
        align-items: start;
    }

    .ticket-no {
        margin: 0 0 4px;
        color: var(--muted);
    }

    .facts {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
        color: var(--ink-soft);
    }

    .brief {
        margin: 18px 0 4px;
        font-size: 15px;
        line-height: 1.55;
        white-space: pre-wrap;
    }

    .entry {
        padding: 12px 0;
        border-top: 1px solid var(--line);
    }

    .entry header {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: baseline;
        margin-bottom: 6px;
        color: var(--muted);
        font-size: 12px;
    }

    .entry .body { margin: 0; white-space: pre-wrap; }

    .entry.is-internal {
        margin: 8px 0;
        padding: 12px;
        background: var(--note);
        border-top: 0;
        border-left: 3px solid var(--brass);
    }

    .composer { margin-top: 22px; }

    .composer.is-internal {
        padding: 14px;
        background: var(--note);
        border: 1px solid var(--note-line);
    }

    .dock { border-left: 1px solid var(--line); padding-left: 20px; }

    .dock section + section {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid var(--line);
    }

    .catalog-layout,
    .split-section {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
        gap: 28px;
        align-items: start;
    }

    .catalog-layout { grid-template-columns: 240px minmax(0, 1fr); }

    .service-list,
    .plain { list-style: none; margin: 0; padding: 0; }

    .service-list a {
        display: block;
        padding: 10px 12px;
        color: inherit;
        text-decoration: none;
        border-left: 2px solid transparent;
    }

    .service-list a strong,
    .service-list a span { display: block; }

    .service-list a span { margin-top: 2px; color: var(--muted); font-size: 13px; }

    .service-list a:hover,
    .service-list a.is-current { background: var(--wash); }

    .service-list a.is-current { border-left-color: var(--brass); }

    .setup-section {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
        gap: 28px;
        align-items: start;
        padding: 26px 0;
        border-top: 1px solid var(--line);
    }

    .plain li {
        padding: 8px 0;
        border-bottom: 1px solid var(--line);
    }

    .plain li .muted,
    .issuer { display: block; margin-top: 2px; overflow-wrap: anywhere; }

    .group-block {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        align-items: center;
        margin-top: 8px;
    }

    .group-block button { margin: 0; }

    .jump {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin-top: 12px;
    }

    .jump a { font-size: 13px; text-decoration: none; }

    .theme-row {
        display: flex;
        justify-content: flex-end;
        margin: 0 0 18px;
    }

    .theme-toggle { white-space: nowrap; }

    .theme-toggle .when-dark { display: none; }

    html[data-theme="dark"] .theme-toggle .when-light { display: none; }

    html[data-theme="dark"] .theme-toggle .when-dark { display: inline; }

    @media (prefers-color-scheme: dark) {
        html:not([data-theme="light"]) .theme-toggle .when-light { display: none; }

        html:not([data-theme="light"]) .theme-toggle .when-dark { display: inline; }
    }

    .gate { min-height: 100vh; background: var(--paper); }

    .gate-split {
        min-height: 100vh;
        display: grid;
        grid-template-columns: minmax(260px, 38%) minmax(0, 1fr);
    }

    .gate-aside {
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: var(--rail);
        color: var(--rail-text);
        border-right: 1px solid var(--rail-edge);
        padding: 48px 40px;
    }

    .gate-aside .mark { padding: 0; }

    .gate-title {
        margin: 22px 0 12px;
        color: var(--mark);
        font-size: 34px;
        font-weight: 600;
        letter-spacing: -0.03em;
        line-height: 1.1;
        max-width: 12ch;
    }

    .gate-aside p { max-width: 34ch; font-size: 15px; }

    .gate-main {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px 32px;
    }

    .gate-panel { width: 100%; max-width: 380px; }

    .gate-panel h1 { margin-bottom: 8px; }

    .gate-panel .lede { margin-bottom: 22px; }

    .gate-panel .form-stack { max-width: none; }

    .gate-panel .inline-link { display: inline-block; margin-top: 16px; }

    @media (max-width: 860px) {
        .shell { align-content: start; }

        .shell,
        .gate-split,
        .ticket-layout,
        .catalog-layout,
        .split-section,
        .setup-section { grid-template-columns: 1fr; }

        .rail {
            position: static;
            height: auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                "brand foot"
                "work work"
                "team team"
                "admin admin";
            gap: 8px 12px;
            padding: 12px 12px 10px;
        }

        .rail > div:first-child { grid-area: brand; }

        .rail-work { grid-area: work; }

        .rail-team { grid-area: team; }

        .rail-admin { grid-area: admin; }

        .rail-nav {
            display: flex;
            flex-wrap: nowrap;
            gap: 2px;
            overflow-x: auto;
        }

        .rail-label { display: none; }

        .rail-foot {
            grid-area: foot;
            margin: 0;
            padding: 0;
            text-align: right;
        }

        .rail-foot form { margin-top: 6px; }

        .stage { padding: 16px 14px 40px; }

        .page-head { align-items: stretch; }

        .finder,
        .finder-row { width: 100%; }

        .finder-row input { flex: 1; width: auto; min-width: 0; }

        table { min-width: 680px; }

        .dock {
            border-left: 0;
            padding-left: 0;
            border-top: 1px solid var(--line);
            padding-top: 18px;
        }

        .form-stack input,
        .form-stack select,
        .form-stack textarea { width: 100%; }

        .gate-split { align-content: start; }

        .gate-aside {
            justify-content: flex-start;
            padding: 28px 20px;
            border-right: 0;
            border-bottom: 1px solid var(--rail-edge);
        }

        .gate-title { margin-top: 24px; font-size: 28px; }

        .gate-main { align-items: start; padding: 28px 20px 48px; }
    }
</style>
