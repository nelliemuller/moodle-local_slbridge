<?php
// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.

namespace local_slbridge\admin;

defined('MOODLE_INTERNAL') || die();

class kiosk_secret_setting extends \admin_setting_configpasswordunmask {

    private function validkey(string $key): bool {
        return (bool)preg_match('/^[a-f0-9]{64}$/i', $key);
    }

    public function write_setting($data) {
        $existing = (string)get_config('local_slbridge', 'apikey');
        $new = trim((string)$data);

        // An empty submission never clears a saved key.
        if ($new === '' || hash_equals($existing, $new)) {
            return '';
        }

        if (!$this->validkey($new)) {
            return 'Please generate a valid 64-character API key.';
        }

        if ($this->validkey($existing) &&
                optional_param('slbridge_confirm_replace', 0, PARAM_BOOL) !== 1) {
            return 'Confirm replacement of the saved API key.';
        }

        return parent::write_setting($new);
    }

    public function output_html($data, $query = '') {
        global $PAGE;

        $existing = (string)get_config('local_slbridge', 'apikey');
        $saved = $this->validkey($existing);
        $readonly = $this->is_readonly();

        $id = $this->get_id();
        $name = $this->get_full_name();

        $html = \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'id' => $id,
            'name' => $name,
            'value' => '',
        ]);

        if ($saved) {
            $html .= \html_writer::div(
                'An API key is saved. View or copy it, or replace it when you choose.',
                'alert alert-info'
            );
        } else {
            $html .= \html_writer::div(
                'No valid API key is configured. Generate your first API key.',
                'alert alert-warning'
            );
        }

        $html .= \html_writer::empty_tag('input', [
            'id' => 'slbridge-key-visible',
            'type' => 'password',
            'class' => 'form-control mb-2',
            'value' => $saved ? $existing : '',
            'readonly' => 'readonly',
            'autocomplete' => 'off',
            'aria-label' => 'API key',
        ]);

        if (!$readonly) {
            $html .= \html_writer::start_div(
                'd-flex flex-wrap align-items-center mb-2',
                ['style' => 'gap:8px']
            );

            if ($saved) {
                $html .= \html_writer::tag(
                    'button',
                    'View Saved Key',
                    [
                        'type' => 'button',
                        'id' => 'slbridge-view-key',
                        'class' => 'btn btn-secondary',
                    ]
                );

                $html .= \html_writer::tag(
                    'button',
                    'Replace API Key',
                    [
                        'type' => 'button',
                        'id' => 'slbridge-start-replace',
                        'class' => 'btn btn-primary',
                    ]
                );
            } else {
                $html .= \html_writer::tag(
                    'button',
                    'Generate API Key',
                    [
                        'type' => 'button',
                        'id' => 'slbridge-generate',
                        'class' => 'btn btn-primary',
                    ]
                );
            }

            $html .= \html_writer::tag(
                'button',
                'Copy Key',
                [
                    'type' => 'button',
                    'id' => 'slbridge-copy',
                    'class' => 'btn btn-secondary',
                    'style' => 'display:none',
                ]
            );

            $html .= \html_writer::tag(
                'button',
                'Clear Generated Key',
                [
                    'type' => 'button',
                    'id' => 'slbridge-clear',
                    'class' => 'btn btn-secondary',
                    'style' => 'display:none',
                ]
            );

            $html .= \html_writer::end_div();

            if ($saved) {
                $html .= \html_writer::start_div(
                    'mt-3',
                    [
                        'id' => 'slbridge-replace-panel',
                        'style' => 'display:none',
                    ]
                );

                $html .= \html_writer::start_tag(
                    'label',
                    [
                        'for' => 'slbridge-confirm-replace',
                        'class' => 'd-flex align-items-start mb-2',
                        'style' => 'gap:8px',
                    ]
                );

                $html .= \html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'id' => 'slbridge-confirm-replace',
                    'name' => 'slbridge_confirm_replace',
                    'value' => '1',
                    'style' => 'margin-top:5px;flex-shrink:0',
                ]);

                $html .= \html_writer::span(
                    'I understand that replacing this key will disconnect kiosks using the previous key.'
                );

                $html .= \html_writer::end_tag('label');

                $html .= \html_writer::tag(
                    'button',
                    'Generate Replacement Key',
                    [
                        'type' => 'button',
                        'id' => 'slbridge-generate',
                        'class' => 'btn btn-primary',
                    ]
                );

                $html .= ' ' . \html_writer::tag(
                    'button',
                    'Cancel Replacement',
                    [
                        'type' => 'button',
                        'id' => 'slbridge-cancel',
                        'class' => 'btn btn-secondary',
                    ]
                );

                $html .= \html_writer::end_div();
            }
        }

        $html .= \html_writer::div(
            '',
            'mt-2',
            [
                'id' => 'slbridge-key-status',
                'role' => 'status',
                'aria-live' => 'polite',
            ]
        );

        $html .= \html_writer::div(
            'Generate and copy a new key before clicking Save changes. '
            . 'A saved key is never replaced merely by viewing this page.',
            'form-text mt-2'
        );

        if (!$readonly) {
            $config = json_encode([
                'fieldid' => $id,
                'saved' => $saved,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            $javascript = <<<'JS'
(function(config) {
    function initialize() {
        const byId = id => document.getElementById(id);
        const hidden = byId(config.fieldid);
        const visible = byId('slbridge-key-visible');
        const view = byId('slbridge-view-key');
        const replace = byId('slbridge-start-replace');
        const panel = byId('slbridge-replace-panel');
        const generate = byId('slbridge-generate');
        const cancel = byId('slbridge-cancel');
        const confirm = byId('slbridge-confirm-replace');
        const copy = byId('slbridge-copy');
        const clear = byId('slbridge-clear');
        const status = byId('slbridge-key-status');

        if (!hidden || !visible || !status) {
            return;
        }

        const savedValue = config.saved ? visible.value : '';
        let generated = false;

        function reset() {
            hidden.value = '';
            visible.value = savedValue;
            visible.type = 'password';
            generated = false;

            if (confirm) confirm.checked = false;
            if (panel) panel.style.display = 'none';
            if (replace) replace.style.display = '';
            if (view) view.textContent = 'View Saved Key';

            copy.style.display = 'none';
            clear.style.display = 'none';
            status.textContent = 'No changes to the saved API key.';
        }

        if (view) {
            view.addEventListener('click', function() {
                const revealing = visible.type === 'password';
                visible.type = revealing ? 'text' : 'password';
                view.textContent = revealing
                    ? 'Hide Saved Key' : 'View Saved Key';
                copy.style.display = revealing
                    ? 'inline-block' : 'none';
            });
        }

        if (replace) {
            replace.addEventListener('click', function() {
                panel.style.display = 'block';
                replace.style.display = 'none';
                visible.type = 'password';
                visible.value = savedValue;
                if (view) {
                    view.textContent = 'View Saved Key';
                    view.style.display = 'none';
                }
                copy.style.display = 'none';
                status.textContent =
                    'Confirm replacement, then generate a new key.';
            });
        }

        if (generate) {
            generate.addEventListener('click', function() {
                if (confirm && !confirm.checked) {
                    status.textContent =
                        'Tick the confirmation checkbox first.';
                    return;
                }

                if (!window.crypto || !window.crypto.getRandomValues) {
                    status.textContent =
                        'Secure generation is unavailable in this browser.';
                    return;
                }

                const bytes = new Uint8Array(32);
                window.crypto.getRandomValues(bytes);

                const key = Array.from(bytes, byte =>
                    byte.toString(16).padStart(2, '0')
                ).join('');

                hidden.value = key;
                visible.value = key;
                visible.type = 'text';
                generated = true;

                copy.style.display = 'inline-block';
                clear.style.display = 'inline-block';

                status.textContent =
                    'API key generated. Copy it now. '
                    + 'Click Save changes only when ready to activate it.';
            });
        }

        if (clear) {
            clear.addEventListener('click', function() {
                if (config.saved) {
                    reset();
                    if (view) view.style.display = '';
                } else {
                    hidden.value = '';
                    visible.value = '';
                    visible.type = 'password';
                    generated = false;
                    copy.style.display = 'none';
                    clear.style.display = 'none';
                    status.textContent =
                        'Generated key cleared. No key has been saved.';
                }
            });
        }

        if (cancel) {
            cancel.addEventListener('click', function() {
                reset();
                if (view) view.style.display = '';
            });
        }

        if (copy) {
            copy.addEventListener('click', async function() {
                if (!visible.value) return;

                try {
                    await navigator.clipboard.writeText(visible.value);
                    status.textContent = generated
                        ? 'Generated key copied. Save changes to activate it.'
                        : 'Saved key copied.';
                } catch (error) {
                    visible.type = 'text';
                    visible.select();
                    status.textContent =
                        'Copy the selected key using your keyboard.';
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})
JS;

            $PAGE->requires->js_init_code(
                $javascript . '(' . $config . ');'
            );
        }

        return format_admin_setting(
            $this,
            $this->visiblename,
            $html,
            $this->description,
            '',
            $query
        );
    }
}
