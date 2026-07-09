/**
 * SmartRestro ERP - Main JavaScript
 * Vanilla JS - No dependencies
 */

// ── Toast Notification System ──
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) {
        // Fallback if no container exists on page yet
        const colors = { success: '#16a34a', error: '#dc2626', warning: '#eab308', info: '#2563eb' };
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 10000;
            background: ${colors[type] || colors.info}; color: #fff;
            padding: 10px 20px; border-radius: 8px; font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: opacity 0.3s;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
        return;
    }

    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${icons[type] || '📢'}</span>
        <span>${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ── Modal System ──
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click and Escape key
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// ── Confirm Dialog ──
function confirmAction(message, callback) {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = `
        <div class="confirm-dialog">
            <p>${message}</p>
            <div class="btn-group">
                <button class="btn btn-secondary" id="confirmCancel">Cancel</button>
                <button class="btn btn-primary" id="confirmOk">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    overlay.querySelector('#confirmOk').onclick = () => {
        overlay.remove();
        callback();
    };
    overlay.querySelector('#confirmCancel').onclick = () => overlay.remove();
    overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };
}

// ── AJAX Helper ──
async function postData(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await response.json();
}

// ── Currency Formatter ──
function formatCurrency(amount) {
    const num = parseFloat(amount) || 0;
    return '₹' + num.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ── Sidebar Mobile Toggle ──
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('active');
}

// Close sidebar on mobile when clicking outside
document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger');
    if (sidebar && sidebar.classList.contains('active') &&
        !sidebar.contains(e.target) && !hamburger?.contains(e.target)) {
        sidebar.classList.remove('active');
    }
});

// ── Print Bill ──
function printBill() {
    window.print();
}

// ── Scroll Animations ──
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.stat-card, .table-card, .menu-card, .card').forEach(el => {
        observer.observe(el);
    });
});

// ── Category Filter ──
function filterCategory(category) {
    document.querySelectorAll('.filter-pill').forEach(pill => {
        const isCurrent = pill.dataset.category === category || 
                          (category === 'all' && (pill.dataset.category === 'all' || pill.textContent.includes('All')));
        pill.classList.toggle('active', isCurrent);
    });

    document.querySelectorAll('.menu-card').forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// ── Dynamic ETA Timer Countdown ──
document.addEventListener('DOMContentLoaded', () => {
    function updateETACountdowns() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.eta-countdown').forEach(el => {
            const targetEpoch = parseInt(el.dataset.target);
            if (isNaN(targetEpoch)) return;
            
            const diff = targetEpoch - now;
            
            if (diff <= 0) {
                el.innerHTML = '🍳 Finishing touches...';
                el.style.color = 'var(--warning)';
            } else {
                const minutes = Math.floor(diff / 60);
                const seconds = diff % 60;
                const formattedSeconds = seconds < 10 ? '0' + seconds : seconds;
                el.innerHTML = `⏱️ ${minutes}:${formattedSeconds} min`;
                
                if (diff < 120) {
                    el.style.color = '#ef4444'; // Red for urgent
                } else if (diff < 300) {
                    el.style.color = '#f59e0b'; // Amber for warning
                } else {
                    el.style.color = '#3b82f6'; // Blue / primary
                }
            }
        });
    }

    updateETACountdowns();
    setInterval(updateETACountdowns, 1000);
});
