/**
 * FCE Writing Trainer — JavaScript
 * Timers, step navigation, sounds, and interactions
 */

(function () {
    'use strict';

    // ── Audio Context for Timer Sound ──────────────────────────
    let audioCtx = null;

    function initAudio() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
    }

    function playBeep(frequency, duration, times) {
        initAudio();
        times = times || 3;
        let delay = 0;

        for (let i = 0; i < times; i++) {
            setTimeout(function () {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(frequency, audioCtx.currentTime);

                gainNode.gain.setValueAtTime(0.5, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + duration);

                oscillator.start(audioCtx.currentTime);
                oscillator.stop(audioCtx.currentTime + duration);
            }, delay);
            delay += duration * 1000 + 150;
        }

        // Vibrate if supported
        if (navigator.vibrate) {
            const pattern = [];
            for (let i = 0; i < times; i++) {
                pattern.push(200, 150);
            }
            navigator.vibrate(pattern);
        }
    }

    function playTimerEnd() {
        playBeep(880, 0.3, 3);
        setTimeout(function () { playBeep(1100, 0.4, 1); }, 1200);
    }

    function playClick() {
        playBeep(600, 0.08, 1);
    }

    // ── Timer Class ────────────────────────────────────────────
    class Timer {
        constructor(container) {
            this.container = container;
            this.display = container.querySelector('.timer-display');
            this.btnStart = container.querySelector('.timer-btn-start');
            this.btnStop = container.querySelector('.timer-btn-stop');
            this.btnReset = container.querySelector('.timer-btn-reset');
            this.hiddenInput = container.querySelector('.timer-hidden-input');

            this.mode = container.dataset.timerMode || 'countdown'; // 'countdown' or 'stopwatch'
            this.initialSeconds = parseInt(container.dataset.timerSeconds) || 300;
            this.seconds = this.initialSeconds;
            this.running = false;
            this.interval = null;
            this.elapsed = 0;

            this.init();
        }

        init() {
            this.updateDisplay();

            if (this.btnStart) {
                this.btnStart.addEventListener('click', () => this.start());
            }
            if (this.btnStop) {
                this.btnStop.addEventListener('click', () => this.stop());
            }
            if (this.btnReset) {
                this.btnReset.addEventListener('click', () => this.reset());
            }

            // Initial button state
            this.updateButtons();
        }

        start() {
            if (this.running) return;
            initAudio(); // Init audio on user gesture
            this.running = true;
            this.updateButtons();
            playClick();

            this.interval = setInterval(() => {
                if (this.mode === 'countdown') {
                    this.seconds--;
                    this.elapsed++;

                    if (this.seconds <= 60 && this.seconds > 0) {
                        this.display.classList.add('timer-warning');
                    }

                    if (this.seconds <= 0) {
                        this.seconds = 0;
                        this.stop();
                        this.display.classList.remove('timer-warning');
                        this.display.classList.add('timer-done');
                        playTimerEnd();
                    }
                } else {
                    this.seconds++;
                    this.elapsed++;
                }

                this.updateDisplay();
                this.updateHiddenInput();
            }, 1000);
        }

        stop() {
            this.running = false;
            clearInterval(this.interval);
            this.interval = null;
            this.updateButtons();
        }

        reset() {
            this.stop();
            if (this.mode === 'countdown') {
                this.seconds = this.initialSeconds;
            } else {
                this.seconds = 0;
            }
            this.elapsed = 0;
            this.display.classList.remove('timer-warning', 'timer-done');
            this.updateDisplay();
            this.updateHiddenInput();
        }

        updateDisplay() {
            const s = this.mode === 'countdown' ? this.seconds : this.seconds;
            const mins = Math.floor(s / 60);
            const secs = s % 60;
            this.display.textContent =
                String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }

        updateButtons() {
            if (this.btnStart) {
                this.btnStart.style.display = this.running ? 'none' : '';
            }
            if (this.btnStop) {
                this.btnStop.style.display = this.running ? '' : 'none';
            }
        }

        updateHiddenInput() {
            if (this.hiddenInput) {
                this.hiddenInput.value = this.elapsed;
            }
        }

        getElapsed() {
            return this.elapsed;
        }
    }

    // ── Step Navigation ────────────────────────────────────────
    function initSteps() {
        const steps = document.querySelectorAll('.step');
        if (steps.length === 0) return;

        steps.forEach(function (step, index) {
            const header = step.querySelector('.step-header');
            if (!header) return;

            header.addEventListener('click', function () {
                // Close all steps
                steps.forEach(function (s) {
                    s.classList.remove('step-active');
                });
                // Open clicked step
                step.classList.add('step-active');
            });
        });

        // "Next step" buttons
        document.querySelectorAll('.step-next-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const currentStep = btn.closest('.step');
                const nextStep = currentStep.nextElementSibling;

                if (nextStep && nextStep.classList.contains('step')) {
                    currentStep.classList.remove('step-active');
                    currentStep.classList.add('step-done');
                    nextStep.classList.add('step-active');

                    // Scroll to next step
                    nextStep.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    playClick();
                }
            });
        });
    }

    // ── Reveal Button (show/hide model text) ───────────────────
    function initRevealButtons() {
        document.querySelectorAll('.reveal-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = btn.dataset.target;
                const target = document.getElementById(targetId);
                if (target) {
                    target.classList.toggle('content-hidden');
                    if (target.classList.contains('content-hidden')) {
                        btn.textContent = '👁️ Mostrar texto del modelo';
                    } else {
                        btn.textContent = '🙈 Ocultar texto del modelo';
                    }
                }
            });
        });
    }

    // ── Checkbox Limit (max 5 for Useful Language) ─────────────
    function initCheckboxLimit() {
        const checkboxes = document.querySelectorAll('.ul-checkbox');
        if (checkboxes.length === 0) return;

        const maxChecked = 5;

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                const checked = document.querySelectorAll('.ul-checkbox:checked');
                if (checked.length > maxChecked) {
                    cb.checked = false;
                    // Brief shake animation
                    cb.closest('.ul-item').style.animation = 'none';
                    cb.closest('.ul-item').offsetHeight; // trigger reflow
                    cb.closest('.ul-item').style.animation = 'shake 0.3s ease';
                }
            });
        });

        // Add shake keyframes if not exists
        if (!document.querySelector('#shake-keyframes')) {
            const style = document.createElement('style');
            style.id = 'shake-keyframes';
            style.textContent = '@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }';
            document.head.appendChild(style);
        }
    }

    // ── Session Form ───────────────────────────────────────────
    function initSessionForm() {
        const form = document.getElementById('session-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            // Collect timer data into hidden fields
            const copyTimeInput = form.querySelector('input[name="copy_time_seconds"]');
            const draftTimeInput = form.querySelector('input[name="draft_time_seconds"]');

            // Timer 2 (transcription) = copy time
            const timer2 = document.querySelector('[data-step="2"] .timer-container');
            if (timer2) {
                const hiddenInput = timer2.querySelector('.timer-hidden-input');
                if (hiddenInput && copyTimeInput) {
                    copyTimeInput.value = hiddenInput.value || 0;
                }
            }

            // Timer 4 (draft) = draft time
            const timer4 = document.querySelector('[data-step="4"] .timer-container');
            if (timer4) {
                const hiddenInput = timer4.querySelector('.timer-hidden-input');
                if (hiddenInput && draftTimeInput) {
                    draftTimeInput.value = hiddenInput.value || 0;
                }
            }
        });
    }

    // ── Confirm Dialogs ────────────────────────────────────────
    function initConfirmDialogs() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                const message = el.dataset.confirm;
                const form = el.closest('form');

                const overlay = document.createElement('div');
                overlay.className = 'confirm-overlay';
                overlay.innerHTML =
                    '<div class="confirm-dialog">' +
                    '<h3>⚠️ Confirmar</h3>' +
                    '<p>' + message + '</p>' +
                    '<div class="confirm-actions">' +
                    '<button class="btn btn-secondary confirm-cancel">Cancelar</button>' +
                    '<button class="btn btn-danger confirm-ok">Confirmar</button>' +
                    '</div>' +
                    '</div>';

                document.body.appendChild(overlay);

                overlay.querySelector('.confirm-cancel').addEventListener('click', function () {
                    overlay.remove();
                });

                overlay.querySelector('.confirm-ok').addEventListener('click', function () {
                    overlay.remove();
                    if (form) form.submit();
                });

                overlay.addEventListener('click', function (ev) {
                    if (ev.target === overlay) overlay.remove();
                });
            });
        });
    }

    // ── Flash Messages Auto-dismiss ────────────────────────────
    function initFlashMessages() {
        document.querySelectorAll('.flash-message').forEach(function (flash) {
            setTimeout(function () {
                flash.style.opacity = '0';
                flash.style.transform = 'translateY(-8px)';
                flash.style.transition = 'all 0.3s ease';
                setTimeout(function () { flash.remove(); }, 300);
            }, 4000);
        });
    }

    // ── Filter Buttons (prevent default only for JS-handled) ──
    function initFilters() {
        // Filters navigate via URL params, no JS needed
        // But make active state feel instant
        document.querySelectorAll('.filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.filter-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
            });
        });
    }

    // ── Init All Timers ────────────────────────────────────────
    function initTimers() {
        document.querySelectorAll('.timer-container').forEach(function (container) {
            new Timer(container);
        });
    }

    // ── Smooth scroll for nav ──────────────────────────────────
    function initNav() {
        // Highlight current nav item based on page
        const currentPage = document.body.dataset.page;
        if (currentPage) {
            document.querySelectorAll('.nav-item').forEach(function (item) {
                item.classList.remove('active');
                if (item.href && item.href.includes('p=' + currentPage)) {
                    item.classList.add('active');
                }
            });
        }
    }

    // ── Warm-up tag animation staggering ───────────────────────
    function initWarmupTags() {
        document.querySelectorAll('.warmup-tag').forEach(function (tag, index) {
            tag.style.animationDelay = (index * 0.06) + 's';
        });
    }

    // ── Init Everything ────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        initTimers();
        initSteps();
        initRevealButtons();
        initCheckboxLimit();
        initSessionForm();
        initConfirmDialogs();
        initFlashMessages();
        initFilters();
        initNav();
        initWarmupTags();
    });

})();
