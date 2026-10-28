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
        --ink: #2f3941;
        --ink-soft: #49545c;
        --muted: #68737d;
        --line: #e4e8ec;
        --line-strong: #d0d7de;
        --paper: #f3f6f8;
        --sheet: #ffffff;
        --rail: #ffffff;
        --rail-text: #2f3941;
        --rail-faint: #68737d;
        --rail-edge: #e4e8ec;
        --rail-current: #e7f6ef;
        --rail-line: #d0d7de;
        --rail-line-hover: #2f3941;
        --mark: #12344d;
        --accent: #038153;
        --accent-ink: #ffffff;
        --brass: #038153;
        --wash: #f3f6f8;
        --note: #fff8e1;
        --note-line: #f0d98a;
        --note-ink: #8a5a12;
        --ok: #0b6e4f;
        --ok-wash: #e8f6ef;
        --ok-line: #038153;
        --danger: #cc3340;
        --danger-ink: #8f1d28;
        --danger-wash: #fdecec;
        --button-hover: #026b45;
        --placeholder: #87929d;
        --selection: #d6efe4;
        --flag: #cc3340;
        --link: #1f73b7;
        --shadow: 0 1px 2px rgba(18, 52, 77, 0.06);
        --state-new: #1f73b7;
        --state-pending: #ad5918;
        --state-progress: #038153;
        --state-resolved: #0b6e4f;
        --mono: ui-monospace, "Cascadia Mono", "Segoe UI Mono", "Liberation Mono", Menlo, Consolas, monospace;
        --sans: "Segoe UI", system-ui, -apple-system, "Helvetica Neue", Helvetica, Arial, sans-serif;
        color-scheme: light;
    }

    html[data-theme="dark"] {
        --ink: #e8ecef;
        --ink-soft: #c9d1d6;
        --muted: #9aa7b2;
        --line: #343b42;
        --line-strong: #465058;
        --paper: #1b1f23;
        --sheet: #24292e;
        --rail: #161a1e;
        --rail-text: #e8ecef;
        --rail-faint: #9aa7b2;
        --rail-edge: #343b42;
        --rail-current: #1d3a2e;
        --rail-line: #465058;
        --rail-line-hover: #e8ecef;
        --mark: #f4f7f8;
        --accent: #1f9d6a;
        --accent-ink: #ffffff;
        --brass: #1f9d6a;
        --wash: #2c3338;
        --note: #3a3420;
        --note-line: #6b5a32;
        --note-ink: #f0d98a;
        --ok: #b7ebc9;
        --ok-wash: #1a2c24;
        --ok-line: #1f9d6a;
        --danger: #f3b7be;
        --danger-ink: #f3b7be;
        --danger-wash: #3a2226;
        --button-hover: #17885a;
        --placeholder: #8b98a3;
        --selection: #1d3a2e;
        --flag: #f3b7be;
        --link: #8eb8e8;
        --shadow: none;
        --state-new: #8eb8e8;
        --state-pending: #f0c27a;
        --state-progress: #8fd4b0;
        --state-resolved: #8fd4b0;
        color-scheme: dark;
    }

    @media (prefers-color-scheme: dark) {
        html:not([data-theme="light"]) {
            --ink: #e8ecef;
            --ink-soft: #c9d1d6;
            --muted: #9aa7b2;
            --line: #343b42;
            --line-strong: #465058;
            --paper: #1b1f23;
            --sheet: #24292e;
            --rail: #161a1e;
            --rail-text: #e8ecef;
            --rail-faint: #9aa7b2;
            --rail-edge: #343b42;
            --rail-current: #1d3a2e;
            --rail-line: #465058;
            --rail-line-hover: #e8ecef;
            --mark: #f4f7f8;
            --accent: #1f9d6a;
            --accent-ink: #ffffff;
            --brass: #1f9d6a;
            --wash: #2c3338;
            --note: #3a3420;
            --note-line: #6b5a32;
            --note-ink: #f0d98a;
            --ok: #b7ebc9;
            --ok-wash: #1a2c24;
            --ok-line: #1f9d6a;
            --danger: #f3b7be;
            --danger-ink: #f3b7be;
            --danger-wash: #3a2226;
            --button-hover: #17885a;
            --placeholder: #8b98a3;
            --selection: #1d3a2e;
            --flag: #f3b7be;
            --link: #8eb8e8;
            --shadow: none;
            --state-new: #8eb8e8;
            --state-pending: #f0c27a;
            --state-progress: #8fd4b0;
            --state-resolved: #8fd4b0;
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
        outline: 2px solid var(--link);
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
        grid-template-columns: 232px minmax(0, 1fr);
    }

    .rail {
        background: var(--rail);
        color: var(--rail-text);
        display: flex;
        flex-direction: column;
        gap: 18px;
        padding: 16px 12px 14px;
        position: sticky;
        top: 0;
        height: 100vh;
        border-right: 1px solid var(--rail-edge);
    }

    .mark {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--mark);
        text-decoration: none;
        font-weight: 700;
        font-size: 16px;
        letter-spacing: -0.02em;
        padding: 4px 8px;
    }

    .mark::before {
        content: "";
        width: 18px;
        height: 18px;
        border-radius: 4px;
        background: var(--accent);
        flex: none;
    }

    .rail-kicker {
        margin: 0;
        padding: 0 8px;
        color: var(--rail-faint);
        font-size: 12px;
    }

    .rail-nav { display: grid; gap: 2px; }

    .rail-label {
        margin: 10px 8px 4px;
        color: var(--rail-faint);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .rail-nav a {
        color: var(--rail-text);
        text-decoration: none;
        padding: 7px 10px;
        border-radius: 6px;
        border-left: 0;
        white-space: nowrap;
        font-weight: 600;
    }

    .rail-team a { white-space: normal; line-height: 1.3; font-weight: 500; }

    .rail-nav a:hover { background: var(--wash); }

    .rail-nav a.is-current {
        color: var(--accent);
        background: var(--rail-current);
    }

    .rail a:focus-visible,
    .rail button:focus-visible { outline-color: var(--link); }

    .rail-foot { margin-top: auto; padding: 8px 4px 0; border-top: 1px solid var(--rail-edge); }

    .rail-person {
        display: flex;
        gap: 8px;
        align-items: center;
        padding: 10px 4px 0;
    }

    .who,
    .who-mail { margin: 0; }

    .who { color: var(--mark); font-weight: 700; font-size: 13px; }

    .who-mail {
        color: var(--rail-faint);
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .rail-foot form { margin-top: 8px; }

    .rail button {
        background: transparent;
        color: var(--rail-text);
        border-color: var(--rail-line);
        margin: 0;
        padding: 5px 10px;
    }

    .rail button:hover {
        color: var(--mark);
        background: var(--wash);
        border-color: var(--rail-line-hover);
    }

    .rail .theme-toggle { margin-top: 8px; }

    .stage {
        padding: 20px 28px 64px;
        min-width: 0;
    }

    .page-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px 28px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .kicker {
        margin: 0 0 4px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    h1 {
        margin: 0;
        font-size: 22px;
        line-height: 1.2;
        font-weight: 650;
        letter-spacing: -0.02em;
    }

    h2 {
        margin: 0 0 10px;
        font-size: 14px;
        font-weight: 700;
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
        padding: 10px 12px;
        background: var(--ok-wash);
        color: var(--ok);
        border: 1px solid var(--ok-line);
        border-radius: 6px;
    }

    .flash.is-error {
        background: var(--danger-wash);
        color: var(--danger-ink);
        border-color: var(--danger);
    }

    .flash ul { margin: 0; padding-left: 18px; }

    .finder { display: grid; gap: 4px; justify-items: start; }

    .finder label,
    .form-stack > label {
        display: grid;
        gap: 4px;
        color: var(--ink-soft);
        font-size: 12px;
        font-weight: 700;
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

    .finder input { width: 280px; }

    input,
    select,
    textarea {
        width: 100%;
        padding: 8px 10px;
        color: var(--ink);
        background: var(--sheet);
        border: 1px solid var(--line-strong);
        border-radius: 4px;
        font: inherit;
    }

    textarea { min-height: 96px; resize: vertical; }

    ::placeholder { color: var(--placeholder); }

    button,
    a.button {
        appearance: none;
        display: inline-block;
        margin: 0;
        padding: 8px 14px;
        background: var(--accent);
        color: var(--accent-ink);
        border: 1px solid var(--accent);
        border-radius: 4px;
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.3;
        text-decoration: none;
        cursor: pointer;
    }

    button:hover,
    a.button:hover { background: var(--button-hover); border-color: var(--button-hover); }

    button.quiet,
    a.button.quiet {
        background: var(--sheet);
        color: var(--ink);
        border-color: var(--line-strong);
        font-weight: 600;
    }

    button.quiet:hover,
    a.button.quiet:hover { background: var(--wash); border-color: var(--line-strong); }

    button.danger {
        background: var(--sheet);
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
        font-weight: 500;
    }

    label.check input { width: auto; }

    .table-wrap {
        overflow-x: auto;
        background: var(--sheet);
        border: 1px solid var(--line);
        border-radius: 8px;
        box-shadow: var(--shadow);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        padding: 10px 14px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        text-align: left;
        background: var(--wash);
        border-bottom: 1px solid var(--line);
    }

    td {
        padding: 12px 14px;
        text-align: left;
        vertical-align: middle;
        border-bottom: 1px solid var(--line);
    }

    tbody tr:last-child td { border-bottom: 0; }

    tbody tr:hover td { background: #f7fafc; }

    html[data-theme="dark"] tbody tr:hover td { background: var(--wash); }

    @media (prefers-color-scheme: dark) {
        html:not([data-theme="light"]) tbody tr:hover td { background: var(--wash); }
    }

    .mono {
        font-family: var(--mono);
        font-size: 12px;
        font-variant-numeric: tabular-nums;
    }

    a.mono,
    a.title { text-decoration: none; }

    a.mono { color: var(--muted); }

    a.title { color: var(--link); font-weight: 650; }

    a.mono:hover,
    a.title:hover,
    .pager a:hover,
    .jump a:hover,
    .inline-link:hover { text-decoration: underline; }

    .title-line {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .flag {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 4px;
        background: var(--danger-wash);
        color: var(--flag);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .state {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--wash);
        color: var(--ink-soft);
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .state-new { background: #e7f1fb; color: var(--state-new); }
    .state-open { background: #fdecec; color: var(--danger); }
    .state-pending { background: #fff3d6; color: var(--state-pending); }
    .state-in_progress { background: #e7f6ef; color: var(--state-progress); }
    .state-resolved { background: #e7f6ef; color: var(--state-resolved); }
    .state-closed,
    .state-cancelled,
    .state-archived { background: var(--wash); color: var(--muted); }

    html[data-theme="dark"] .state-new,
    html[data-theme="dark"] .state-open,
    html[data-theme="dark"] .state-pending,
    html[data-theme="dark"] .state-in_progress,
    html[data-theme="dark"] .state-resolved { background: var(--wash); }

    @media (prefers-color-scheme: dark) {
        html:not([data-theme="light"]) .state-new,
        html:not([data-theme="light"]) .state-open,
        html:not([data-theme="light"]) .state-pending,
        html:not([data-theme="light"]) .state-in_progress,
        html:not([data-theme="light"]) .state-resolved { background: var(--wash); }
    }

    .priority-high { color: var(--flag); font-weight: 700; }
    .priority-critical { color: var(--danger); font-weight: 700; }

    .muted { color: var(--muted); }
    .error-inline { color: var(--danger); }

    .pager {
        display: flex;
        gap: 16px;
        margin-top: 14px;
    }

    .pager a,
    .inline-link,
    .jump a,
    .files a { color: var(--link); }

    .ticket-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 20px;
        align-items: start;
    }

    .ticket-no {
        margin: 0 0 2px;
        color: var(--muted);
        font-size: 12px;
    }

    .facts {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        align-items: center;
        margin: 10px 0 0;
        padding: 0;
        list-style: none;
        color: var(--ink-soft);
    }

    .brief,
    .entry .body { margin: 0; white-space: pre-wrap; line-height: 1.5; }

    .entry {
        margin-top: 12px;
        padding: 14px 16px;
        background: var(--sheet);
        border: 1px solid var(--line);
        border-radius: 8px;
        box-shadow: var(--shadow);
    }

    .entry header {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
    }

    .entry header time {
        margin-left: auto;
        color: var(--muted);
        font-size: 12px;
        white-space: nowrap;
    }

    .entry-who { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; min-width: 0; }

    .entry-who strong { font-size: 13px; }

    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #d6e6f5;
        color: var(--link);
        font-size: 12px;
        font-weight: 700;
        flex: none;
    }

    .channel {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 4px;
        background: var(--wash);
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
    }

    .entry .body.is-rich { white-space: normal; }

    .entry .body.is-rich img,
    .editor .sheet img { max-width: 100%; height: auto; display: block; margin-top: 8px; border-radius: 4px; }

    .files {
        list-style: none;
        margin: 10px 0 0;
        padding: 0;
    }

    .files a { font-weight: 650; }

    .entry.is-internal {
        background: var(--note);
        border-color: var(--note-line);
    }

    .entry.is-internal .avatar { background: #f3e2a8; color: var(--note-ink); }

    .entry.is-internal .channel { background: #f3e2a8; color: var(--note-ink); }

    .composer {
        margin-top: 16px;
        padding: 14px 16px 16px;
        background: var(--sheet);
        border: 1px solid var(--line);
        border-radius: 8px;
        box-shadow: var(--shadow);
    }

    .composer.is-internal {
        background: var(--note);
        border-color: var(--note-line);
    }

    .composer.is-internal button.quiet[type="submit"] {
        background: #ad5918;
        color: #ffffff;
        border-color: #ad5918;
    }

    .composer.is-internal button.quiet[type="submit"]:hover { background: #8d4712; border-color: #8d4712; }

    .editor .toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 8px;
    }

    .editor .toolbar button { padding: 4px 8px; font-size: 12px; }

    .editor .sheet {
        min-height: 120px;
        padding: 8px 10px;
        color: var(--ink);
        background: var(--sheet);
        border: 1px solid var(--line-strong);
        border-radius: 4px;
        line-height: 1.5;
    }

    .editor .sheet:focus { outline: 2px solid var(--link); outline-offset: 1px; }

    .editor .fallback[hidden],
    .editor .sheet[hidden],
    .editor input[hidden] { display: none; }

    .editor .attach { margin-top: 8px; }

    .dock {
        position: sticky;
        top: 16px;
        padding: 16px;
        background: var(--sheet);
        border: 1px solid var(--line);
        border-radius: 8px;
        box-shadow: var(--shadow);
    }

    .dock section + section {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--line);
    }

    .catalog-layout,
    .split-section {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
        gap: 20px;
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
        border-radius: 6px;
    }

    .service-list a strong,
    .service-list a span { display: block; }

    .service-list a span { margin-top: 2px; color: var(--muted); font-size: 13px; }

    .service-list a:hover,
    .service-list a.is-current { background: var(--wash); }

    .service-list a.is-current { box-shadow: inset 3px 0 0 var(--accent); }

    .setup-section {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 420px);
        gap: 20px;
        align-items: start;
        margin-top: 16px;
        padding: 18px;
        background: var(--sheet);
        border: 1px solid var(--line);
        border-radius: 8px;
        box-shadow: var(--shadow);
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
        grid-template-columns: minmax(280px, 42%) minmax(0, 1fr);
    }

    .gate-aside {
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: #12344d;
        color: #d5e2ea;
        padding: 48px 40px;
    }

    .gate-aside .mark { color: #ffffff; padding: 0; }

    .gate-title {
        margin: 28px 0 12px;
        color: #ffffff;
        font-size: 34px;
        font-weight: 700;
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
        background: var(--sheet);
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
            gap: 4px;
            overflow-x: auto;
        }

        .rail-label { display: none; }

        .rail-foot {
            grid-area: foot;
            margin: 0;
            padding: 0;
            border-top: 0;
            text-align: right;
        }

        .rail-person { display: none; }

        .rail-foot form { margin-top: 6px; }

        .stage { padding: 16px 14px 40px; }

        .page-head { align-items: stretch; }

        .finder,
        .finder-row { width: 100%; }

        .finder-row input { flex: 1; width: auto; min-width: 0; }

        table { min-width: 680px; }

        .dock {
            position: static;
        }

        .form-stack input,
        .form-stack select,
        .form-stack textarea { width: 100%; }

        .gate-split { align-content: start; }

        .gate-aside {
            justify-content: flex-start;
            padding: 28px 20px;
        }

        .gate-title { margin-top: 18px; font-size: 28px; }

        .gate-main { align-items: start; padding: 28px 20px 48px; }
    }
</style>
