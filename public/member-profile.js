(function () {
    function applySidebarName(name) {
        const nameElement = document.getElementById('user-name');
        if (nameElement) nameElement.textContent = name || '';
    }

    function applySidebarAvatar(photoPath, name) {
        const profileMini = document.querySelector('.user-profile-mini');
        if (!profileMini) return;

        const icon = profileMini.querySelector('i');
        let avatar = profileMini.querySelector('#sidebarAvatar');
        if (photoPath) {
            if (!avatar) {
                avatar = document.createElement('img');
                avatar.id = 'sidebarAvatar';
                profileMini.insertBefore(avatar, profileMini.querySelector('.user-info'));
            }
            avatar.alt = name ? `${name} profile picture` : 'Profile picture';
            avatar.src = `../${photoPath.replace(/^\/+/, '')}?v=${Date.now()}`;
            avatar.style.display = 'block';
            if (icon) icon.style.display = 'none';
        } else {
            if (avatar) avatar.remove();
            if (icon) icon.style.display = '';
        }
    }

    window.applySidebarName = applySidebarName;
    window.applySidebarAvatar = applySidebarAvatar;

    async function syncMemberProfile() {
        try {
            const response = await fetch('../api/user-data.php', {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const data = await response.json();
            if (!data.loggedIn) return;

            const user = data.user || {};
            const displayName = data.fullName || data.fullname || user.fullname || user.email || data.email || '';
            const nickname = data.nickname || user.nickname || '';
            const photoPath = data.profile_photo || user.profile_photo || '';
            const activeName = nickname || displayName;

            applySidebarName(activeName);
            applySidebarAvatar(photoPath, activeName);

            if (data.email) localStorage.setItem('userEmail', data.email);
        } catch (error) {
            console.error('Unable to synchronize profile sidebar:', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncMemberProfile);
    } else {
        syncMemberProfile();
    }
})();
