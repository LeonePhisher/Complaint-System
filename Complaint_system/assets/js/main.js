// Main JavaScript File for HTU Complaint System

// Base URL used by fetch calls in this file.
// Prefer <meta name="app-url" ...>, but fall back to the local app path.
(function initBaseUrl() {
    const meta = document.querySelector('meta[name="app-url"]');
    const metaValue = meta && meta.content ? meta.content.trim() : '';
    if (metaValue) {
        window.baseUrl = metaValue;
        return;
    }
    // Default XAMPP path for this project
    window.baseUrl = (window.location.origin || '') + '/complaint-system';
})();

// Make fetch(...).json() resilient to PHP notices/whitespace before JSON.
// This prevents false UI 'Network error' toasts when the backend did the work but emitted extra output.
(function patchResponseJson() {
    try {
        if (Response.prototype.__htuJsonPatched) return;
        const origJson = Response.prototype.json;
        Response.prototype.json = async function() {
            const clone = this.clone();
            try {
                return await origJson.call(this);
            } catch (err) {
                const text = await clone.text();
                const trimmed = (text || '').trim();
                if (!trimmed) throw err;

                // Try normal parse first
                try { return JSON.parse(trimmed); } catch (e) {}

                // If PHP warnings/HTML preceded JSON, strip leading junk up to the first { or [
                const firstObj = trimmed.indexOf('{');
                const firstArr = trimmed.indexOf('[');
                let start = -1;
                if (firstObj >= 0 && firstArr >= 0) start = Math.min(firstObj, firstArr);
                else start = firstObj >= 0 ? firstObj : firstArr;

                if (start > 0) {
                    const candidate = trimmed.slice(start);
                    try { return JSON.parse(candidate); } catch (e) {}
                }

                console.error('JSON parse failed. Response snippet:', trimmed.slice(0, 500));
                throw err;
            }
        };
        Response.prototype.__htuJsonPatched = true;
    } catch (e) {
        // If this fails for any reason, fall back to default behavior.
    }
})();

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initTheme();
    initNotifications();
    initFormValidation();
    initTooltips();
    initModals();
    initDropdowns();
    initSmoothScroll();
    initLazyLoading();
    initAJAXForms();
    
    // Setup event listeners
    setupEventListeners();
    
    // Check for notifications
    checkNotifications();
    // Refresh count every 30 seconds
    setInterval(checkNotifications, 30000);
});

// Theme Management
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    let savedTheme = 'light';
    try {
        savedTheme = localStorage.getItem('theme') || 'light';
    } catch (e) {
        savedTheme = 'light';
    }
    
    // Set initial theme
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    if (themeToggle) {
        // Update toggle button icon
        updateThemeIcon(savedTheme);
        
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Apply new theme
            document.documentElement.setAttribute('data-theme', newTheme);
            try {
                localStorage.setItem('theme', newTheme);
            } catch (e) {
                // Ignore storage failures (e.g. Safari private mode)
            }
             
            // Update toggle button icon
            updateThemeIcon(newTheme);
            
            // Dispatch theme change event
            document.dispatchEvent(new CustomEvent('themeChange', { detail: newTheme }));
        });
    }
}

function updateThemeIcon(theme) {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    const icon = themeToggle.querySelector('i');
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Notification System
function initNotifications() {
    // Admin pages have their own notifications UI in the admin navbar.
    // Avoid double-binding handlers (and student-only URLs) when on /pages/admin/.
    if (window.location.pathname && window.location.pathname.indexOf('/pages/admin/') !== -1) {
        return;
    }

    const notificationBell = document.getElementById('notificationBell');
    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            toggleNotificationsPanel();
        });
    }
    
    // Close notifications when clicking outside or handle mark-all-read link
    document.addEventListener('click', function(e) {
        const notificationsPanel = document.getElementById('notificationsPanel');
        if (notificationsPanel && !notificationsPanel.contains(e.target) && 
            notificationBell && !notificationBell.contains(e.target)) {
            notificationsPanel.classList.remove('show');
        }

        if (e.target.matches('.mark-all-read')) {
            e.preventDefault();
            markNotificationsAsRead();
        }
    });
} 

