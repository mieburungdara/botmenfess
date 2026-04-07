/**
 * Admin Dashboard JavaScript
 */

let adminStats = null;

async function loadAdminStats() {
    const telegramId = TelegramWebApp.getUserId();
    
    if (!telegramId) {
        UI.showToast('Tidak dapat mengakses admin', 'error');
        return;
    }
    
    UI.showLoading();
    
    try {
        const response = await API.get('admin.php', { telegram_id: telegramId });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to load admin stats');
        }
        
        adminStats = response.stats;
        renderAdminStats(response.stats);
        
    } catch (error) {
        console.error('Error loading admin stats:', error);
        UI.showToast('Gagal memuat statistik admin', 'error');
    } finally {
        UI.hideLoading();
    }
}

function renderAdminStats(stats) {
    // Render overview stats with null checks
    const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    
    setEl('total-users', stats.total_users || 0);
    setEl('shadow-banned', stats.shadow_banned_users || 0);
    setEl('total-comments', UI.formatNumber(stats.total_comments || 0));
    setEl('total-submissions', UI.formatNumber(stats.total_submissions || 0));
    setEl('comments-today', stats.comments_today || 0);
    
    // Render cache status
    renderCacheStatus(stats.cache_status || []);
    
    // Render recent activity
    renderRecentActivity(stats.recent_activity || []);
}

function renderCacheStatus(cacheStatus) {
    const container = document.getElementById('cache-status');
    
    if (cacheStatus.length === 0) {
        container.innerHTML = '<div style="color:var(--text-muted)">Belum ada cache</div>';
        return;
    }
    
    let html = '<div class="card">';
    html += '<div class="card-title">Status Cache Leaderboard</div>';
    
    cacheStatus.forEach(cache => {
        const safeTimeframe = escapeHtml(cache.timeframe || 'unknown');
        let generatedAt;
        try {
            generatedAt = new Date(cache.generated_at);
        } catch (e) {
            generatedAt = new Date();
        }
        const now = new Date();
        const hoursOld = (now - generatedAt) / (1000 * 60 * 60);
        const isExpired = hoursOld >= 24;
        const hoursDisplay = isNaN(hoursOld) ? '?' : Math.round(hoursOld);
        
        html += `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-color)">
                <div>
                    <strong>${safeTimeframe}</strong>
                    <div style="font-size:12px;color:var(--text-muted)">${generatedAt.toLocaleString('id-ID')}</div>
                </div>
                <div>
                    ${isExpired ? 
                        '<span style="color:var(--error-color)">Expired</span>' : 
                        `<span style="color:var(--success-color)">${hoursDisplay} jam lalu</span>`
                    }
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderRecentActivity(activity) {
    const container = document.getElementById('recent-activity');
    
    if (activity.length === 0) {
        container.innerHTML = '<div style="color:var(--text-muted)">Belum ada aktivitas</div>';
        return;
    }
    
    const actionLabels = {
        'created': 'Komentar baru',
        'deleted': 'Komentar dihapus',
        'shadow_banned': 'Shadow banned'
    };
    
    let html = '<ul class="activity-log">';
    
    activity.forEach(item => {
        const safeAction = escapeHtml(actionLabels[item.action] || item.action || 'Unknown');
        const safeUsername = escapeHtml(item.username || 'Anonymous');
        const safeCommentText = escapeHtml(item.comment_text?.substring(0, 50) || '');
        const safeDate = escapeHtml(UI.formatDate(item.created_at));
        
        html += `
            <li>
                <strong>${safeAction}</strong> oleh ${safeUsername}
                <br>
                <span style="font-size:12px;color:var(--text-muted)">"${safeCommentText}..."</span>
                <br>
                <span class="timestamp">${safeDate}</span>
            </li>
        `;
    });
    
    html += '</ul>';
    container.innerHTML = html;
}

async function rebuildCache() {
    const telegramId = TelegramWebApp.getUserId();
    
    if (!telegramId) {
        UI.showToast('Unauthorized', 'error');
        return;
    }
    
    UI.showLoading();
    
    try {
        const response = await API.post('admin.php', {
            action: 'rebuild_cache',
            admin_id: telegramId
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to rebuild cache');
        }
        
        UI.showToast('Cache berhasil di-rebuild!', 'success');
        
        // Reload stats
        await loadAdminStats();
        
    } catch (error) {
        console.error('Error rebuilding cache:', error);
        UI.showToast('Gagal rebuild cache: ' + error.message, 'error');
    } finally {
        UI.hideLoading();
    }
}

async function shadowBanUser(telegramId) {
    const adminId = TelegramWebApp.getUserId();
    
    if (!adminId || !telegramId) {
        UI.showToast('Invalid input', 'error');
        return;
    }
    
    if (!confirm('Shadow ban user ' + telegramId + '? Komentar mereka tidak akan dihitung di leaderboard.')) {
        return;
    }
    
    UI.showLoading();
    
    try {
        const response = await API.post('admin.php', {
            action: 'shadow_ban',
            admin_id: adminId,
            telegram_id: telegramId
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to shadow ban user');
        }
        
        UI.showToast('User berhasil di-shadow ban', 'success');
        await loadAdminStats();
        
    } catch (error) {
        console.error('Error shadow banning user:', error);
        UI.showToast('Gagal shadow ban user: ' + error.message, 'error');
    } finally {
        UI.hideLoading();
    }
}

async function unshadowBanUser(telegramId) {
    const adminId = TelegramWebApp.getUserId();
    
    if (!adminId || !telegramId) {
        UI.showToast('Invalid input', 'error');
        return;
    }
    
    UI.showLoading();
    
    try {
        const response = await API.post('admin.php', {
            action: 'unshadow_ban',
            admin_id: adminId,
            telegram_id: telegramId
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to unshadow ban user');
        }
        
        UI.showToast('User berhasil di-unshadow ban', 'success');
        await loadAdminStats();
        
    } catch (error) {
        console.error('Error unshadow banning user:', error);
        UI.showToast('Gagal unshadow ban user: ' + error.message, 'error');
    } finally {
        UI.hideLoading();
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initApp();
    
    // Show back button
    TelegramWebApp.showBackButton(() => {
        window.location.href = 'leaderboard.html';
    });
    
    // Load admin stats
    loadAdminStats();
    
    // Setup pull to refresh
    initPullToRefresh(() => loadAdminStats());
});