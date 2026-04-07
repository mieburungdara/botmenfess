/**
 * User Profile Page JavaScript
 */

let profileData = null;

async function loadProfile() {
    const telegramId = TelegramWebApp.getUserId();
    
    if (!telegramId) {
        UI.showToast('Tidak dapat mengakses profil', 'error');
        return;
    }
    
    UI.showLoading();
    
    try {
        const response = await API.get('profile.php', { telegram_id: telegramId });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to load profile');
        }
        
        profileData = response.profile;
        renderProfile(response.profile);
        
    } catch (error) {
        console.error('Error loading profile:', error);
        UI.showToast('Gagal memuat profil', 'error');
    } finally {
        UI.hideLoading();
    }
}

function renderProfile(profile) {
    // Render header
    const headerHtml = `
        <div class="profile-header">
            <div class="profile-avatar">${(profile.user.first_name || 'U').charAt(0).toUpperCase()}</div>
            <div class="profile-name">${escapeHtml(profile.user.first_name || 'User')}</div>
            <div class="profile-username">${profile.user.username ? '@' + escapeHtml(profile.user.username) : ''}</div>
            <div style="margin-top:8px;font-size:13px;color:var(--text-muted)">
                Bergabung ${UI.formatDate(profile.user.first_seen)}
            </div>
        </div>
    `;
    document.getElementById('profile-header').innerHTML = headerHtml;
    
    // Render personal rank
    if (profile.personal_rank) {
        const rank = profile.personal_rank;
        document.getElementById('personal-rank').innerHTML = `
            <div class="personal-rank">
                <div class="rank-label">Posisi kamu di leaderboard</div>
                <div class="rank-number">#${rank.rank}</div>
                <div class="stats">
                    <div class="stat">
                        <div class="stat-value">${rank.comment_count}</div>
                        <div class="stat-label">Komentar</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">${rank.comment_velocity}</div>
                        <div class="stat-label">Per bulan</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">${UI.formatHour(rank.most_active_hour)}</div>
                        <div class="stat-label">Paling Aktif</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Render stats
    const stats = profile.stats;
    document.getElementById('total-comments').textContent = stats.total_comments || 0;
    document.getElementById('comment-velocity').textContent = stats.comment_velocity || 0;
    document.getElementById('streak').textContent = (stats.streak || 0) + ' hari';
    
    // Render badges
    renderBadges(profile.badges || []);
    
    // Render activity heatmap
    renderHeatmap(stats.most_active_hours || []);
    
    // Render daily activity chart
    renderDailyActivity(profile.daily_activity || []);
    
    // Render comment history
    renderCommentHistory(profile.comment_history || []);
}

function renderBadges(badges) {
    const container = document.getElementById('badges-container');
    
    if (badges.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted)">Belum ada badge. Mulai berkomentar untuk mendapatkan badge!</div>';
        return;
    }
    
    let html = '<div class="badge-grid">';
    
    badges.forEach(badge => {
        const badgeInfo = BADGES[badge.badge_key] || {};
        html += `
            <div class="badge-item badge-earned" title="${badgeInfo.description || badge.badge_key}">
                <span class="badge-icon">${badgeInfo.icon || '🏅'}</span>
                <div class="badge-name">${badgeInfo.name || badge.badge_key}</div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function renderHeatmap(activeHours) {
    const container = document.getElementById('heatmap');
    
    // Create 24-hour array with counts
    const hourCounts = new Array(24).fill(0);
    activeHours.forEach(h => {
        const hour = parseInt(h.hour);
        const count = parseInt(h.count) || 0;
        if (!isNaN(hour) && hour >= 0 && hour < 24) {
            hourCounts[hour] = count;
        }
    });
    
    const maxCount = Math.max(...hourCounts, 1);
    
    let html = '<div class="heatmap">';
    for (let i = 0; i < 24; i++) {
        const count = hourCounts[i];
        const level = count === 0 ? '' : 
                      count < maxCount * 0.25 ? 'level-1' :
                      count < maxCount * 0.5 ? 'level-2' :
                      count < maxCount * 0.75 ? 'level-3' : 'level-4';
        html += `<div class="heatmap-cell ${level}" title="${i}:00 - ${count} komentar"></div>`;
    }
    html += '</div>';
    html += '<div class="heatmap-labels"><span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span></div>';
    
    container.innerHTML = html;
}

function renderDailyActivity(activity) {
    const container = document.getElementById('daily-activity');
    
    if (activity.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted)">Belum ada aktivitas</div>';
        return;
    }
    
    const maxCount = Math.max(...activity.map(d => parseInt(d.count)), 1);
    
    let html = '<div class="activity-chart">';
    activity.forEach(day => {
        const height = (parseInt(day.count) / maxCount) * 100;
        const date = new Date(day.date);
        html += `<div class="activity-bar" style="height:${height}%" title="${date.toLocaleDateString('id-ID')}: ${day.count} komentar"></div>`;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function renderCommentHistory(history) {
    const container = document.getElementById('comment-history');
    
    if (history.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted)">Belum ada komentar</div>';
        return;
    }
    
    let html = '<ul class="comment-history">';
    
    history.forEach(comment => {
        const commentText = escapeHtml(comment.comment_text);
        const submissionText = comment.submission_text ? 
            ' pada "' + escapeHtml(comment.submission_text.substring(0, 50)) + '..."' : '';
        
        html += `
            <li class="comment-item">
                <div class="comment-text">"${commentText}"</div>
                <div class="comment-meta">${UI.formatDate(comment.created_at)}${submissionText}</div>
            </li>
        `;
    });
    
    html += '</ul>';
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Badge definitions - will be loaded from shared app.js if available, or from API
let BADGES = typeof window.BADGES !== 'undefined' ? window.BADGES : {};

/**
 * Ensure BADGES are loaded before rendering
 */
async function ensureBadgesLoaded() {
    if (Object.keys(BADGES).length === 0) {
        // Try to load from API
        try {
            const response = await API.get('badges.php');
            if (response.success && response.badges) {
                BADGES = response.badges;
            }
        } catch (error) {
            console.warn('Failed to load badges from API, using fallback:', error);
            loadFallbackBadges();
        }
    }
}

/**
 * Fallback badge definitions for when API fails
 */
function loadFallbackBadges() {
    BADGES = {
        'weekly_champion': { name: 'Weekly Champion', icon: '🏅', description: 'Rank #1 mingguan' },
        'monthly_legend': { name: 'Monthly Legend', icon: '🌟', description: 'Top 3 bulanan' },
        'alltime_king': { name: 'All-Time King', icon: '👑', description: 'Rank #1 all-time' },
        'rising_star': { name: 'Rising Star', icon: '⚡', description: 'Velocity tertinggi minggu ini' },
        'active_commenter': { name: 'Active Commenter', icon: '💬', description: '100+ komentar' },
        'newcomer': { name: 'Newcomer', icon: '🌱', description: 'Komentar pertama' },
        'early_bird': { name: 'Early Bird', icon: '🐦', description: '50%+ komentar jam 06-09' },
        'night_owl': { name: 'Night Owl', icon: '🦉', description: '50%+ komentar jam 23-02' },
        'streak_master': { name: 'Streak Master', icon: '🔥', description: '7+ hari berturut-turut' },
        'conversation_starter': { name: 'Conversation Starter', icon: '💭', description: 'Submission dengan 20+ komentar' }
    };
}

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    // Initialize app first
    initApp();
    
    // Ensure badges are loaded before rendering profile
    await ensureBadgesLoaded();
    
    // Show back button
    TelegramWebApp.showBackButton(() => {
        window.location.href = 'leaderboard.html';
    });
    
    // Load profile
    loadProfile();
    
    // Setup pull to refresh
    initPullToRefresh(() => loadProfile());
});
