// assets/js/ajax-handler.js
class ComplaintSystem {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const metaUrl = document.querySelector('meta[name="app-url"]')?.content?.trim();
        this.baseUrl = metaUrl || window.baseUrl || (window.location.origin + '/complaint-system');

    }
    
    async submitComplaint(formData) {
        try {
            const response = await fetch(`${this.baseUrl}/api/submit_complaint.php`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.csrfToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            return await response.json();
        } catch (error) {
            console.error('Submit complaint error:', error);
            return { success: false, message: 'Network error' };
        }
    }
    
    async voteComplaint(complaintId, voteType) {
        try {
            const response = await fetch(`${this.baseUrl}/api/vote.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    complaint_id: complaintId, 
                    vote_type: voteType,
                    csrf_token: this.csrfToken
                })
            });
            
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error('Vote error:', error);
            return { success: false, message: 'Network error' };
        }
    }
    
    async updateComplaintStatus(complaintId, status, reason = '') {
        try {
            const response = await fetch(`${this.baseUrl}/api/update_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    complaint_id: complaintId, 
                    status: status,
                    rejection_reason: reason,
                    csrf_token: this.csrfToken
                })
            });
            
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error('Update status error:', error);
            return { success: false, message: 'Network error' };
        }
    }
    
    async loadComplaints(page = 1, filters = {}) {
        try {
            const query = new URLSearchParams({ page, ...filters }).toString();
            const response = await fetch(`${this.baseUrl}/api/load_complaints.php?${query}`);
            return await response.json();
        } catch (error) {
            console.error('Load complaints error:', error);
            return { success: false, complaints: [] };
        }
    }
    
    async addComment(complaintId, comment, isAnonymous = true) {
        try {
            const response = await fetch(`${this.baseUrl}/api/add_comment.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    complaint_id: complaintId,
                    content: comment,
                    // keep backward-compat for any endpoint still reading 'comment'
                    comment: comment,
                    is_anonymous: isAnonymous,
                    csrf_token: this.csrfToken
                })
            });
            
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error('Add comment error:', error);
            return { success: false, message: 'Network error' };
        }
    }
    
    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}

// Initialize globally
window.ComplaintSystem = new ComplaintSystem();