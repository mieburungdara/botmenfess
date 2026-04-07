/**
 * Shared JavaScript utilities for Telegram Web App
 */

// ============================================
// Telegram Web App Integration
// ============================================

const TelegramWebApp = {
    init() {
        if (window.Telegram && window.Telegram.WebApp) {
            this.webApp = window.Telegram.WebApp;
            this.webApp.ready();
            this.webApp.expand();
            return true;
        }
        return false;
    },
    
    getTheme() {
        if (this.webApp) {
            return this.webApp.colorScheme || 'light';
        }
        return document.documentElement.getAttribute('data-theme') || 'light';
    },
    
    applyTheme() {
        const theme = this.getTheme();
        document.documentElement.setAttribute('data-theme', theme);
    },
    
    getUserId() {
        if (this.webApp && this.webApp.initDataUnsafe && this.webApp.initDataUnsafe.user) {
            return this.webApp.initDataUnsafe.user.id;
        }
        return null;
    },
    
    getUserName() {
        if (this.webApp && this.webApp.initDataUnsafe && this.webApp.initDataUnsafe.user) {
            const user = this.webApp.initDataUnsafe.user;
            return user.username ? `@${user.username}` : (user.first_name || 'User');
        }
        return 'User';
    },
    
    hapticFeedback(type = 'light') {
        if (this.webApp && this.webApp.HapticFeedback) {
            this.webApp.HapticFeedback.impactOccurred(type);
        }
    },
    
    showMainButton(text, callback) {
        if (this.webApp) {
            this.webApp.MainButton.setText(text);
            this.webApp.MainButton.show();
            this.webApp.MainButton.onClick(callback);
        }
    },
    
    hideMainButton() {
        if (this.webApp && this.webApp.MainButton) {
            this.webApp.MainButton.hide();
        }
    },
    
    showBackButton(callback) {
        if (this.webApp) {
            this.webApp.BackButton.show();
            this.webApp.BackButton.onClick(callback);
        }
    },
    
    hideBackButton() {
        if (this.webApp && this.webApp.BackButton) {
            this.webApp.BackButton.hide();
        }
    },
    
    close() {
        if (this.webApp) {
            this.webApp.close();
        }
    }
};

// ============================================
// API Helper
// ============================================

const API = {
    baseURL: './api/',
    
    async get(endpoint, params = {}) {
        const url = new URL(this.baseURL + endpoint, window.location.href);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        const response = await fetch(url.toString());
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    },
    
    async post(endpoint, data = {}) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }
};

// ============================================
// UI Helpers
// ============================================

const UI = {
    showToast(message, type = 'info', duration = 3000) {
        // Remove existing toast from DOM to prevent accumulation
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        toast.classList.add(type, 'show');
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('show');
            // Remove from DOM after animation completes
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    showLoading() {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }
        overlay.classList.add('active');
    },
    
    hideLoading() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    },
    
    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    },
    
    formatDate(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) {
            return 'Baru saja';
        }
        if (diff < 3600000) {
            return Math.floor(diff / 60000) + ' menit lalu';
        }
        if (diff < 86400000) {
            return Math.floor(diff / 3600000) + ' jam lalu';
        }
        if (diff < 604800000) {
            return Math.floor(diff / 86400000) + ' hari lalu';
        }
        
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    },
    
    formatHour(hour) {
        if (hour === undefined || hour === null) return '-';
        const h = parseInt(hour);
        if (h >= 6 && h < 12) return `${h}:00 Pagi`;
        if (h >= 12 && h < 18) return `${h}:00 Siang`;
        if (h >= 18 && h < 24) return `${h}:00 Malam`;
        return `${h}:00 Dini Hari`;
    },
    
    getMedalEmoji(rank) {
        if (rank === 1) return '🥇';
        if (rank === 2) return '🥈';
        if (rank === 3) return '🥉';
        return rank;
    },
    
    getTrendIcon(trend) {
        switch (trend) {
            case 'up': return '⬆️';
            case 'down': return '⬇️';
            default: return '➡️';
        }
    },
    
    getTrendClass(trend) {
        switch (trend) {
            case 'up': return 'trend-up';
            case 'down': return 'trend-down';
            default: return 'trend-same';
        }
    }
};

