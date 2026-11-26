import './bootstrap';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Dashboard pie charts: Chart.js is loaded only on pages that have a chart.
if (document.querySelector('canvas[data-pie-chart]')) {
    import('./charts').then(({ drawPieCharts }) => drawPieCharts());
}

// Show/hide password fields (see resources/views/components/password-input.blade.php).
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) {
        return;
    }

    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) {
        return;
    }

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';

    const label = show ? 'Hide password' : 'Show password';
    button.title = label;
    button.setAttribute('aria-label', label);
    button.setAttribute('aria-pressed', String(show));

    const icon = button.querySelector('i');
    icon.classList.toggle('bi-eye', !show);
    icon.classList.toggle('bi-eye-slash', show);

    input.focus();
});