function toggleNotificationsPanel() {
    const panel = document.getElementById('notificationsPanel');
    if (!panel) return;
    const willShow = !panel.classList.contains('show');
    
    if (willShow) {
        panel.classList.add('show');
        panel.style.display = 'block';
        loadNotifications();
    } else {
        panel.classList.remove('show');
        panel.style.display = 'none';
    }
}

function checkNotifications() {
    fetch(`${window.baseUrl}/api/notifications_unified.php?action=unread_count`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                updateNotificationBadge(data.count);
            } else {
                const badge = document.querySelector('.notification-badge');
                if (badge) badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

function markNotificationsAsRead() {
    fetch(`${window.baseUrl}/api/notifications_unified.php?action=mark_all_read`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(el=>{
                el.classList.remove('unread');
                el.classList.add('read');
            });
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.style.display = 'none';
            checkNotifications();
        }
    })
    .catch(error => console.error('Error marking notifications as read:', error));
}

function loadNotifications() {
    const container = document.getElementById('notificationList');
    if (!container) return;
    
    container.innerHTML = '<p style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
    
    // Try new unified API first, fall back to old API
    fetch(`${window.baseUrl}/api/notifications_unified.php?action=recent_unread&limit=5`)
        .then(resp => resp.json())
        .then(data => {
            if (!container) return;
            container.innerHTML = '';
            
            if (data.success && Array.isArray(data.notifications) && data.notifications.length) {
                data.notifications.forEach(n => {
                    const el = document.createElement('div');
                    el.className = `notification-item ${n.is_read ? 'read' : 'unread'}`;
                    el.dataset.id = n.id;
                    el.dataset.relatedType = n.related_type || '';
                    el.dataset.relatedId = n.related_id || '';
                    el.dataset.title = n.title || '';
                    
                    if (n.related_type && n.related_id) {
                        let url = '';
                        if (n.related_type === 'complaint') {
                            url = `${window.baseUrl}/pages/student/feed.php?complaint_id=${n.related_id}`;
                        } else if (n.related_type === 'comment') {
                            url = `${window.baseUrl}/pages/student/feed.php?comment_id=${n.related_id}`;
                        }
                        el.dataset.url = url;
                    }
                    
                    el.innerHTML = `
                        <div class="notification-icon"><i class="fas fa-${getNotificationIcon(n.type)}"></i></div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(n.title)}</div>
                            <div class="notification-message">${escapeHtml(n.message)}</div>
                            <div class="notification-time">${n.time_ago}</div>
                        </div>
                    `;
                    
                    el.addEventListener('click', () => {
                        // Default behavior: mark read then follow URL (if any)
                        markSingleNotificationAsRead(n.id);
                        if (el.dataset.url) {
                            window.location.href = el.dataset.url;
                        }
                    });
                    
                    container.appendChild(el);
                });
            } else {
                container.innerHTML = '<div class="empty-notifications" style="padding: 2rem; text-align: center;"><i class="fas fa-bell-slash"></i><p>No new notifications</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            if (container) {
                container.innerHTML = '<div class="alert alert-error">Failed to load notifications</div>';
            }
        });
}

function markSingleNotificationAsRead(id) {
    fetch(`${window.baseUrl}/api/notifications_unified.php?action=mark_read`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({notification_id: id})
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            checkNotifications();
        }
    })
    .catch(console.error);
}

function escapeHtml(text) {
    text = String(text ?? '');
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Student complaint-details modal for non-published complaint notifications (rejected/resolved/etc.)
let __studentComplaintModalBound = false;
let __studentComplaintModalPending = { notificationId: null, sourceEl: null };

function isPublishedComplaintNotification(title, message) {
    const t = String(title ?? '').toLowerCase();
    // Current system titles include: "Your Complaint Has Been Published"
    return t.includes('published');
}

function bindStudentComplaintModalOnce() {
    if (__studentComplaintModalBound) return;
    __studentComplaintModalBound = true;

    const modal = document.getElementById('studentComplaintModal');
    const closeBtn = document.getElementById('studentComplaintModalClose');
    if (!modal) return;

    closeBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        closeStudentComplaintDetailsModal();
    });

    modal.addEventListener('click', (e) => {
        // click outside dialog closes
        if (e.target === modal) {
            closeStudentComplaintDetailsModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeStudentComplaintDetailsModal();
        }
    });
}

