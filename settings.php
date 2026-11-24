<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_slbridge', 'SLBridge (Simulator Learning Bridge)');

    $endpoint = $CFG->wwwroot . '/local/slbridge/api.php';

    $guide = '
        <div class="mb-3">
            <h4>Getting Started</h4>
            <p>Follow these steps to connect your Second Life or
            OpenSimulator kiosk to Moodle. You can begin with a
            simple cube while testing.</p>

            <p><strong>Second Life:</strong> Create or select an
            editable object and open its Contents tab.</p>

            <p><strong>OpenSimulator:</strong> Follow the same
            object setup process. Your grid must allow outbound
            HTTPS requests and support the script functions
            required by SLBridge.</p>

            <ol>
                <li><strong>Prepare your kiosk.</strong>
                Create a simple prim, information board, or other
                object you own and can edit. A special purchased
                kiosk is not required. Open the object in your
                viewer, select Edit, then Contents. The SLBridge
                kiosk script belongs in the Contents tab.
                </li>

                <li><strong>Find the object UUID.</strong>
                Copy the UUID of your kiosk object using your viewer.
                Do not use your avatar UUID.</li>

                <li><strong>Generate your API key.</strong>
                Use Generate API Key below, copy the key securely,
                and click Save changes. If a key already exists,
                use View Saved Key or Copy Key.</li>

                <li><strong>Configure the kiosk script.</strong>
                Enter the API Endpoint URL and saved API key in
                your kiosk configuration. Keep the key private.</li>

                <li><strong>Authorize your kiosk.</strong>
                Enter the object UUID in Authorized kiosk object UUID
                below, then click Save changes.</li>

                <li><strong>Test the connection.</strong>
                Visit the kiosk and use a valid avatar-linking code
                or active attendance session. Verify the result
                in Moodle.</li>

                <li><strong>Manage your API key when needed.</strong>
                View or copy your saved key, or use Replace API Key
                to generate a replacement. Confirm and save it,
                then update your kiosk configuration.</li>
            </ol>

            <p><strong>Security:</strong> Never publish your API key.
            Replacing it invalidates the previous key.</p>
        </div>
';

    // Use Moodle's native settings columns.


    // Kiosk Setup Wizard for the public SLBridge plugin.
    // The kiosk script is prepared only after administrator interaction.


$slbridgecompletionjs = <<<'SLBRIDGE_COMPLETION_JS'

