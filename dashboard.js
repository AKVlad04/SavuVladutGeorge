    if (!window.__verifSelectedAction) window.__verifSelectedAction = {};
    let selectedAction = window.__verifSelectedAction;
// dashboard.js — renders dashboard panels and handles actions
document.addEventListener('DOMContentLoaded', () => {
        // --- VERIFICATION REQUESTS ---
    let currentUserRole = 'user';
    // determine caller role to adjust which actions are enabled and only then init data
    const rolePromise = fetch('check_auth.php').then(r=>r.json()).then(d=>{
        if (d && d.logged_in && d.role) currentUserRole = d.role.toLowerCase();
        return currentUserRole;
    }).catch(()=>currentUserRole);

    // Panel switching
    const navItems = document.querySelectorAll('.dashboard_nav li');
    const panels = document.querySelectorAll('.dashboard_panel');
    navItems.forEach(item => item.addEventListener('click', () => {
        navItems.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        const target = item.dataset.panel;
        panels.forEach(p => p.style.display = p.id === 'panel-' + target ? '' : 'none');
    }));

    // Fetch overview data (try admin endpoints, fallback to mock)
    // cached data for client-side filtering
    let usersCache = [];
    let nftsCache = [];

    function debounce(fn, wait) {
        let t = null;
        return function(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }
    function fetchOverview() {
        fetch('admin_get_overview.php')
            .then(r => r.json())
            .then(data => renderOverview(data))
            .catch(() => renderOverview(mockOverview()));
    }

    function renderOverview(data) {
        document.getElementById('statUsers').textContent = data.total_users;
        document.getElementById('statNFTs').textContent = data.total_nfts;
        document.getElementById('statVolume').textContent = data.total_volume + ' ETH';
        // --- Sales Volume (Line chart, last 30 days) ---
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesLabels = (data.sales_last_30 || []).map(d => d.date);
        const salesValues = (data.sales_last_30 || []).map(d => d.volume);
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Sales Volume (ETH)',
                    data: salesValues,
                    borderColor: '#00d9ff',
                    backgroundColor: 'rgba(0,217,255,0.12)',
                    tension: 0.3,
                    pointRadius: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true } } }
        });

        // --- Categories Distribution (Doughnut) ---
        const catCtx = document.getElementById('categoriesChart').getContext('2d');
        const categories = data.categories_distribution || [];
        const catLabels = categories.map(c => c.category);
        const catCounts = categories.map(c => c.count);
        const totalCats = catCounts.reduce((s,v)=>s+v,0) || 1;
        const catColors = ["#6366f1","#00d9ff","#10b981","#f59e0b","#ef4444","#84cc16","#8b5cf6"];
        new Chart(catCtx, {
            type: 'doughnut',
            data: { labels: catLabels, datasets: [{ data: catCounts, backgroundColor: catColors.slice(0, catLabels.length) }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const v = ctx.parsed || 0;
                                const pct = ((v/totalCats)*100).toFixed(1);
                                return ctx.label + ': ' + v + ' (' + pct + '%)';
                            }
                        }
                    },
                    legend: { position: 'bottom' }
                }
            }
        });

        // --- Minting Activity (small line chart) ---
        const mintCtx = document.getElementById('mintChart').getContext('2d');
        const mintLabels = (data.mint_activity || []).map(d => d.date);
        const mintValues = (data.mint_activity || []).map(d => d.count);
        new Chart(mintCtx, {
            type: 'line',
            data: { labels: mintLabels, datasets: [{ label: 'Minted', data: mintValues, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.12)', tension:0.3, pointRadius:0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, elements: { point: { radius: 0 } } }
        });
    }

    function mockOverview() {
        const days = 30; const sales = [], mint = [];
        const categories = ["Digital Art","Collectibles","Utility","3D Render","Music/Audio"];
        const catDist = categories.map(c => ({ category: c, count: Math.floor(Math.random()*40)+5 }));
        for (let i = days-1; i >= 0; i--) {
            const d = new Date(); d.setDate(d.getDate()-i);
            const date = d.toISOString().slice(0,10);
            sales.push({ date, volume: +(Math.random()*8).toFixed(2) });
            mint.push({ date, count: Math.floor(Math.random()*6) });
        }
        const totalVol = sales.reduce((s,x)=>s+x.volume,0).toFixed(2);
        return { total_users: 123, total_nfts: 456, total_volume: totalVol, sales_last_30: sales, categories_distribution: catDist, mint_activity: mint };
    }

    // Users table
    function fetchUsers() {
        fetch('admin_get_users.php')
            .then(r => r.json())
            .then(data => {
                usersCache = Array.isArray(data) ? data : [];
                initUserControls();
                filterUsers();
            })
            .catch(() => renderUsersTable(mockUsers()));
    }

    function initUserControls() {
        const search = document.getElementById('userSearch');
        const status = document.getElementById('userStatusFilter');
        if (!search || !status) return;
        const onChange = debounce(() => filterUsers(), 200);
        search.removeEventListener('input', onChange);
        status.removeEventListener('change', onChange);
        search.addEventListener('input', onChange);
        status.addEventListener('change', onChange);
    }

    function filterUsers() {
        const search = (document.getElementById('userSearch') && document.getElementById('userSearch').value || '').toLowerCase();
        const status = (document.getElementById('userStatusFilter') && document.getElementById('userStatusFilter').value) || 'all';
        let filtered = usersCache.slice();
        if (status !== 'all') filtered = filtered.filter(u => (u.status || 'active').toLowerCase() === status);
        if (search) {
            filtered = filtered.filter(u => {
                const s = (u.username||'')+ ' ' + (u.email||'');
                return s.toLowerCase().indexOf(search) !== -1;
            });
        }
        renderUsersTable(filtered);
    }

    function renderUsersTable(users) {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = '';
        users.forEach(u => {
            const tr = document.createElement('tr');
            tr.dataset.rowId = u.id;
            tr.innerHTML = `
                <td>${u.id}</td>
                <td>${u.username}<br><small>${u.email}</small></td>
                <td>${u.wallet || '-'}</td>
                    <td>${u.role}</td>
                    <td>${(u.status ? (u.status.charAt(0).toUpperCase() + u.status.slice(1)) : 'Active')}</td>
                <td class="actions_cell"></td>
            `;

            const actionsCell = tr.querySelector('.actions_cell');

            // If account is disabled, do not show any actions
            if (u.status && u.status.toLowerCase() === 'disabled') {
                actionsCell.textContent = '';
            } else {
                // Ban/Unban button
                // For admins: only show ban button for regular users; hide for owner/admins
                if (!(currentUserRole === 'admin' && !(u.role && u.role.toLowerCase() === 'user'))) {
                    const banBtn = document.createElement('button');
                    banBtn.className = 'btn btn-ban';
                    banBtn.textContent = (u.status && u.status.toLowerCase() === 'banned') ? 'Unban' : 'Ban';
                    banBtn.addEventListener('click', () => adminAction('ban', u.id));
                    actionsCell.appendChild(banBtn);
                }

                // Promote / Demote button — only owner can promote/demote
                if (currentUserRole !== 'admin') {
                    const promoteBtn = document.createElement('button');
                    promoteBtn.className = 'btn btn-promote';
                    const isAdmin = (u.role && u.role.toLowerCase() === 'admin');
                    promoteBtn.textContent = isAdmin ? 'Demote' : 'Promote';
                    // protect owner from changes
                    if (u.role && u.role.toLowerCase() === 'owner') {
                        promoteBtn.disabled = true;
                        promoteBtn.title = 'Cannot change owner role';
                    }
                    promoteBtn.addEventListener('click', () => {
                        const action = isAdmin ? 'demote' : 'promote';
                        adminAction(action, u.id);
                    });
                    actionsCell.appendChild(promoteBtn);
                }

                // Verify button
                const verifyBtn = document.createElement('button');
                verifyBtn.className = 'btn btn-verify';
                verifyBtn.textContent = 'Verify';
                if (currentUserRole === 'admin' && u.role && u.role.toLowerCase() === 'owner') {
                    verifyBtn.disabled = true;
                    verifyBtn.title = 'Cannot verify owner';
                }
                verifyBtn.addEventListener('click', () => adminAction('verify', u.id));
                actionsCell.appendChild(verifyBtn);
            }

            tbody.appendChild(tr);
        });
    }

    function mockUsers() {
        return [
            {id:1, username:'akvlad99', email:'owner@example.com', wallet:'0xabc', role:'owner', is_banned:false},
            {id:2, username:'andrei', email:'andrei@example.com', wallet:'0xdef', role:'user', is_banned:false},
            {id:3, username:'maria', email:'maria@example.com', wallet:'0x123', role:'creator', is_banned:true},
        ];
    }

    // NFTs table
    function fetchNFTs() {
        fetch('admin_get_nfts.php')
            .then(r => r.json())
            .then(data => {
                nftsCache = Array.isArray(data) ? data : [];
                initNftControls();
                filterNfts();
            })
            .catch(() => renderNFTsTable(mockNFTs()));
    }

    function initNftControls() {
        const search = document.getElementById('nftSearch');
        const min = document.getElementById('nftPriceMin');
        const max = document.getElementById('nftPriceMax');
        if (!search || !min || !max) return;
        const onChange = debounce(() => filterNfts(), 200);
        search.removeEventListener('input', onChange);
        min.removeEventListener('input', onChange);
        max.removeEventListener('input', onChange);
        search.addEventListener('input', onChange);
        min.addEventListener('input', onChange);
        max.addEventListener('input', onChange);
    }

    function filterNfts() {
        const search = (document.getElementById('nftSearch') && document.getElementById('nftSearch').value || '').toLowerCase();
        const minVal = parseFloat(document.getElementById('nftPriceMin') && document.getElementById('nftPriceMin').value) || null;
        const maxVal = parseFloat(document.getElementById('nftPriceMax') && document.getElementById('nftPriceMax').value) || null;
        let filtered = nftsCache.slice();
        if (!Array.isArray(filtered)) filtered = [];
        if (minVal !== null) filtered = filtered.filter(n => parseFloat(n.price) >= minVal);
        if (maxVal !== null) filtered = filtered.filter(n => parseFloat(n.price) <= maxVal);
        if (search) {
            filtered = filtered.filter(n => {
                const s = (n.name||'') + ' ' + (n.creator||'') + ' ' + (n.owner||'');
                return s.toLowerCase().indexOf(search) !== -1;
            });
        }
        renderNFTsTable(filtered);
    }

    function renderNFTsTable(items) {
        const tbody = document.querySelector('#nftsTable tbody');
        tbody.innerHTML = '';
        items.forEach(n => {
            const tr = document.createElement('tr');
            tr.dataset.rowId = n.id;
            tr.innerHTML = `
                <td><img src="${n.thumbnail}" style="width:48px;height:48px;object-fit:cover;border-radius:6px"></td>
                <td>${n.name}</td>
                <td><a href="profile.html">${n.creator}</a></td>
                <td>${n.owner}</td>
                <td>${n.price} ETH</td>
                <td>${n.created_at}</td>
                <td>
                    <button class="btn btn-del">Delete</button>
                    <button class="btn btn-feature">Feature</button>
                </td>
            `;
            tr.querySelector('.btn-del').addEventListener('click', () => adminAction('delete_nft', n.id));
            tr.querySelector('.btn-feature').addEventListener('click', () => adminAction('feature_nft', n.id));
            tbody.appendChild(tr);
        });
    }

    function mockNFTs() {
        return [
            {id:11, thumbnail:'img/galactic.jpg', name:'Cyber Dreams', creator:'akvlad99', owner:'andrei', price:2.5, created_at:'2025-10-01'},
            {id:12, thumbnail:'img/neon.jpg', name:'Giga Cats', creator:'creator1', owner:'maria', price:1.2, created_at:'2025-11-04'}
        ];
    }

    // Reports
    function fetchReports() {
        fetch('admin_get_reports.php')
            .then(r => r.json())
            .then(data => renderReportsTable(data))
            .catch(() => renderReportsTable(mockReports()));
    }

    function renderReportsTable(reports) {
        const tbody = document.querySelector('#reportsTable tbody');
        tbody.innerHTML = '';
        reports.forEach(r => {
            const tr = document.createElement('tr');
            tr.dataset.rowId = r.id;
            tr.innerHTML = `
                <td><a href="explore.html">${r.item_name}</a></td>
                <td>${r.reason}</td>
                <td>${r.reported_by}</td>
                <td>
                    <button class="btn btn-dismiss">Dismiss</button>
                    <button class="btn btn-delete">Delete Item</button>
                </td>
            `;
            tr.querySelector('.btn-dismiss').addEventListener('click', () => adminAction('dismiss_report', r.id));
            tr.querySelector('.btn-delete').addEventListener('click', () => adminAction('delete_nft', r.item_id));
            tbody.appendChild(tr);
        });
    }

    function mockReports() {
        return [
            {id:1, item_id:11, item_name:'Cyber Dreams', reason:'Fake content', reported_by:'andrei'},
        ];
    }

    // Transactions
    function fetchTransactions() {
        fetch('admin_get_transactions.php')
            .then(r => r.json())
            .then(data => renderTransactions(data))
            .catch(() => renderTransactions(mockTransactions()));
    }

    function renderTransactions(tx) {
        const div = document.getElementById('transactionsList');
        div.innerHTML = tx.map(t => `<div class="tx_row">[${t.time}] ${t.actor} ${t.action} ${t.target} for ${t.amount} ETH</div>`).join('');
    }

    function mockTransactions() {
        return [
            {time:'12:30 PM', actor:"Andrei", action:'bought', target:"Monkey #5 from Maria", amount:0.5},
            {time:'1:05 PM', actor:"Luca", action:'listed', target:"Galaxy #3", amount:2.0},
        ];
    }

    // --- VERIFICATION REQUESTS TAB ---

    // Fix: define handleVerificationAction for approve/reject/confirm
    function handleVerificationAction(id, action) {
    if (action === 'approve') return adminAction('verification_approve', id, true);
    if (action === 'reject') return adminAction('verification_reject', id, true);
    // fallback for legacy
    return adminAction(action, id, true);
}
    function fetchVerifications() {
        fetch('admin_get_verifications.php')
            .then(r => r.json())
            .then(data => renderVerifications(data))
            .catch(() => renderVerifications([]));
    }
    function renderVerifications(requests) {
    console.log('Verification requests data:', requests);
    const tbody = document.querySelector('#verificationsTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!Array.isArray(requests) || requests.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="5" style="text-align:center;color:#aaa">No pending requests</td>';
        tbody.appendChild(tr);
        return;
    }
    requests.forEach(req => {
        const tr = document.createElement('tr');
        tr.dataset.reqId = req.id;
        let approveActive = selectedAction[req.id] === 'approve';
        let rejectActive = selectedAction[req.id] === 'reject';
        tr.innerHTML = `
            <td>${req.username}</td>
            <td>${req.email}</td>
            <td>${req.description}</td>
            <td><button class="img_view_btn" data-img="${req.image_url}">View</button></td>
            <td class="verif_actions_cell">
                <button class="approve_btn${approveActive ? ' active pressed' : ''}" data-id="${req.id}">Approve</button>
                <button class="reject_btn${rejectActive ? ' active pressed' : ''}" data-id="${req.id}">Reject</button>
                <button class="confirm_btn" data-id="${req.id}">Confirm</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Approve/reject doar selectează acțiunea (highlight)
    tbody.querySelectorAll('.approve_btn').forEach(btn => {
        btn.onclick = function() {
            const reqId = this.dataset.id;
            Object.keys(selectedAction).forEach(k => delete selectedAction[k]);
            selectedAction[reqId] = 'approve';
            renderVerifications(requests.map(r => Object.assign({}, r)));
        };
    });
    tbody.querySelectorAll('.reject_btn').forEach(btn => {
        btn.onclick = function() {
            const reqId = this.dataset.id;
            Object.keys(selectedAction).forEach(k => delete selectedAction[k]);
            selectedAction[reqId] = 'reject';
            renderVerifications(requests.map(r => Object.assign({}, r)));
        };
    });
    // Confirm trimite efectiv acțiunea
    tbody.querySelectorAll('.confirm_btn').forEach(btn => {
        btn.onclick = async function() {
            const reqId = this.dataset.id;
            const action = selectedAction[reqId];
            if (!action) return;
            let result = null;
            if (typeof handleVerificationAction === 'function') {
                if (action === 'approve') result = await handleVerificationAction(reqId, 'verification_approve');
                if (action === 'reject') result = await handleVerificationAction(reqId, 'verification_reject');
            }
            // Feedback vizibil eliminat la cerere (UX mai curat)
            Object.keys(selectedAction).forEach(k => delete selectedAction[k]);
            setTimeout(() => fetchVerifications(), 300); // Refresh după acțiune
        };
    });
    // Image preview
    tbody.querySelectorAll('.img_view_btn').forEach(btn => {
        btn.addEventListener('click', function() {
            showImgPopup(this.dataset.img);
        });
    });
}
    // Image popup logic
    function showImgPopup(imgUrl) {
        const overlay = document.getElementById('imgPopupOverlay');
        const img = document.getElementById('imgPopupImg');
        const closeBtn = document.getElementById('imgPopupClose');
        img.src = imgUrl;
        overlay.style.display = 'flex';
        closeBtn.onclick = function() {
            overlay.style.display = 'none';
            img.src = '';
        };
    }

    // Generic admin action handler (sends to admin_action.php if available)
    async function adminAction(action, id, returnJson) {
    try {
        console.log('[adminAction] Sending:', {action, id});
        const resp = await fetch('admin_action.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action, id})
        });
        let data = {};
        try { data = await resp.json(); } catch(e) { /* ignore parse errors */ }
        console.log('[adminAction] Response:', resp.status, data);
        if (returnJson) return data;
        if (resp.ok && data && (data.ok || data.status)) {
            // refresh only the affected sections
            switch(action) {
                case 'ban':
                case 'promote':
                case 'demote':
                case 'verify':
                case 'verification_approve':
                case 'verification_reject':
                    fetchUsers();
                    fetchVerifications && fetchVerifications();
                    break;
                case 'delete_nft':
                    fetchNFTs();
                    fetchReports();
                    break;
                case 'feature_nft':
                    fetchNFTs();
                    break;
                case 'dismiss_report':
                    fetchReports();
                    break;
                default:
                    // fallback: refresh users and nfts
                    fetchUsers(); fetchNFTs(); fetchReports();
            }
        } else {
            console.error('Action failed', action, id, data);
            // flash the affected row briefly to indicate failure
            const row = document.querySelector(`[data-row-id='${id}']`);
            if (row) {
                const orig = row.style.background;
                row.style.transition = 'background 0.3s';
                row.style.background = 'rgba(239,68,68,0.08)';
                setTimeout(()=>{ row.style.background = orig; }, 800);
            }
        }
    } catch (err) {
        console.error('Network or server error for adminAction', err);
        const row = document.querySelector(`[data-row-id='${id}']`);
        if (row) {
            const orig = row.style.background;
            row.style.transition = 'background 0.3s';
            row.style.background = 'rgba(239,68,68,0.08)';
            setTimeout(()=>{ row.style.background = orig; }, 800);
        }
    }
}

    // Init after we know the caller role to avoid race conditions
    rolePromise.then(()=>{
        fetchOverview();
        fetchUsers();
        fetchNFTs();
        fetchReports();
        fetchTransactions();
    });

    // Încarcă cererile de verificare la inițializare și la click pe tab
    const verifTab = document.querySelector('li[data-panel="verifications"]');
    if (verifTab) {
        verifTab.addEventListener('click', function() {
            fetchVerifications();
        });
        // Încarcă automat dacă tab-ul e activ la load
        if (verifTab.classList.contains('active')) {
            fetchVerifications();
        }
    } else {
        // Dacă nu există tab, încarcă oricum la inițializare
        fetchVerifications();
    }

    // Expose for debugging
    window.__dashboard = { fetchOverview, fetchUsers, fetchNFTs, fetchReports, fetchTransactions, filterUsers, filterNfts, usersCache, nftsCache };
});
