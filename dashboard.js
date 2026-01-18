    if (!window.__verifSelectedAction) window.__verifSelectedAction = {};
    let selectedAction = window.__verifSelectedAction;
    if (!window.__nftVerifSelectedAction) window.__nftVerifSelectedAction = {};
    let nftSelectedAction = window.__nftVerifSelectedAction;
// dashboard.js — renders dashboard panels and handles actions
document.addEventListener('DOMContentLoaded', () => {
        // --- VERIFICATION REQUESTS ---
    let currentUserRole = 'user';
    let currentUsername = null;
    let currentReportsMode = 'open';
    // determine caller role to adjust which actions are enabled and only then init data
    const rolePromise = fetch('check_auth.php').then(r=>r.json()).then(d=>{
        if (d && d.logged_in) {
            if (d.role) currentUserRole = d.role.toLowerCase();
            if (d.username) currentUsername = d.username;
        }
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

        // --- Top Creators (Last 30 days, by volume) ---
        const topCreators = data.top_creators || [];
        const topCreatorsCanvas = document.getElementById('topCreatorsChart');
        if (topCreatorsCanvas && topCreators.length) {
            const tcCtx = topCreatorsCanvas.getContext('2d');
            const tcLabels = topCreators.map(c => c.username);
            const tcValues = topCreators.map(c => c.volume);
            new Chart(tcCtx, {
                type: 'bar',
                data: {
                    labels: tcLabels,
                    datasets: [{
                        label: 'Volume (ETH)',
                        data: tcValues,
                        backgroundColor: 'rgba(99,102,241,0.6)',
                        borderColor: '#6366f1',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }
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
        // mock top creators (5 entries)
        const mockTopCreators = [
            { creator_id: 1, username: 'creator_one', volume: 12.5 },
            { creator_id: 2, username: 'creator_two', volume: 9.3 },
            { creator_id: 3, username: 'creator_three', volume: 7.1 },
            { creator_id: 4, username: 'creator_four', volume: 5.4 },
            { creator_id: 5, username: 'creator_five', volume: 3.2 }
        ];
        return {
            total_users: 123,
            total_nfts: 456,
            total_volume: totalVol,
            sales_last_30: sales,
            categories_distribution: catDist,
            mint_activity: mint,
            top_creators: mockTopCreators
        };
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
                <td><span class="user_link user-row-link" data-username="${u.username}">${u.username}</span><br><small>${u.email}</small></td>
                <td>${u.wallet || '-'}</td>
                <td>${u.role}</td>
                <td>${(u.status ? (u.status.charAt(0).toUpperCase() + u.status.slice(1)) : 'Active')}</td>
                <td>${(u.is_verified === 1 || u.is_verified === '1' || u.is_verified === true) ? '<span style="color:#009900;font-weight:bold">Verified</span>' : '-'}</td>
                <td class="actions_cell"></td>
            `;

            const actionsCell = tr.querySelector('.actions_cell');

            // If account is disabled, do not show any actions
            if (u.status && u.status.toLowerCase() === 'disabled') {
                actionsCell.textContent = '';
            } else {
                const isSelfOwner = (currentUserRole === 'owner' && currentUsername && u.username && u.username.toLowerCase() === currentUsername.toLowerCase());
                if (isSelfOwner) {
                    actionsCell.textContent = '';
                    tbody.appendChild(tr);
                    return;
                }
                // Ban/Unban button
                // For admins: only show ban button for regular users; hide for owner/admins
                if (!(currentUserRole === 'admin' && !(u.role && u.role.toLowerCase() === 'user'))) {
                    const banBtn = document.createElement('button');
                    banBtn.className = 'btn btn-ban';
                    banBtn.textContent = (u.status && u.status.toLowerCase() === 'banned') ? 'Unban' : 'Ban';
                    banBtn.addEventListener('click', async () => {
                        await adminAction('ban', u.id);
                        fetchUsers();
                    });
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
                    promoteBtn.addEventListener('click', async () => {
                        const action = isAdmin ? 'demote' : 'promote';
                        await adminAction(action, u.id);
                        fetchUsers();
                    });
                    actionsCell.appendChild(promoteBtn);
                }

                // Verify button (doar dacă nu e deja verificat)
                if (!(u.is_verified === 1 || u.is_verified === '1' || u.is_verified === true)) {
                    const verifyBtn = document.createElement('button');
                    verifyBtn.className = 'btn btn-verify';
                    verifyBtn.textContent = 'Verify';
                    if (currentUserRole === 'admin' && u.role && u.role.toLowerCase() === 'owner') {
                        verifyBtn.disabled = true;
                        verifyBtn.title = 'Cannot verify owner';
                    }
                    verifyBtn.addEventListener('click', async () => {
                        await adminAction('verify', u.id);
                        fetchUsers();
                    });
                    actionsCell.appendChild(verifyBtn);
                }
            }

            const userLink = tr.querySelector('.user-row-link');
            if (userLink && u.username) {
                userLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const uname = u.username;
                    if (!uname) return;
                    if (currentUsername && currentUsername.toLowerCase() === uname.toLowerCase()) {
                        window.location.href = 'profile.html';
                    } else {
                        window.location.href = 'profile_public.html?user=' + encodeURIComponent(uname);
                    }
                });
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
        const type = document.getElementById('nftTypeFilter');
        if (!search || !min || !max || !type) return;
        const onChange = debounce(() => filterNfts(), 200);
        search.removeEventListener('input', onChange);
        min.removeEventListener('input', onChange);
        max.removeEventListener('input', onChange);
        type.removeEventListener('change', onChange);
        search.addEventListener('input', onChange);
        min.addEventListener('input', onChange);
        max.addEventListener('input', onChange);
        type.addEventListener('change', onChange);
    }

    function filterNfts() {
        const search = (document.getElementById('nftSearch') && document.getElementById('nftSearch').value || '').toLowerCase();
        const minVal = parseFloat(document.getElementById('nftPriceMin') && document.getElementById('nftPriceMin').value) || null;
        const maxVal = parseFloat(document.getElementById('nftPriceMax') && document.getElementById('nftPriceMax').value) || null;
        let filtered = nftsCache.slice();
        if (!Array.isArray(filtered)) filtered = [];
        const type = document.getElementById('nftTypeFilter') ? document.getElementById('nftTypeFilter').value : 'all';
        if (type === 'featured') filtered = filtered.filter(n => (n.is_featured === 1 || n.is_featured === '1' || n.is_featured === true));
        if (type === 'deleted') filtered = filtered.filter(n => n.is_deleted && (n.is_deleted === 1 || n.is_deleted === '1' || n.is_deleted === true));
        // "all" nu filtrează suplimentar
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
            let actionCell = '';
            let featuredCell = (n.is_featured === 1 || n.is_featured === '1' || n.is_featured === true) ? '<span style="color:#009900;font-weight:bold">Featured</span>' : '-';
            if (n.is_deleted && parseInt(n.is_deleted) === 1) {
                tr.classList.add('nft-deleted');
                actionCell = '';
            } else {
                let featureBtnText = (n.is_featured === 1 || n.is_featured === '1' || n.is_featured === true) ? 'Unfeature' : 'Feature';
                actionCell = `
                    <button class="btn btn-del">Delete</button>
                    <button class="btn btn-feature">${featureBtnText}</button>
                `;
            }
            tr.innerHTML = `
                <td><img src="${n.thumbnail}" style="width:48px;height:48px;object-fit:cover;border-radius:6px"></td>
                <td>${n.name}</td>
                <td><span class="user_link nft-creator-link" data-username="${n.creator || ''}">${n.creator || '-'}</span></td>
                <td><span class="user_link nft-owner-link" data-username="${n.owner || ''}">${n.owner || '-'}</span></td>
                <td>${n.price} ETH</td>
                <td>${n.created_at}</td>
                <td>${featuredCell}</td>
                <td>${actionCell}</td>
            `;

            // Clickable creator / owner names -> profile or public profile
            const creatorLink = tr.querySelector('.nft-creator-link');
            if (creatorLink && creatorLink.dataset.username) {
                creatorLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const uname = creatorLink.dataset.username;
                    if (!uname) return;
                    if (currentUsername && currentUsername.toLowerCase() === uname.toLowerCase()) {
                        window.location.href = 'profile.html';
                    } else {
                        window.location.href = 'profile_public.html?user=' + encodeURIComponent(uname);
                    }
                });
            }

            const ownerLink = tr.querySelector('.nft-owner-link');
            if (ownerLink && ownerLink.dataset.username) {
                ownerLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const uname = ownerLink.dataset.username;
                    if (!uname) return;
                    if (currentUsername && currentUsername.toLowerCase() === uname.toLowerCase()) {
                        window.location.href = 'profile.html';
                    } else {
                        window.location.href = 'profile_public.html?user=' + encodeURIComponent(uname);
                    }
                });
            }
            if (!n.is_deleted || parseInt(n.is_deleted) !== 1) {
                const delBtn = tr.querySelector('.btn-del');
                if (delBtn) {
                    delBtn.addEventListener('click', function handleDelete() {
                        delBtn.textContent = 'Confirm';
                        delBtn.style.background = '#a30000';
                        delBtn.style.color = '#fff';
                        delBtn.removeEventListener('click', handleDelete);
                        delBtn.addEventListener('click', function handleConfirm() {
                            delBtn.disabled = true;
                            adminAction('delete_nft', n.id).then(() => {
                                tr.remove();
                            });
                        });
                    });
                }
                const featureBtn = tr.querySelector('.btn-feature');
                if (featureBtn) {
                    featureBtn.addEventListener('click', async () => {
                        if (featureBtn.textContent === 'Feature') {
                            await adminAction('feature_nft', n.id);
                        } else {
                            await adminAction('unfeature_nft', n.id);
                        }
                        fetchNFTs();
                    });
                }
            }
            tbody.appendChild(tr);
        });
    // Stil pentru NFT șters
    const style = document.createElement('style');
    style.innerHTML = `
    tr.nft-deleted {
        background: #ffeaea !important;
        color: #a30000;
        opacity: 0.7;
    }
    `;
    document.head.appendChild(style);
    }

    function mockNFTs() {
        return [
            {id:11, thumbnail:'img/galactic.jpg', name:'Cyber Dreams', creator:'akvlad99', owner:'andrei', price:2.5, created_at:'2025-10-01'},
            {id:12, thumbnail:'img/neon.jpg', name:'Giga Cats', creator:'creator1', owner:'maria', price:1.2, created_at:'2025-11-04'}
        ];
    }

    // Reports
    function fetchReports(mode) {
        if (mode) currentReportsMode = mode;
        const statusParam = currentReportsMode === 'history' ? 'closed' : 'open';
        fetch('admin_get_reports.php?status=' + encodeURIComponent(statusParam))
            .then(r => r.json())
            .then(data => renderReportsTable(data))
            .catch(() => renderReportsTable(mockReports()));
    }

    function renderReportsTable(reports) {
        const tbody = document.querySelector('#reportsTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!Array.isArray(reports) || reports.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.textContent = 'No reports found.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }
        const isHistory = (currentReportsMode === 'history');
        reports.forEach(r => {
            const tr = document.createElement('tr');
            tr.dataset.rowId = r.id;

            const type = (r.type || 'nft').toLowerCase();
            const reporterName = r.reported_by || '';
            const reporterCellHtml = reporterName
                ? `<span class="user_link report-reporter-link" data-username="${reporterName}">${reporterName}</span>`
                : '-';

            let targetLabel = '-';
            let targetHtml = '';
            if (type === 'nft') {
                const nftName = r.nft_name || r.item_name || '';
                const ownerName = r.nft_owner_name || '';
                const nftPart = nftName ? `NFT: "${nftName}"` : `NFT #${r.item_id || ''}`;
                const ownerPart = ownerName ? ` (Owner: ${ownerName})` : '';
                targetLabel = nftPart + ownerPart;
            } else {
                const uname = r.user_name || '';
                if (uname) {
                    targetLabel = uname;
                } else {
                    targetLabel = `User #${r.item_id || ''}`;
                }
            }

            if (type === 'user') {
                const uname = r.user_name || '';
                if (uname) {
                    targetHtml = `<span class=\"user_link report-target-link\" data-username=\"${uname}\">${uname}</span>`;
                } else {
                    targetHtml = targetLabel;
                }
            } else {
                targetHtml = targetLabel;
            }

            const createdAt = r.created_at || '';
            const createdText = createdAt ? createdAt : '';
            const reasonText = r.reason || '';
            const detailsText = r.details ? `<br><small>${r.details}</small>` : '';
            const statusText = r.status ? (r.status.charAt(0).toUpperCase() + r.status.slice(1)) : 'Open';

            let actionsHtml = '';
            if (!isHistory) {
                actionsHtml = `
                    <span style="margin-right:8px;font-size:12px;color:#6b7280;">${statusText}</span>
                    <button class="btn btn-dismiss">Dismiss</button>
                    ${type === 'nft' ? '<button class="btn btn-delete">Delete NFT</button>' : '<button class="btn btn-ban">Ban user</button>'}
                `;
            } else {
                actionsHtml = `<span style="font-size:12px;color:#6b7280;">${statusText}</span>`;
            }

            tr.innerHTML = `
                <td>${reporterCellHtml}</td>
                <td>${targetHtml}</td>
                <td>${reasonText}${detailsText}</td>
                <td>${createdText}</td>
                <td>${actionsHtml}</td>
            `;

            if (!isHistory) {
                const closeBtn = tr.querySelector('.btn-dismiss');
                if (closeBtn) {
                    closeBtn.addEventListener('click', async () => {
                        await adminAction('dismiss_report', r.id);
                        fetchReports('open');
                    });
                }

                if (type === 'nft') {
                    const delBtn = tr.querySelector('.btn-delete');
                    if (delBtn) {
                        delBtn.addEventListener('click', async () => {
                            if (!r.item_id) return;
                            await adminAction('delete_nft', r.item_id);
                            await adminAction('dismiss_report', r.id);
                            fetchReports('open');
                        });
                    }
                } else {
                    const banBtn = tr.querySelector('.btn-ban');
                    if (banBtn && r.item_id) {
                        banBtn.addEventListener('click', async () => {
                            await adminAction('ban', r.item_id);
                            await adminAction('dismiss_report', r.id);
                            fetchReports('open');
                            fetchUsers();
                        });
                    }
                }

                const reporterLink = tr.querySelector('.report-reporter-link');
                if (reporterLink && reporterName) {
                    reporterLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        const uname = reporterName;
                        if (!uname) return;
                        if (currentUsername && currentUsername.toLowerCase() === uname.toLowerCase()) {
                            window.location.href = 'profile.html';
                        } else {
                            window.location.href = 'profile_public.html?user=' + encodeURIComponent(uname);
                        }
                    });
                }

                const targetLink = tr.querySelector('.report-target-link');
                if (targetLink && targetLink.dataset.username) {
                    targetLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        const uname = targetLink.dataset.username;
                        if (!uname) return;
                        if (currentUsername && currentUsername.toLowerCase() === uname.toLowerCase()) {
                            window.location.href = 'profile.html';
                        } else {
                            window.location.href = 'profile_public.html?user=' + encodeURIComponent(uname);
                        }
                    });
                }
            }

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
        if (!div) return;
        if (!Array.isArray(tx) || tx.length === 0) {
            div.innerHTML = '<div class="transactions_empty">No transactions found.</div>';
            return;
        }
        const rows = tx.map(t => {
            const type = (t.type || '').toLowerCase();
            let badgeClass = 'tx_badge';
            let badgeLabel = '';
            switch (type) {
                case 'deposit':
                    badgeClass += ' tx_badge_deposit';
                    badgeLabel = 'Deposit';
                    break;
                case 'withdraw':
                    badgeClass += ' tx_badge_withdraw';
                    badgeLabel = 'Withdraw';
                    break;
                case 'buy':
                    badgeClass += ' tx_badge_buy';
                    badgeLabel = 'Buy';
                    break;
                case 'sell':
                    badgeClass += ' tx_badge_sell';
                    badgeLabel = 'Sell';
                    break;
                default:
                    badgeLabel = (t.action || 'Action');
            }

            const actorName = t.actor || '';
            const actorHtml = actorName
                ? `<span class="user_link tx-actor-link" data-username="${actorName}">${actorName}</span>`
                : '';

            let targetText = t.target || '';
            if (t.nft_name) {
                targetText = `"${t.nft_name}"`;
            }

            const title = `${actorHtml} ${t.action || ''} ${targetText}`.trim();
            const meta = t.time ? t.time : '';

            return `
                <div class="tx_item" data-actor="${actorName}">
                    <div class="tx_item_left">
                        <div class="tx_title">${title}</div>
                        <div class="tx_meta">${meta}</div>
                    </div>
                    <div class="tx_item_right">
                        <div class="tx_badge ${badgeClass}">${badgeLabel}</div><br>
                        <div class="tx_amount">${t.amount} ETH</div>
                    </div>
                </div>
            `;
        });
        div.innerHTML = rows.join('');

        // Wire clickable actor names
        const actorLinks = div.querySelectorAll('.tx-actor-link[data-username]');
        actorLinks.forEach(link => {
            const uname = link.dataset.username;
            if (!uname) return;
            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (currentUsername && currentUsername.toLowerCase() === uname.toLowerCase()) {
                    window.location.href = 'profile.html';
                } else {
                    window.location.href = 'profile_public.html?user=' + encodeURIComponent(uname);
                }
            });
        });
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

    // NFT VERIFICATION REQUESTS (unapproved NFTs)
    function fetchNftVerifications() {
        fetch('admin_get_nft_verifications.php')
            .then(r => r.json())
            .then(data => renderNftVerifications(data))
            .catch(() => renderNftVerifications([]));
    }

    function renderNftVerifications(items) {
        const tbody = document.querySelector('#nftVerificationsTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="6" style="text-align:center;color:#aaa">No NFTs pending approval</td>';
            tbody.appendChild(tr);
            return;
        }

        items.forEach(nft => {
            const tr = document.createElement('tr');
            tr.dataset.rowId = nft.id;
            tr.dataset.nftId = nft.id;
            const approveActive = nftSelectedAction[nft.id] === 'approve';
            const rejectActive = nftSelectedAction[nft.id] === 'reject';
            const thumb = nft.thumbnail || nft.image_url || 'img/placeholder.jpg';
            const creator = nft.creator || nft.creator_name || ('User #' + (nft.creator_id || '?'));
            const price = nft.price !== undefined ? nft.price : '-';
            const createdAt = nft.created_at || '';
            tr.innerHTML = `
                <td><img src="${thumb}" style="width:48px;height:48px;object-fit:cover;border-radius:6px" /></td>
                <td>${nft.name || '-'}</td>
                <td>${creator}</td>
                <td>${price} ETH</td>
                <td>${createdAt}</td>
                <td class="verif_actions_cell">
                    <button class="approve_btn${approveActive ? ' active pressed' : ''}" data-id="${nft.id}">Approve</button>
                    <button class="reject_btn${rejectActive ? ' active pressed' : ''}" data-id="${nft.id}">Reject</button>
                    <button class="confirm_btn" data-id="${nft.id}">Confirm</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // approve/reject just select the action (highlight)
        tbody.querySelectorAll('.approve_btn').forEach(btn => {
            btn.onclick = function() {
                const id = this.dataset.id;
                Object.keys(nftSelectedAction).forEach(k => delete nftSelectedAction[k]);
                nftSelectedAction[id] = 'approve';
                renderNftVerifications(items.map(i => Object.assign({}, i)));
            };
        });
        tbody.querySelectorAll('.reject_btn').forEach(btn => {
            btn.onclick = function() {
                const id = this.dataset.id;
                Object.keys(nftSelectedAction).forEach(k => delete nftSelectedAction[k]);
                nftSelectedAction[id] = 'reject';
                renderNftVerifications(items.map(i => Object.assign({}, i)));
            };
        });
        // Confirm sends the action
        tbody.querySelectorAll('.confirm_btn').forEach(btn => {
            btn.onclick = async function() {
                const id = this.dataset.id;
                const action = nftSelectedAction[id];
                if (!action) return;
                if (action === 'approve') {
                    await adminAction('nft_approve', id, true);
                } else if (action === 'reject') {
                    await adminAction('nft_reject', id, true);
                }
                Object.keys(nftSelectedAction).forEach(k => delete nftSelectedAction[k]);
                setTimeout(() => fetchNftVerifications(), 300);
            };
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
                case 'nft_approve':
                case 'nft_reject':
                    fetchNftVerifications && fetchNftVerifications();
                    break;
                case 'delete_nft':
                    fetchNFTs();
                    fetchReports();
                    break;
                case 'feature_nft':
                case 'unfeature_nft':
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
        // Wire reports subtabs
        const reportTabs = document.querySelectorAll('.reports_tab_btn');
        reportTabs.forEach(btn => {
            btn.addEventListener('click', () => {
                reportTabs.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const mode = btn.dataset.reportMode === 'history' ? 'history' : 'open';
                fetchReports(mode);
            });
        });

        fetchOverview();
        fetchUsers();
        fetchNFTs();
        fetchReports('open');
        fetchTransactions();
    });

    // Încarcă cererile de verificare la inițializare și la click pe tab
    const verifTab = document.querySelector('li[data-panel="verifications"]');
    const verifModeButtons = document.querySelectorAll('.verif_tab_btn');
    function setVerificationMode(mode) {
        const userSec = document.getElementById('userVerificationsSection');
        const nftSec = document.getElementById('nftVerificationsSection');
        verifModeButtons.forEach(btn => {
            if (btn.dataset.verifMode === mode) btn.classList.add('active');
            else btn.classList.remove('active');
        });
        if (userSec && nftSec) {
            userSec.style.display = (mode === 'users') ? '' : 'none';
            nftSec.style.display = (mode === 'nfts') ? '' : 'none';
        }
        if (mode === 'users') fetchVerifications();
        if (mode === 'nfts') fetchNftVerifications();
    }

    if (verifModeButtons.length) {
        verifModeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.verifMode || 'users';
                setVerificationMode(mode);
            });
        });
    }

    if (verifTab) {
        verifTab.addEventListener('click', function() {
            setVerificationMode('users');
        });
        // Încarcă automat dacă tab-ul e activ la load
        if (verifTab.classList.contains('active')) {
            setVerificationMode('users');
        }
    }

    // Expose for debugging
    window.__dashboard = { fetchOverview, fetchUsers, fetchNFTs, fetchReports, fetchTransactions, filterUsers, filterNfts, usersCache, nftsCache };
});