(function() {
    function start() {
        const wizard = document.getElementById('slbridge-kiosk-wizard');
        const course = document.getElementById('slbridge-course');

        if (!wizard || !course) {
            return;
        }

        if (document.getElementById('slbridge-completion-catalog')) {
            return;
        }

        const panel = document.createElement('section');
        panel.id = 'slbridge-completion-catalog';
        panel.className = 'card p-3 mt-3 mb-3';

        const heading = document.createElement('h5');
        heading.textContent = 'Moodle Activities and Resources';

        const explanation = document.createElement('p');
        explanation.textContent =
            'Completion-enabled activities and resources in the ' +
            'selected Moodle course. Moodle retains control of ' +
            'completion conditions and course completion.';

        const message = document.createElement('p');
        message.textContent = 'Select a Moodle course to view its content.';

        const list = document.createElement('div');

        panel.append(heading, explanation, message, list);

        const session = document.getElementById('slbridge-session-id');

        if (session && session.parentElement) {
            session.parentElement.insertAdjacentElement(
                'afterend', panel
            );
        } else {
            wizard.appendChild(panel);
        }

        let generation = 0;

        async function refresh() {
            const current = ++generation;
            list.replaceChildren();

            if (!course.value) {
                message.textContent =
                    'Select a Moodle course to view its content.';
                return;
            }

            message.textContent = 'Loading course content...';

            try {
                const url = new URL(
                    window.location.origin +
                    '/local/slbridge/completion_catalog.php'
                );

                url.searchParams.set('courseid', course.value);
                url.searchParams.set('sesskey', M.cfg.sesskey);

                const response = await fetch(url, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (current !== generation) {
                    return;
                }

                if (!data.ok) {
                    throw new Error('Invalid response');
                }

                if (!data.completionenabled) {
                    message.textContent =
                        'Activity completion tracking is not enabled ' +
                        'for this course.';
                    return;
                }

                message.textContent = data.items.length
                    ? data.items.length +
                      ' completion-enabled activities and resources found.'
                    : 'No completion-enabled activities or resources found.';

                for (const item of data.items) {
                    const row = document.createElement('div');
                    row.className = 'py-2 border-bottom';

                    const name = document.createElement('strong');
                    name.textContent = item.name;

                    const details = document.createElement('div');
                    details.className = 'text-muted small';
                    details.textContent =
                        item.module + ' | ' + item.description;

                    row.append(name, details);
                    list.appendChild(row);
                }

            } catch (error) {
                if (current === generation) {
                    message.textContent =
                        'Unable to load course completion information.';
                }
            }
        }

        course.addEventListener('change', refresh);

        // The existing wizard populates courses asynchronously.
        // Wait for that population without modifying its handlers.
        const observer = new MutationObserver(function() {
            if (course.value) {
                observer.disconnect();
                refresh();
            }
        });

        observer.observe(course, {childList: true});

        if (course.value) {
            observer.disconnect();
            refresh();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
SLBRIDGE_COMPLETION_JS;
$PAGE->requires->js_init_code($slbridgecompletionjs);


$slbridgemultijs = <<<'SLBRIDGE_MULTI_JS'

(function() {
    'use strict';

    function init() {
        const wizard = document.getElementById('slbridge-kiosk-wizard');
        const course = document.getElementById('slbridge-course');
        const session = document.getElementById('slbridge-session-id');

        if (!wizard || !course || !session ||
            document.getElementById('slbridge-multi-picker')) {
            return;
        }

        const base = new URL(
            '/local/slbridge/',
            window.location.origin
        );

        const panel = document.createElement('section');
        panel.id = 'slbridge-multi-picker';
        panel.className = 'card p-3 my-3';

        const heading = document.createElement('h5');
        heading.textContent = 'Select Moodle Items';

        const help = document.createElement('p');
        help.textContent =
            'Choose one or more attendance sessions, activities, ' +
            'resources, or course completion items.';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className =
            'btn btn-outline-secondary w-100 text-start';
        toggle.textContent = 'Select Moodle items';
        toggle.setAttribute('aria-expanded', 'false');

        const menu = document.createElement('div');
        menu.className = 'border rounded p-3 mt-2';
        menu.hidden = true;
        menu.style.maxHeight = '340px';
        menu.style.overflowY = 'auto';

        const actions = document.createElement('div');
        actions.className = 'd-flex gap-2 mb-3';

        const all = document.createElement('button');
        all.type = 'button';
        all.className = 'btn btn-sm btn-outline-primary';
        all.textContent = 'Select All';

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn btn-sm btn-outline-secondary';
        clear.textContent = 'Clear Selection';

        actions.append(all, clear);

        const choices = document.createElement('div');

        const summary = document.createElement('p');
        summary.className = 'small mt-2';

        const note = document.createElement('p');
        note.className = 'small text-muted';
        note.textContent =
            'Selecting items does not override Moodle completion ' +
            'conditions. Multi-item recording is not yet enabled.';

        menu.append(actions, choices);
        panel.append(
            heading, help, toggle, menu, summary, note
        );

        // Insert directly after the Moodle Course selector.
        course.insertAdjacentElement('afterend', panel);

        // Keep the original attendance control available to
        // the existing script generator, but hide its UI.
        const oldLabel = wizard.querySelector(
            'label[for="slbridge-session-id"]'
        );

        if (oldLabel) {
            oldLabel.hidden = true;
            oldLabel.style.display = 'none';
        }

        session.hidden = true;
        session.style.display = 'none';

        // Hide the old attendance explanation.
        const helpText = session.nextElementSibling;

        if (helpText && helpText.classList.contains('form-text')) {
            helpText.hidden = true;
            helpText.style.display = 'none';
        }

        // Hide the previous read-only catalog.
        // Its endpoint remains available for this selector.
        function hideOldCatalog() {
            const old = document.getElementById(
                'slbridge-completion-catalog'
            );

            if (old) {
                old.hidden = true;
                old.style.display = 'none';
            }
        }

        hideOldCatalog();

        const catalogObserver = new MutationObserver(
            hideOldCatalog
        );

        catalogObserver.observe(wizard, {
            childList: true,
            subtree: true
        });

        const prepare = document.getElementById(
            'slbridge-prepare-script'
        );
        const copy = document.getElementById(
            'slbridge-copy-script'
        );
        const output = document.getElementById(
            'slbridge-script-output'
        );

        let available = [];
        const selected = new Map();
        let generation = 0;

        function invalidate() {
            if (output) output.value = '';
            if (copy) copy.disabled = true;
        }

        function updateSummary() {
            const values = Array.from(selected.values());

            toggle.textContent = values.length
                ? values.length + ' Moodle items selected'
                : 'Select Moodle items';

            summary.textContent = values.length
                ? values.map(item => item.label).join(', ')
                : 'No items selected';

            const attendance = values.filter(
                item => item.type === 'attendance'
            );

            session.value = attendance.length === 1
                ? String(attendance[0].id)
                : '';

            invalidate();
        }

        function render() {
            choices.replaceChildren();

            if (!available.length) {
                const message = document.createElement('p');
                message.textContent =
                    'No eligible items found in this course.';
                choices.appendChild(message);
                updateSummary();
                return;
            }

            const groups = [
                ['attendance', 'Attendance Sessions'],
                ['activity', 'Activities and Resources'],
                ['course', 'Course Completion']
            ];

            for (const [type, title] of groups) {
                const items = available.filter(
                    item => item.type === type
                );

                if (!items.length) continue;

                const h = document.createElement('div');
                h.className = 'fw-bold mt-2 mb-1';
                h.textContent = title;
                choices.appendChild(h);

                for (const item of items) {
                    const row = document.createElement('label');
                    row.className =
                        'd-flex align-items-start gap-2 py-1';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'form-check-input mt-1';
                    checkbox.checked = selected.has(item.key);

                    const text = document.createElement('span');
                    text.textContent = item.label;

                    checkbox.addEventListener('change', function() {
                        if (checkbox.checked) {
                            selected.set(item.key, item);
                        } else {
                            selected.delete(item.key);
                        }

                        updateSummary();
                    });

                    row.append(checkbox, text);
                    choices.appendChild(row);
                }
            }

            updateSummary();
        }

        async function fetchJSON(filename, courseid) {
            // Resolve against the plugin directory, not
            // the Moodle administration page URL.
            const url = new URL(filename, base);

            url.searchParams.set('courseid', courseid);
            url.searchParams.set('sesskey', M.cfg.sesskey);

            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error(
                    filename + ': HTTP ' + response.status
                );
            }

            const data = await response.json();

            if (!data.ok) {
                throw new Error(filename + ': invalid response');
            }

            return data;
        }

        async function load() {
            const request = ++generation;

            selected.clear();
            available = [];
            render();
            invalidate();

            if (!course.value) {
                summary.textContent =
                    'Select a Moodle course first.';
                return;
            }

            toggle.textContent = 'Loading Moodle items...';

            const courseid = course.value;

            // Load independently so an unavailable attendance
            // endpoint does not hide working activity results.
            const results = await Promise.allSettled([
                fetchJSON('kiosk_options.php', courseid),
                fetchJSON('completion_catalog.php', courseid)
            ]);

            if (request !== generation) return;

            const items = [];
            const errors = [];

            if (results[0].status === 'fulfilled') {
                for (const s of results[0].value.sessions || []) {
                    items.push({
                        key: 'attendance:' + s.id,
                        type: 'attendance',
                        id: s.id,
                        label: s.label +
                            (s.active ? ' (Active)' : '')
                    });
                }
            } else {
                errors.push(
                    'Attendance sessions could not be loaded.'
                );
            }

            if (results[1].status === 'fulfilled') {
                const catalog = results[1].value;

                for (const a of catalog.items || []) {
                    items.push({
                        key: 'activity:' + a.cmid,
                        type: 'activity',
                        id: a.cmid,
                        label: a.module + ': ' + a.name +
                            ' (' + a.description + ')'
                    });
                }

                if (catalog.completionenabled) {
                    items.push({
                        key: 'course:' + courseid,
                        type: 'course',
                        id: courseid,
                        label: 'Course completion'
                    });
                }
            } else {
                errors.push(
                    'Activities and resources could not be loaded.'
                );
            }

            available = items;
            render();

            if (errors.length) {
                summary.textContent += ' ' + errors.join(' ');
            }
        }

        toggle.addEventListener('click', function() {
            menu.hidden = !menu.hidden;

            toggle.setAttribute(
                'aria-expanded',
                String(!menu.hidden)
            );
        });

        all.addEventListener('click', function() {
            for (const item of available) {
                selected.set(item.key, item);
            }

            render();
        });

        clear.addEventListener('click', function() {
            selected.clear();
            render();
        });

        course.addEventListener('change', load);

        // Keep existing kiosk behavior safe until the API
        // supports recording multiple selected items.
        if (prepare) {
            prepare.addEventListener('click', function(event) {
                const values = Array.from(selected.values());

                const supported =
                    values.length === 0 ||
                    (values.length === 1 &&
                     values[0].type === 'attendance');

                if (!supported) {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    invalidate();

                    summary.textContent =
                        'Multiple Moodle items can be selected, ' +
                        'but recording them requires the next ' +
                        'SLBridge API implementation.';
                }
            }, true);
        }

        if (course.value) {
            load();
        } else {
            summary.textContent =
                'Select a Moodle course first.';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
SLBRIDGE_MULTI_JS;
$PAGE->requires->js_init_code($slbridgemultijs);

$slbridgewizard = <<<'HTML'
<div id="slbridge-kiosk-wizard" class="card mt-4 mb-4">
  <div class="card-body">
    <h4>Kiosk Setup Wizard</h4>
    <p>Prepare your kiosk script without downloading or editing a file.</p>

    <div class="mb-3">
      <label for="slbridge-platform" class="form-label">
        Virtual world
      </label>
      <select id="slbridge-platform" class="form-select">
        <option value="secondlife">Second Life</option>
        <option value="opensim">OpenSimulator</option>
      </select>
    </div>

    <div class="mb-3">
      <label for="slbridge-course" class="form-label">
        Moodle Course
      </label>
      <select id="slbridge-course" class="form-select mb-3">
        <option value="">Loading courses...</option>
      </select>

      <label for="slbridge-session-id" class="form-label">
        Attendance Session
      </label>
      <select id="slbridge-session-id" class="form-select">
        <option value="">Select a course first</option>
      </select>

      <div class="form-text">
        Choose a course and its attendance session.
        For avatar linking only, leave the session unselected.
      </div>
    </div>

    <div class="mb-3">
      <label class="d-flex align-items-start"
             style="gap:8px">
        <input type="checkbox"
               id="slbridge-include-key"
               style="margin-top:5px;flex-shrink:0">
        <span>
          Include my saved API key in the copied script.
          The script will contain a private credential.
          Only use this option for an object you control.
        </span>
      </label>
    </div>

    <div class="d-flex flex-wrap mb-3" style="gap:8px">
      <button type="button"
              id="slbridge-prepare-script"
              class="btn btn-primary">
        Prepare Kiosk Script
      </button>

      <button type="button"
              id="slbridge-copy-script"
              class="btn btn-secondary"
              disabled>
        Copy Kiosk Script
      </button>
    </div>

    <div id="slbridge-wizard-status"
         role="status"
         aria-live="polite"
         class="mb-3"></div>

    <div id="slbridge-script-preview" style="display:none">
      <label for="slbridge-script-output" class="form-label">
        Prepared LSL script
      </label>
      <textarea id="slbridge-script-output"
                class="form-control font-monospace"
                rows="12"
                readonly
                spellcheck="false"></textarea>
    </div>

    <div class="alert alert-info mt-3 mb-3">
      <strong>Next step:</strong>
      Copy the prepared script into a new script in the
      Content tab of your kiosk object.
      Follow Getting Started above to authorize the kiosk
      and verify the connection.
    </div>

    <p class="form-text">
      OpenSimulator requires a compatible scripting engine and
      permission for outbound HTTPS requests.
      Platform compatibility must be verified on the target grid.
    </p>
  </div>
</div>
HTML;

    $guide .= $slbridgewizard;

    $slbridgetemplate = file_get_contents(
        __DIR__ . '/kiosk/slbridge_kiosk.lsl'
    );

    $slbridgeconfig = json_encode([
        'template' => $slbridgetemplate,
        'savedplatform' => get_config('local_slbridge', 'kioskplatform') ?: 'secondlife',
        'endpoint' => $CFG->wwwroot . '/local/slbridge/api.php',
        'lookup' => (new moodle_url(
            '/local/slbridge/kiosk_options.php',
            ['sesskey' => sesskey()]
        ))->out(false),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $PAGE->requires->js_init_code(<<<'JS'
(function(config) {
    function init() {
        const el = id => document.getElementById(id);
        const prepare = el('slbridge-prepare-script');
        const copy = el('slbridge-copy-script');
        const output = el('slbridge-script-output');
        const preview = el('slbridge-script-preview');
        const status = el('slbridge-wizard-status');
        const includeKey = el('slbridge-include-key');
        const session = el('slbridge-session-id');
        const course = el('slbridge-course');
        const platform = el('slbridge-platform');

        if (platform && ['secondlife', 'opensim'].includes(config.savedplatform)) {
            platform.value = config.savedplatform;
        }

        if (!prepare || !copy || !output || !status) return;

        function getSavedKey() {
            const field = el('slbridge-key-visible');
            if (!field) return '';

            // Only use the existing saved key.
            // A newly generated unsaved replacement is not accepted.
            const hidden = document.querySelector(
                'input[name="s_local_slbridge_apikey"]'
            );

            if (hidden && hidden.value) return '';

            return /^[a-f0-9]{64}$/i.test(field.value)
                ? field.value : '';
        }


        function resetPreparedScript() {
            output.value = '';
            preview.style.display = 'none';
            copy.disabled = true;
        }

        function setOptions(select, rows, placeholder, labelFn) {
            select.replaceChildren();

            const first = document.createElement('option');
            first.value = '';
            first.textContent = placeholder;
            select.appendChild(first);

            for (const row of rows) {
                const option = document.createElement('option');
                option.value = String(row.id);
                option.textContent = labelFn(row);
                select.appendChild(option);
            }
        }

        async function loadOptions(courseId) {
            const url = new URL(config.lookup, window.location.href);

            if (courseId) {
                url.searchParams.set('courseid', courseId);
            }

            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('Moodle lookup failed');
            }

            const data = await response.json();

            if (!data.ok) {
                throw new Error('Invalid Moodle response');
            }

            return data;
        }

        async function loadCourses() {
            try {
                const data = await loadOptions(null);

                setOptions(
                    course,
                    data.courses,
                    'Select a Moodle course',
                    row => row.name
                );

                if (!data.courses.length) {
                    status.textContent =
                        'No SLBridge attendance sessions exist yet. ' +
                        'You can still prepare a kiosk for avatar linking.';
                }
            } catch (error) {
                setOptions(course, [], 'Unable to load courses', row => row.name);
                status.textContent =
                    'Could not load Moodle courses. Refresh the page.';
            }
        }

        course.addEventListener('change', async function() {
            resetPreparedScript();

            setOptions(
                session, [],
                course.value ? 'Loading sessions...' : 'Select a course first',
                row => row.label
            );

            if (!course.value) return;

            const requestedCourse = course.value;

            try {
                const data = await loadOptions(requestedCourse);

                if (course.value !== requestedCourse) return;

                setOptions(
                    session,
                    data.sessions,
                    'Avatar linking only (no attendance session)',
                    row => row.label + (row.active ? ' (Active)' : '')
                );

                if (!data.sessions.length) {
                    status.textContent =
                        'This course has no attendance sessions.';
                }
            } catch (error) {
                setOptions(session, [], 'Unable to load sessions', row => row.label);
                status.textContent =
                    'Could not load attendance sessions.';
            }
        });

        loadCourses();

        prepare.addEventListener('click', function() {
            const id = session.value === ''
                ? 0 : Number(session.value);

            if (!Number.isSafeInteger(id) || id < 0) {
                status.textContent =
                    'Enter a valid nonnegative session ID.';
                return;
            }

            if (session.value !== '' && !course.value) {
                status.textContent = 'Select a Moodle course first.';
                return;
            }

            let script = config.template;
            const courseId = Number(course.value);
            if (!Number.isSafeInteger(courseId) || courseId <= 1) {
                status.textContent = 'Select a Moodle course first.';
                return;
            }
            const courseUrl = new URL(
                '/course/view.php?id=' + courseId,
                window.location.origin
            ).toString();
            script = script.replace(
                /string COURSE_URL = "[^"]*";/,
                'string COURSE_URL = "' + courseUrl + '";'
            ); // SLBRIDGE_COURSE_URL_INSERT

            script = script.replace(
                'https://YOUR-MOODLE-SITE.example/local/slbridge/api.php',
                config.endpoint
            );

            const key = includeKey.checked ? getSavedKey() : '';

            if (!includeKey.checked) {
                status.textContent =
                    'Select the option to include your saved API key ' +
                    'before preparing a ready-to-use kiosk script. ' +
                    'Only do this for an object you own and control.';
                resetPreparedScript();
                return;
            }

            if (!key) {
                status.textContent =
                    'A valid saved API key is required. ' +
                    'Save your key in Moodle first.';
                resetPreparedScript();
                return;
            }

            script = script.replace(
                'PASTE_YOUR_GENERATED_API_KEY_HERE',
                key || 'PASTE_YOUR_GENERATED_API_KEY_HERE'
            );

            script = script.replace(
                /(\binteger\s+SESSION_ID\s*=\s*)0\s*;/,
                (match, prefix) => prefix + String(id) + ';'
            );

            output.value = script;
            preview.style.display = 'block';
            copy.disabled = false;

            status.textContent =
                'Script prepared for ' +
                (platform.value === 'opensim'
                    ? 'OpenSimulator' : 'Second Life') +
                '. Review it, then click Copy Kiosk Script.' +
                (key
                    ? ' Warning: the script contains your private API key.'
                    : ' Add your API key privately before activating the kiosk.');
        });

        copy.addEventListener('click', async function() {
            if (!output.value) return;

            try {
                await navigator.clipboard.writeText(output.value);
                status.textContent =
                    'Script copied. Paste it into a new script ' +
                    'in the Contents tab of your kiosk object.';
            } catch (error) {
                output.focus();
                output.select();
                status.textContent =
                    'Script selected. Use Command+C or Ctrl+C to copy.';
            }
        });

        for (const control of [course, session, platform, includeKey]) {
            control.addEventListener('change', function() {
                output.value = '';
                preview.style.display = 'none';
                copy.disabled = true;
                status.textContent =
                    'Configuration changed. Prepare the script again.';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})
JS
    . '(' . $slbridgeconfig . ');');

    $settings->add(new admin_setting_description(
        'local_slbridge/gettingstarted',
        '',
        $guide
    ));

    $settings->add(new admin_setting_description(
        'local_slbridge/apiendpoint',
        'API Endpoint URL',
        html_writer::tag('code', s($endpoint))
    ));

    $settings->add(new \local_slbridge\admin\kiosk_secret_setting(
        'local_slbridge/apikey',
        'API Key',
        'Generate, view, copy, or replace the secure kiosk API key.',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_slbridge/objectuuid',
        'Authorized kiosk object UUID',
        'Enter the UUID of the trusted Second Life kiosk object.',
        ''
    ));


    $settings->add(new admin_setting_configselect(
        'local_slbridge/kioskplatform',
        'Authorized kiosk platform',
        'Select the virtual world containing the authorized kiosk object.',
        'secondlife',
        [
            'secondlife' => 'Second Life',
            'opensim' => 'OpenSimulator'
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_slbridge/kioskgrid',
        'Authorized kiosk grid',
        'For Second Life use agni. For OpenSimulator enter the grid hostname and optional port, for example grid.example.org:8002.',
        'agni',
        PARAM_TEXT
    ));

    $PAGE->requires->css(new moodle_url('/local/slbridge/admin_guide.css'));

    $ADMIN->add('localplugins', $settings);
}