function openStudentComplaintDetailsModal(complaintId, notificationId, sourceEl) {
    const modal = document.getElementById('studentComplaintModal');
    const body = document.getElementById('studentComplaintModalBody');
    if (!modal || !body) {
        // Fallback: at least mark as read so badge updates.
        if (notificationId) markSingleNotificationAsRead(notificationId);
        return;
    }

    bindStudentComplaintModalOnce();

    // Close the dropdown panel (especially important on mobile)
    const panel = document.getElementById('notificationsPanel');
    if (panel) {
        panel.classList.remove('show');
        panel.style.display = 'none';
    }

    __studentComplaintModalPending.notificationId = notificationId || null;
    __studentComplaintModalPending.sourceEl = sourceEl || null;

    body.innerHTML = '<div style="padding: 1rem; text-align:center;"><i class="fas fa-spinner fa-spin"></i> Loading complaint...</div>';
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');

    fetch(`${window.baseUrl}/api/get_student_complaint.php?id=${encodeURIComponent(complaintId)}`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success || !data.complaint) {
                body.innerHTML = `<div style="padding: 1rem;" class="alert alert-error">${escapeHtml(data?.message || 'Failed to load complaint')}</div>`;
                return;
            }

            const c = data.complaint;
            const status = String(c.status || '').replace(/_/g, ' ');

            const rejectionBlock = c.rejection_reason ? `
                <div class="sc-banner sc-banner-danger">
                    <div class="sc-banner-title">Rejected</div>
                    <div class="sc-banner-text"><strong>Reason:</strong> ${escapeHtml(c.rejection_reason)}</div>
                    ${c.rejected_by ? `<div class="sc-banner-sub">Rejected by Admin #${escapeHtml(c.rejected_by)}</div>` : ''}
                </div>
            ` : '';

            const resolvedBlock = c.resolved_at ? `
                <div class="sc-banner sc-banner-success">
                    <div class="sc-banner-title">Resolved</div>
                    <div class="sc-banner-text">Resolved on ${escapeHtml(new Date(c.resolved_at).toLocaleString())}</div>
                    ${c.resolution_notes ? `<div class="sc-banner-sub">${escapeHtml(c.resolution_notes)}</div>` : ''}
                </div>
            ` : '';

            body.innerHTML = `
                <div class="sc-meta">
                    <span class="sc-pill" style="border-color: ${escapeHtml(c.category_color || '#667eea')}; color: ${escapeHtml(c.category_color || '#667eea')};">
                        ${escapeHtml(c.category_name || 'Category')}
                    </span>
                    <span class="sc-pill sc-pill-muted">${escapeHtml(status || 'status')}</span>
                    <span class="sc-pill sc-pill-muted">#${escapeHtml(c.complaint_code || '')}</span>
                </div>

                <h3 class="sc-title">${escapeHtml(c.title || '')}</h3>

                <div class="sc-small">
                    <span><i class="fas fa-calendar"></i> ${escapeHtml(new Date(c.created_at).toLocaleString())}</span>
                    ${c.location ? `<span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(c.location)}</span>` : ''}
                    ${c.urgency ? `<span><i class="fas fa-bolt"></i> ${escapeHtml(String(c.urgency).toUpperCase())}</span>` : ''}
                </div>

                <div class="sc-section">
                    <div class="sc-section-title">Description</div>
                    <div class="sc-box">${escapeHtml(c.description || '')}</div>
                </div>

                ${rejectionBlock}
                ${resolvedBlock}
            `;
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = '<div style="padding: 1rem;" class="alert alert-error">Network error loading complaint</div>';
        });
}

function closeStudentComplaintDetailsModal() {
    const modal = document.getElementById('studentComplaintModal');
    if (!modal) return;

    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');

    // Mark notification as read on close (matches admin behavior)
    const nid = __studentComplaintModalPending.notificationId;
    const el = __studentComplaintModalPending.sourceEl;
    __studentComplaintModalPending.notificationId = null;
    __studentComplaintModalPending.sourceEl = null;

    if (nid) {
        markSingleNotificationAsRead(nid);
        if (el && el.classList) {
            el.classList.remove('unread');
            el.classList.add('read');
        }
    }
}

// Expose for pages that render notifications server-side (pages/student/notifications.php)
window.openStudentComplaintDetailsModal = openStudentComplaintDetailsModal;
window.closeStudentComplaintDetailsModal = closeStudentComplaintDetailsModal;



// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('[required], [data-validate]');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
            
            // Scroll to first error
            if (isValid === false) {
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                input.focus();
                isValid = true; // Prevent multiple scrolls
            }
        }
    });
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    let isValid = true;
    
    // Clear previous errors
    clearFieldError(field);
    
    // Required validation
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    // Email validation
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Please enter a valid email address');
            return false;
        }
    }
    
    // Password validation
    if (name === 'password' && value) {
        if (value.length < 6) {
            showFieldError(field, 'Password must be at least 6 characters');
            return false;
        }
    }
    
    // Confirm password validation
    if (name === 'confirm_password' && value) {
        const password = document.querySelector('input[name="password"]');
        if (password && password.value !== value) {
            showFieldError(field, 'Passwords do not match');
            return false;
        }
    }
    
    // Phone number validation
    if (name === 'phone' && value) {
        const phoneRegex = /^[0-9]{10}$/;
        if (!phoneRegex.test(value.replace(/\D/g, ''))) {
            showFieldError(field, 'Please enter a valid phone number');
            return false;
        }
    }
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('error');
    
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    errorElement.style.cssText = `
        color: var(--danger-color);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    `;
    
    field.parentNode.appendChild(errorElement);
}

function clearFieldError(field) {
    field.classList.remove('error');
    
    const errorElement = field.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

// Tooltips
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('focus', showTooltip);
        element.addEventListener('blur', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.target;
    const tooltipText = element.getAttribute('data-tooltip');
    
    if (!tooltipText) return;
    
    // Remove existing tooltip
    hideTooltip(e);
    
    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.cssText = `
        position: absolute;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        color: var(--text-primary);
        z-index: 1000;
        white-space: nowrap;
        box-shadow: var(--shadow-md);
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    let top = rect.top - tooltipRect.height - 8;
    let left = rect.left + (rect.width - tooltipRect.width) / 2;
    
    // Adjust if tooltip goes off screen
    if (top < 0) {
        top = rect.bottom + 8;
    }
    
    if (left < 0) {
        left = 8;
    } else if (left + tooltipRect.width > window.innerWidth) {
        left = window.innerWidth - tooltipRect.width - 8;
    }
    
    tooltip.style.top = `${top + window.scrollY}px`;
    tooltip.style.left = `${left}px`;
    
    element.dataset.tooltipId = tooltip.id;
}

