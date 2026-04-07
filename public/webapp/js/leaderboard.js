/**
 * Leaderboard Page JavaScript
 */

let currentTimeframe = 'alltime';
let leaderboardData = null;
let badgesCache = {};

async function loadLeaderboard(timeframe = 'alltime') {
    currentTimeframe = timeframe;
    const container = document.getElementById('leaderboard-container');
    const telegramId = TelegramWebApp.getUserId();
    
    // Show skeleton
    showSkeleton(container, 'leaderboard', 10);
    
    try {
        const params = { timeframe };
        if (telegramId) {
            params.telegram_id = telegramId;
        }
        
        const response = await API.get('leaderboard.php', params);
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to load leaderboard');
        }
        
        leaderboardData = response;
        renderLeaderboard(response);
        renderPersonalRank(response.personal_rank);
        renderStats(response.stats);
        
        // Update timestamp
        const lastUpdatedEl = document.getElementById('last-updated');
        if (lastUpdatedEl && response.generated_at) {
            lastUpdatedEl.textContent = 
                'Terakhir diperbarui: ' + new Date(response.generated_at).toLocaleString('id-ID');
        }
        
    } catch (error) {
        console.error('Error loading leaderboard:', error);
        UI.showToast('Gagal memuat leaderboard', 'error');
    }
}

function renderLeaderboard(data) {
    const container = document.getElementById('leaderboard-container');
    const leaderboard = data.leaderboard || [];
    
    if (leaderboard.length === 0) {
        container.innerHTML = '<div class="card" style="text-align:center;padding:40px;color:var(--text-muted)">Belum ada data leaderboard</div>';
        return;
    }
    
    let html = '<ul class="leaderboard-list">';
    
    leaderboard.forEach((user, index) => {
        const rankClass = user.rank <= 3 ? `rank-${user.rank}` : '';
        const medal = UI.getMedalEmoji(user.rank);
        const trendIcon = UI.getTrendIcon(user.trend);
        const trendClass = UI.getTrendClass(user.trend);
        
        // Build badges HTML
        let badgesHtml = '';
        if (user.badges && user.badges.length > 0) {
            const displayBadges = user.badges.slice(0, 4);
            badgesHtml = '<div class="user-badges">';
            displayBadges.forEach(badge => {
                const badgeInfo = BADGES[badge.badge_key] || {};
                badgesHtml += `<span class="badge" title="${badgeInfo.name || badge.badge_key}">${badgeInfo.icon || '🏅'}</span>`;
            });
            if (user.badges.length > 4) {
                badgesHtml += `<span class="badge">+${user.badges.length - 4}</span>`;
            }
            badgesHtml += '</div>';
        }
        
        html += `
            <li class="leaderboard-item ${rankClass} fade-in" style="animation-delay:${index * 0.05}s">
                <div class="rank">
                    <span class="rank-medal">${medal}</span>
                </div>
                <div class="user-info">
                    <div class="username">${escapeHtml(user.username)}</div>
                    ${badgesHtml}
                </div>
                <div class="user-stats">
                    <div class="comment-count">${UI.formatNumber(user.comment_count)}</div>
                    <div class="comment-velocity">${user.comment_velocity}/bulan</div>
                    <span class="trend ${trendClass}">${trendIcon}</span>
                </div>
            </li>
        `;
    });
    
    html += '</ul>';
    container.innerHTML = html;
}

function renderPersonalRank(rank) {
    const container = document.getElementById('personal-rank');
    
    if (!rank || !rank.comment_count || rank.comment_count === 0) {
        container.innerHTML = `
            <div class="personal-rank" style="text-align:center">
                <div class="rank-label">Kamu belum memiliki komentar</div>
                <div style="margin-top:8px;font-size:14px;opacity:0.8">Mulai berkomentar untuk masuk leaderboard!</div>
            </div>
        `;
        return;
    }
    
    // Sanitize values before rendering
    const safeRank = parseInt(rank.rank) || 0;
    const safeCommentCount = parseInt(rank.comment_count) || 0;
    const safeCommentVelocity = escapeHtml(String(rank.comment_velocity || '0'));
    const safeActiveHour = escapeHtml(UI.formatHour(rank.most_active_hour));
    
    container.innerHTML = `
        <div class="personal-rank">
            <div class="rank-label">Posisi kamu</div>
            <div class="rank-number">#${safeRank}</div>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value">${safeCommentCount}</div>
                    <div class="stat-label">Komentar</div>
                </div>
                <div class="stat">
                    <div class="stat-value">${safeCommentVelocity}</div>
                    <div class="stat-label">Per bulan</div>
                </div>
                <div class="stat">
                    <div class="stat-value">${safeActiveHour}</div>
                    <div class="stat-label">Paling Aktif</div>
                </div>
            </div>
        </div>
    `;
}

function renderStats(stats) {
    const totalCommentsEl = document.getElementById('total-comments');
    const totalCommentersEl = document.getElementById('total-commenters');
    const totalSubmissionsEl = document.getElementById('total-submissions');
    
    if (totalCommentsEl) {
        totalCommentsEl.textContent = UI.formatNumber(stats?.total_comments || 0);
    }
    if (totalCommentersEl) {
        totalCommentersEl.textContent = UI.formatNumber(stats?.total_commenters || 0);
    }
    if (totalSubmissionsEl) {
        totalSubmissionsEl.textContent = UI.formatNumber(stats?.total_submissions || 0);
    }
}

function switchTab(timeframe) {
    // Update active tab
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.timeframe === timeframe) {
            tab.classList.add('active');
        }
    });
    
    // Haptic feedback
    TelegramWebApp.hapticFeedback('light');
    
    // Load new data
    loadLeaderboard(timeframe);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Badge definitions - will be loaded from API if not available from PHP config
let BADGES = {};

/**
 * Load badges from API or use fallback definitions
 */
async function loadBadges() {
    try {
        const response = await API.get('badges.php');
        if (response.success && response.badges) {
            BADGES = response.badges;
        } else {
            loadFallBackBadges();
        }
    } catch (error) {
        console.warn('Failed to load badges from API, using fallback:', error);
        loadFallBackBadges();
    }
}

/**
 * Fallback badge definitions for when API fails
 */
function loadFallBackBadges() {
    BADGES = {
        'weekly_champion': { name: 'Weekly Champion', icon: '🏅' },
        'monthly_legend': { name: 'Monthly Legend', icon: '🌟' },
        'alltime_king': { name: 'All-Time King', icon: '👑' },
        'rising_star': { name: 'Rising Star', icon: '⚡' },
        'active_commenter': { name: 'Active Commenter', icon: '💬' },
        'newcomer': { name: 'Newcomer', icon: '🌱' },
        'early_bird': { name: 'Early Bird', icon: '🐦' },
        'night_owl': { name: 'Night Owl', icon: '🦉' },
        'streak_master': { name: 'Streak Master', icon: '🔥' },
        'conversation_starter': { name: 'Conversation Starter', icon: '💭' }
    };
}

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    // Initialize app first
    initApp();
    
    // Load badge definitions from API
    await loadBadges();
    
    // Load initial leaderboard data
    loadLeaderboard('alltime');
    
    // Setup pull to refresh
    initPullToRefresh(() => loadLeaderboard(currentTimeframe));
    
    // Auto refresh every 5 minutes (reduced from 60 seconds for better UX and server load)
    setInterval(() => {
        loadLeaderboard(currentTimeframe);
    }, 300000);
});
