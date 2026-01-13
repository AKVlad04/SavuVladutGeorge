document.addEventListener('DOMContentLoaded', () => {
    fetch('check_auth.php')
        .then(res => res.json())
        .then(data => {
            console.log('owner.js check_auth response:', data);
            if (data.logged_in && data.role) {
                const role = data.role.toLowerCase();
                // expose current role on body for debugging
                try { document.body.dataset.role = role; } catch(e) {}
                if (role === 'owner' || role === 'admin') {
                let el = document.getElementById('ownerDashboardLink');
                // If link exists, ensure it has the correct nav class and is visible
                if (el) {
                    el.className = 'profile_button';
                    el.style.setProperty('display', 'inline-block', 'important');
                    el.style.visibility = 'visible';
                    console.log('owner.js: revealed existing dashboard link');
                } else {
                    // try to insert into nav if missing
                    const nav = document.querySelector('.buttons_footer');
                    if (nav) {
                        el = document.createElement('a');
                        el.id = 'ownerDashboardLink';
                        el.href = 'dashboard.html';
                        el.className = 'profile_button';
                        el.textContent = 'Dashboard';
                        el.style.setProperty('display', 'inline-block', 'important');
                        el.style.visibility = 'visible';
                        console.log('owner.js: inserted dashboard link into nav');
                        nav.appendChild(el);
                    }
                }
                } else {
                    console.log('owner.js: user role is', role, '- dashboard hidden');
                }

                // hide Create links for users who are not verified
                if (typeof data.is_verified !== 'undefined' && !data.is_verified) {
                    const createLinks = document.querySelectorAll('a[href="create.html"], a.create_button');
                    createLinks.forEach(el => {
                        el.style.display = 'none';
                    });
                    console.log('owner.js: hid create links for unverified user');
                }
            }

            // Nu mai modifica butonul de login/logout aici, lasă script.js să controleze complet stilul și textul pentru consistență pe toate paginile.
        })
        .catch(err => {
            // silent fail
            console.error('owner.js error', err);
        });
});