function hideTooltip(e) {
    const element = e.target;
    const tooltip = document.querySelector('.tooltip');
    
    if (tooltip) {
        tooltip.remove();
    }
    
    delete element.dataset.tooltipId;
}

// Modal System
function initModals() {
    // Open modals
    document.querySelectorAll('[data-modal-toggle]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal-toggle');
            const modal = document.getElementById(modalId);
            
            if (modal) {
                openModal(modal);
            }
        });
    });
    
    // Close modals
    document.querySelectorAll('[data-modal-hide]').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal-hide');
            const modal = document.getElementById(modalId);
            
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    // Close modal when clicking on backdrop
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function openModal(modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus first input in modal
    const input = modal.querySelector('input, textarea, select');
    if (input) {
        setTimeout(() => input.focus(), 100);
    }
}

function closeModal(modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// Dropdowns
function initDropdowns() {
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.dropdown');
            const isOpen = dropdown.classList.contains('show');
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown.show').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show', !isOpen);
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown.show').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    });
}

// Smooth Scroll
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            
            if (targetId === '#') return;
            
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Lazy Loading
function initLazyLoading() {
    if ('IntersectionObserver' in window) {
        const lazyImages = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    
                    if (img.dataset.srcset) {
                        img.srcset = img.dataset.srcset;
                    }
                    
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    }
}

// AJAX Forms
function initAJAXForms() {
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAJAXForm(this);
        });
    });
}

