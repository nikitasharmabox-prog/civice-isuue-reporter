// Civic Issue Reporter - Main JS

// Navbar mobile toggle
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('navToggle');
    const navLinks = document.querySelector('.nav-links');
    if (toggle && navLinks) {
        toggle.addEventListener('click', () => navLinks.classList.toggle('open'));
    }
});

// Toast notification system
const ToastManager = {
    container: null,
    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span>${message}</span>`;
        this.container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px)';
            toast.style.transition = '0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// Photo upload preview
function initPhotoUpload(inputId, previewId, areaId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const area = document.getElementById(areaId);
    if (!input || !preview || !area) return;

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            ToastManager.show('File too large. Max 5MB allowed.', 'error');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            area.querySelector('.upload-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
    area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
    area.addEventListener('drop', e => {
        e.preventDefault();
        area.classList.remove('drag-over');
        if (e.dataTransfer.files[0]) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
}

// GPS acquisition
function initGPSButton(btnId, latId, lngId, statusId, addrId) {
    const btn = document.getElementById(btnId);
    const latInput = document.getElementById(latId);
    const lngInput = document.getElementById(lngId);
    const statusEl = document.getElementById(statusId);
    const addrInput = addrId ? document.getElementById(addrId) : null;
    if (!btn || !latInput || !lngInput) return;

    btn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            ToastManager.show('Geolocation is not supported by your browser.', 'error');
            return;
        }
        const dot = statusEl ? statusEl.querySelector('.gps-dot') : null;
        const txt = statusEl ? statusEl.querySelector('.gps-text') : null;
        if (dot) { dot.className = 'gps-dot acquiring'; }
        if (txt) txt.textContent = 'Acquiring location...';
        btn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude.toFixed(7);
                const lng = pos.coords.longitude.toFixed(7);
                latInput.value = lat;
                lngInput.value = lng;
                if (dot) dot.className = 'gps-dot acquired';
                if (txt) txt.textContent = `GPS acquired (±${Math.round(pos.coords.accuracy)}m)`;
                btn.disabled = false;
                ToastManager.show('Location captured successfully!', 'success');

                // Reverse geocode using Nominatim
                if (addrInput) {
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.display_name) addrInput.value = data.display_name;
                        }).catch(() => {});
                }
            },
            (err) => {
                if (dot) dot.className = 'gps-dot error';
                const msg = err.code === 1 ? 'Location permission denied.' : 'Could not get location.';
                if (txt) txt.textContent = msg;
                btn.disabled = false;
                ToastManager.show(msg, 'error');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Category icons
const CATEGORY_ICONS = {
    pothole: 'fa-road',
    garbage: 'fa-trash',
    streetlight: 'fa-lightbulb',
    water_leak: 'fa-tint',
    sewage: 'fa-water',
    road_damage: 'fa-exclamation-triangle',
    encroachment: 'fa-building',
    other: 'fa-question-circle',
};

function categoryIcon(cat) {
    return CATEGORY_ICONS[cat] || 'fa-question-circle';
}

function categoryLabel(cat) {
    return cat ? cat.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '—';
}

// Expose globally
window.ToastManager = ToastManager;
window.initPhotoUpload = initPhotoUpload;
window.initGPSButton = initGPSButton;
window.formatDate = formatDate;
window.categoryIcon = categoryIcon;
window.categoryLabel = categoryLabel;