// ============================================
// Pull to Refresh
// ============================================

function initPullToRefresh(onRefresh) {
    let startY = 0;
    let currentY = 0;
    let isPulling = false;
    const threshold = 60;
    
    const container = document.querySelector('.pull-to-refresh') || document.querySelector('.container');
    if (!container) return;
    
    // Create pull indicator
    let pullIndicator = document.querySelector('.pull-indicator');
    if (!pullIndicator) {
        pullIndicator = document.createElement('div');
        pullIndicator.className = 'pull-indicator';
        pullIndicator.innerHTML = '<div class="spinner"></div>';
        container.style.position = 'relative';
        container.prepend(pullIndicator);
    }
    
    function handleTouchStart(e) {
        if (window.scrollY === 0) {
            startY = e.touches[0].pageY;
            isPulling = true;
        }
    }
    
    function handleTouchMove(e) {
        if (!isPulling) return;
        
        currentY = e.touches[0].pageY - startY;
        
        if (currentY > 0 && currentY < 150) {
            pullIndicator.style.top = (currentY - 50) + 'px';
            if (currentY > threshold) {
                pullIndicator.classList.add('visible');
            } else {
                pullIndicator.classList.remove('visible');
            }
        }
    }
    
    async function handleTouchEnd() {
        if (!isPulling) return;
        isPulling = false;
        
        if (currentY > threshold) {
            pullIndicator.classList.add('visible');
            await onRefresh();
            pullIndicator.classList.remove('visible');
            pullIndicator.style.top = '-50px';
        }
        
        currentY = 0;
    }
    
    container.addEventListener('touchstart', handleTouchStart, { passive: true });
    container.addEventListener('touchmove', handleTouchMove, { passive: true });
    container.addEventListener('touchend', handleTouchEnd, { passive: true });
    
    // Return cleanup function
    return () => {
        container.removeEventListener('touchstart', handleTouchStart);
        container.removeEventListener('touchmove', handleTouchMove);
        container.removeEventListener('touchend', handleTouchEnd);
    };
}

// ============================================
// Skeleton Loading
// ============================================

function showSkeleton(container, type = 'leaderboard', count = 10) {
    if (!container) return;
    
    let html = '';
    
    if (type === 'leaderboard') {
        for (let i = 0; i < count; i++) {
            html += `
                <div class="skeleton-leaderboard-item">
                    <div class="rank-skeleton skeleton"></div>
                    <div class="user-skeleton">
                        <div class="name skeleton"></div>
                        <div class="badges skeleton short"></div>
                    </div>
                    <div class="stats-skeleton">
                        <div class="count skeleton"></div>
                        <div class="velocity skeleton short"></div>
                    </div>
                </div>
            `;
        }
    }
    
    container.innerHTML = html;
}

function hideSkeleton(container) {
    if (container) {
        container.innerHTML = '';
    }
}

// ============================================
// Initialize App
// ============================================

function initApp() {
    // Initialize Telegram Web App
    TelegramWebApp.init();
    TelegramWebApp.applyTheme();
    
    // Listen for theme changes
    if (window.Telegram && window.Telegram.WebApp) {
        window.Telegram.WebApp.onEvent('themeChanged', () => {
            TelegramWebApp.applyTheme();
        });
    }
    
    // Add smooth scroll behavior
    document.addEventListener('click', (e) => {
        if (e.target.matches('a[href^="#"]')) {
            e.preventDefault();
            const target = document.querySelector(e.target.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });
}

// Export for use in other files
window.TelegramWebApp = TelegramWebApp;
window.API = API;
window.UI = UI;
window.initPullToRefresh = initPullToRefresh;
window.showSkeleton = showSkeleton;
window.hideSkeleton = hideSkeleton;
window.initApp = initApp;