async function submitAJAXForm(form) {
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    submitBtn.disabled = true;
    
    // Collect form data
    const formData = new FormData(form);
    const action = form.getAttribute('action') || window.location.href;
    const method = form.getAttribute('method') || 'POST';
    
    try {
        const response = await fetch(action, {
            method: method,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            showToast(data.message || 'Success!', 'success');
            
            // Reset form if needed
            if (form.hasAttribute('data-reset')) {
                form.reset();
            }
            
            // Redirect if needed
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
            
            // Trigger callback if exists
            if (window[form.dataset.callback]) {
                window[form.dataset.callback](data);
            }
        } else {
            // Show error message
            showToast(data.message || 'An error occurred', 'error');
            
            // Show field errors if any
            if (data.errors) {
                Object.keys(data.errors).forEach(fieldName => {
                    const field = form.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        showFieldError(field, data.errors[fieldName]);
                    }
                });
            }
        }
    } catch (error) {
        console.error('Form submission error:', error);
        showToast('Network error. Please try again.', 'error');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Toast Notifications
function showToast(message, type = 'info', duration = 3000) {
    // Remove existing toasts
    document.querySelectorAll('.toast').forEach(toast => {
        toast.remove();
    });
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${getToastIcon(type)}"></i>
        </div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add styles
    toast.style.cssText = `
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        max-width: 350px;
        box-shadow: var(--shadow-xl);
    `;
    
    // Add border color based on type
    const borderColors = {
        success: '#48bb78',
        error: '#f56565',
        warning: '#ed8936',
        info: '#4299e1'
    };
    
    toast.style.borderLeft = `4px solid ${borderColors[type] || borderColors.info}`;
    
    document.body.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

function getToastIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Setup Event Listeners
function setupEventListeners() {
    // Password visibility toggle
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    
    // Character counters
    document.querySelectorAll('[data-maxlength]').forEach(element => {
        const maxLength = parseInt(element.getAttribute('data-maxlength'));
        const counterId = element.getAttribute('data-counter') || `counter-${Date.now()}`;
        
        // Create counter element
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.id = counterId;
        counter.style.cssText = `
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: right;
            margin-top: 0.25rem;
        `;
        
        element.parentNode.appendChild(counter);
        
        // Update counter
        function updateCounter() {
            const length = element.value.length;
            counter.textContent = `${length}/${maxLength}`;
            
            if (length > maxLength * 0.9) {
                counter.style.color = 'var(--warning-color)';
            } else if (length > maxLength) {
                counter.style.color = 'var(--danger-color)';
            } else {
                counter.style.color = 'var(--text-muted)';
            }
        }
        
        element.addEventListener('input', updateCounter);
        updateCounter(); // Initial update
    });
    
    // Copy to clipboard
    document.querySelectorAll('[data-copy]').forEach(button => {
        button.addEventListener('click', function() {
            const text = this.getAttribute('data-copy');
            const target = this.getAttribute('data-copy-target');
            
            let textToCopy = text;
            
            if (target) {
                const targetElement = document.querySelector(target);
                if (targetElement) {
                    textToCopy = targetElement.textContent || targetElement.value;
                }
            }
            
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });
        });
    });
    
    // File upload preview
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            const preview = document.getElementById(previewId);
            
            if (!preview || !this.files.length) return;
            
            preview.innerHTML = '';
            
            Array.from(this.files).forEach((file, index) => {
                const fileElement = document.createElement('div');
                fileElement.className = 'file-preview';
                fileElement.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <small>(${(file.size / 1024).toFixed(2)} KB)</small>
                    </div>
                    <button type="button" class="file-remove" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                preview.appendChild(fileElement);
            });
            
            // Add remove functionality
            preview.querySelectorAll('.file-remove').forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    
                    // Create new FileList without the removed file
                    const dt = new DataTransfer();
                    Array.from(input.files).forEach((file, i) => {
                        if (i !== index) {
                            dt.items.add(file);
                        }
                    });
                    
                    input.files = dt.files;
                    fileElement.remove();
                    
                    // Trigger change event
                    input.dispatchEvent(new Event('change'));
                });
            });
        });
    });
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Export for use in other modules
window.HTUComplaint = {
    showToast,
    openModal,
    closeModal,
    validateForm,
    submitAJAXForm,
    debounce,
    throttle
};
