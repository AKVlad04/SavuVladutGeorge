const mockNFTs = [
    {"creator_id": 1, "name": "Cyber Dreams #001", "description": "This unique digital artwork presents a captivating vision of a futuristic cyberpunk landscape.", "price": 2.5, "category": "Digital Art", "image_url": "img/galactic.jpg"},
    {"creator_id": 4, "name": "Giga Cats #001", "description": "A futuristic feline collectible from the first Giga Cats series.", "price": 1.2, "category": "Collectibles", "image_url": "img/neon.jpg"},
    {"creator_id": 18, "name": "Genesis Token #008", "description": "One of the original Genesis tokens.", "price": 5.0, "category": "Utility", "image_url": "img/echoes.jpg"}
];
    
// --- LOGICA PENTRU NFT-URI ȘI INTERFAȚĂ ---

// Elementele DOM
const container = document.getElementById('nftsContainer');
// quick load log and clear placeholder if present
console.log('script.js loaded');
const globalAproxPlaceholder = document.getElementById('aprox');
if (globalAproxPlaceholder && globalAproxPlaceholder.textContent && globalAproxPlaceholder.textContent.includes('xxxx')) {
    globalAproxPlaceholder.textContent = '≈ $0.00 USD';
}
// Verificăm dacă există containerul (pentru a nu da eroare pe pagini unde nu sunt NFT-uri)
if (container) {
    const popup = document.getElementById('nftPopup');
    const popupTitle = document.getElementById('popupTitle');
    const popupCategory = document.getElementById('popupCategory');
    const popupDescription = document.getElementById('popupDescription');
    const popupPrice = document.getElementById('popupPrice');
    const popupImg = document.getElementById('popupImage');
    const closeBtn = popup ? popup.querySelector('.close') : null;

    // Elementele pentru Filtrare
    const filterButtonDiv = document.getElementById('filterButton');
    const filterBox = document.getElementById('filterBox'); 
    const bgElement = document.querySelector('.bg');
    const categoryFilterSelect = document.getElementById('categoryFilter');
    const priceRangeFilterSelect = document.getElementById('priceRangeFilter');
    const sortBySelect = document.getElementById('sortBy');

    let currentNFTs = [];
    let nftsFromDB = [];
    const searchInputMain = document.getElementById('search_bar');
    const searchInputExplore = document.getElementById('search_bar_explore');

    // Load NFTs from server, fallback to mock data
    fetch('nfts.php').then(r => r.json()).then(data => {
        if (Array.isArray(data) && data.length > 0) nftsFromDB = data;
        else nftsFromDB = mockNFTs;
        // On main page show only featured; on explore show all
        const isIndex = !!document.getElementById('search_bar');
        if (isIndex) {
            currentNFTs = nftsFromDB.filter(n => parseInt(n.is_featured) === 1);
        } else {
            currentNFTs = nftsFromDB.slice();
        }
        populateCategories();
        renderNFTs(currentNFTs);
    }).catch(err => {
        console.warn('Could not load nfts.php, using mock', err);
        nftsFromDB = mockNFTs;
        const isIndex = !!document.getElementById('search_bar');
        if (isIndex) currentNFTs = nftsFromDB.filter(n => n.is_featured && parseInt(n.is_featured) === 1);
        else currentNFTs = nftsFromDB.slice();
        populateCategories();
        renderNFTs(currentNFTs);
    });

    // Functie pentru a genera optiile de categorii in box
    function populateCategories() {
        if (!categoryFilterSelect) return;
        // populate from full DB set if available, otherwise from current render set
        const source = (Array.isArray(nftsFromDB) && nftsFromDB.length) ? nftsFromDB : currentNFTs;
        const categories = [...new Set(source.map(nft => nft.category || ''))].filter(Boolean).sort();
        // reset existing options (preserves first placeholder if present)
        categoryFilterSelect.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'All Categories';
        categoryFilterSelect.appendChild(defaultOption);
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categoryFilterSelect.appendChild(option);
        });
    }

    // Functie pentru a randa NFT-urile
    function renderNFTs(nftsToRender) {
        if (!container) return;
        container.innerHTML = ''; 
        nftsToRender.forEach(nft => {
            const div = document.createElement('div');
            div.className = 'nft';
            div.innerHTML = `
            <div class="nft_image">
                <img src="${nft.image_url}" alt="${nft.name}">
            </div>
            <div class="nft_footer">
                <div class="nft_name_cat">
                <span class="nft_name">${nft.name}</span>
                <span class="nft_category">${nft.category}</span>
                <span class="nft_price">${nft.price} ETH</span>
                </div>
            </div>
            `;
            
            div.addEventListener('click', async () => {
                popupTitle.textContent = nft.name;
                popupCategory.textContent = nft.category;
                popupDescription.textContent = nft.description;
                popupPrice.textContent = nft.price + " ETH";
                popupImg.src = nft.image_url;
                popup.classList.add('show');
                // Update approximate USD price in the popup. Query the element inside the popup
                const aproxElLocal = popup ? popup.querySelector('#aprox') : null;
                // Debug: log opening
                console.log('Opening popup for', nft.name, 'popup found?', !!popup);
                if (aproxElLocal) {
                    aproxElLocal.textContent = '≈ loading...';
                    console.log('#aprox inside popup found');
                } else {
                    console.log('#aprox NOT found inside popup, trying global lookup');
                }

                try {
                    const fallbackRate = 1900; // USD per ETH if external API fails
                    let rate = fallbackRate;
                    const resp = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=ethereum&vs_currencies=usd');
                    console.log('CoinGecko fetch ok?', resp.ok, 'status', resp.status);
                    if (resp.ok) {
                        const json = await resp.json();
                        console.log('CoinGecko response', json);
                        if (json && json.ethereum && typeof json.ethereum.usd === 'number') {
                            rate = json.ethereum.usd;
                            console.log('ETH->USD rate', rate);
                        }
                    } else {
                        console.warn('CoinGecko returned non-ok status, using fallback rate', rate);
                    }

                    const text = "≈ $" + (nft.price * rate).toFixed(2) + " USD";
                    if (aproxElLocal) {
                        aproxElLocal.textContent = text;
                        console.log('Updated aprox inside popup:', text);
                    } else {
                        // fallback: try document-wide id (in case popup scoping differs)
                        const globalAprox = document.getElementById('aprox');
                        if (globalAprox) {
                            globalAprox.textContent = text;
                            console.log('#aprox updated via document.getElementById');
                        } else {
                            console.warn('Could not find #aprox to update');
                        }
                    }
                } catch (e) {
                    const text = "≈ $" + (nft.price * 1900).toFixed(2) + " USD";
                    if (aproxElLocal) {
                        aproxElLocal.textContent = text;
                        console.log('Fetch failed — used fallback and updated inside popup:', text);
                    } else {
                        const globalAprox = document.getElementById('aprox');
                        if (globalAprox) globalAprox.textContent = text;
                        console.log('Fetch failed — used fallback and updated global #aprox:', text);
                    }
                    console.error('Error fetching ETH price:', e);
                }
            });
            
            container.appendChild(div);
        });
    }

    // Functie de filtrare si sortare
    function applyFiltersAndSort() {
        if (!categoryFilterSelect) return;

        const category = categoryFilterSelect.value;
        const priceRange = priceRangeFilterSelect.value;
        const sortBy = sortBySelect.value;
        const searchTerm = (searchInputMain && searchInputMain.value || searchInputExplore && searchInputExplore.value || '').toLowerCase().trim();

        // Parsarea intervalului de preț
        let minPrice = 0;
        let maxPrice = Infinity;
        if (priceRange && priceRange !== '0-max') {
            const parts = priceRange.split('-');
            minPrice = parseFloat(parts[0]) || 0;
            maxPrice = parts[1] === 'max' ? Infinity : parseFloat(parts[1]) || Infinity;
        }

        // start from DB source
        let filteredNFTs = Array.isArray(nftsFromDB) ? nftsFromDB.slice() : [];

        // If on index, only featured
        const isIndex = !!document.getElementById('search_bar');
        if (isIndex) filteredNFTs = filteredNFTs.filter(n => parseInt(n.is_featured) === 1);

        // category & price filters
        filteredNFTs = filteredNFTs.filter(nft => {
            const matchesCategory = !category || (nft.category === category);
            const price = parseFloat(nft.price) || 0;
            const matchesPrice = price >= minPrice && price <= maxPrice;
            return matchesCategory && matchesPrice;
        });

        // search
        if (searchTerm) {
            filteredNFTs = filteredNFTs.filter(n => {
                const s = ((n.name||'') + ' ' + (n.category||'') + ' ' + (n.creator||'') + ' ' + (n.owner||'')).toLowerCase();
                return s.indexOf(searchTerm) !== -1;
            });
        }

        // sort
        filteredNFTs.sort((a, b) => {
            switch (sortBy) {
            case 'price_asc':
                return (parseFloat(a.price)||0) - (parseFloat(b.price)||0);
            case 'price_desc':
                return (parseFloat(b.price)||0) - (parseFloat(a.price)||0);
            case 'name_asc':
                return (a.name||'').localeCompare(b.name||'');
            case 'creator_id_asc':
                return (a.creator_id||0) - (b.creator_id||0);
            default:
                return 0; 
            }
        });

        currentNFTs = filteredNFTs;
        renderNFTs(currentNFTs);
    }

    // Initial render happens after fetching nfts (above)

    // Event Listeners pentru filtre
    if (categoryFilterSelect) categoryFilterSelect.addEventListener('change', applyFiltersAndSort);
    if (priceRangeFilterSelect) priceRangeFilterSelect.addEventListener('change', applyFiltersAndSort);
    if (sortBySelect) sortBySelect.addEventListener('change', applyFiltersAndSort);
    // search inputs (realtime + Enter to apply)
    const deb = (fn, ms=200)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms);} };
    if (searchInputMain) searchInputMain.addEventListener('input', deb(()=>applyFiltersAndSort()));
    if (searchInputExplore) searchInputExplore.addEventListener('input', deb(()=>applyFiltersAndSort()));
    [searchInputMain, searchInputExplore].forEach(inp=>{
        if (!inp) return;
        inp.addEventListener('keydown', (e)=>{ if (e.key === 'Enter') { e.preventDefault(); applyFiltersAndSort(); } });
    });

    // Apply Filters button
    const applyFiltersBtn = document.getElementById('applyFiltersBtn');
    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', (e)=>{ e.preventDefault(); applyFiltersAndSort(); });

    // Buton Filtre
    if (filterButtonDiv) {
        filterButtonDiv.addEventListener('click', (e) => {
            e.preventDefault(); 
            const isShown = filterBox.classList.toggle('show');
            if (bgElement) bgElement.classList.toggle('active', isShown); 
        });
    }

    // Popup Close
    if (closeBtn) {
        closeBtn.addEventListener('click', () => popup.classList.remove('show'));
    }
    if (popup) {
        popup.addEventListener('click', e => {
            if(e.target === popup) popup.classList.remove('show');
        });
    }
}

// --- LOGICA DE AUTHENTICARE (Login/Logout Vizual) ---
// Aceasta rulează pe toate paginile

// --- LOGICA DE AUTHENTICARE (Login <-> Logout Transform) ---

document.addEventListener("DOMContentLoaded", () => {
    // Hide auth and create buttons until auth check completes
    document.querySelectorAll('.connect_wallet, .create_button, .small_button_footer[href="create.html"]').forEach(el => {
        el.style.visibility = 'hidden';
    });
    const loginBtnLink = document.querySelector('.connect_wallet'); 
    const loginBtnText = document.querySelector('.connect_wallet_text');
    const profileMenuBtn = document.querySelector('.profile_button'); 

    fetch('get_profile.php')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            console.log("Auth Status:", data);

            if (data.logged_in) {
                // === UTILIZATOR CONECTAT ===
                
                // 1. Transformăm butonul din dreapta în LOGOUT
                if (loginBtnLink && loginBtnText) {
                    loginBtnLink.style.display = 'flex'; // Îl ținem vizibil
                    loginBtnLink.href = 'logout.php';    // Schimbăm destinația
                    loginBtnText.textContent = "Logout"; // Schimbăm textul
                    
                    // Schimbăm stilul în ROȘU (pentru Logout)
                    loginBtnLink.style.borderColor = "#ef4444"; 
                    loginBtnLink.style.boxShadow = "0 0 5px rgba(239, 68, 68, 0.3)";
                    loginBtnText.style.color = "#ef4444";
                }

                // 2. Afișăm butonul Profile în meniul central
                if (profileMenuBtn) {
                    profileMenuBtn.style.display = 'inline-block';
                }

            } else {
                // === UTILIZATOR NECONECTAT ===
                
                // 1. Resetăm butonul din dreapta la LOGIN (Albastru)
                if (loginBtnLink && loginBtnText) {
                    loginBtnLink.style.display = 'flex';
                    loginBtnLink.href = 'login.html';
                    loginBtnText.textContent = "Login";
                    
                    // Resetăm stilul (sau îl lăsăm pe cel din CSS)
                    loginBtnLink.style.borderColor = ""; 
                    loginBtnLink.style.boxShadow = "";
                    loginBtnText.style.color = ""; 
                }

                // 2. Ascundem butonul Profile
                if (profileMenuBtn) {
                    profileMenuBtn.style.display = 'none';
                }
            }

            // Ascunde tab-ul de Create dacă userul nu e verificat
            if (!data.is_verified || Number(data.is_verified) !== 1) {
                document.querySelectorAll('a.create_button, a[href="create.html"], a.small_button_footer[href="create.html"]').forEach(el => {
                    el.style.display = 'none';
                });
            }

            // Show auth and create buttons after auth check
            document.querySelectorAll('.connect_wallet, .create_button, .small_button_footer[href="create.html"]').forEach(el => {
                el.style.visibility = 'visible';
            });
        })
        .catch(error => {
            console.error('Eroare JS Auth:', error);
            // Fallback la Guest Mode
            if (profileMenuBtn) profileMenuBtn.style.display = 'none';
            document.querySelectorAll('.connect_wallet, .create_button, .small_button_footer[href="create.html"]').forEach(el => {
                el.style.visibility = 'visible';
            });
        });

    // Profile wallet load/save (only on profile page where inputs exist)
    const walletInput = document.getElementById('walletInput');
    const saveBtn = document.getElementById('saveWallet');
    const saveStatus = document.getElementById('saveStatus');
    if (walletInput && saveBtn) {
        fetch('get_profile.php')
            .then(r => r.ok ? r.json() : Promise.reject('no-profile'))
            .then(data => {
                if (data && data.wallet) walletInput.value = data.wallet;
            })
            .catch(e => console.warn('Could not load wallet:', e));

        saveBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (saveStatus) { saveStatus.style.display = 'none'; }
            const wallet = walletInput.value.trim();
            const form = new FormData();
            form.append('wallet', wallet);
            try {
            console.log('Sending wallet update', wallet);
                const resp = await fetch('update_profile.php', { method: 'POST', body: form, credentials: 'include' });
                const text = await resp.text();
                let json = {};
                try { json = JSON.parse(text); } catch (parseErr) { console.warn('update_profile: invalid JSON', text); }
                console.log('update_profile response', resp.status, json, text);
                if (resp.ok && json.ok) {
                    if (saveStatus) { saveStatus.style.display = 'block'; saveStatus.textContent = 'Saved'; saveStatus.style.color = 'green'; }
                } else {
                    const msg = json.error || ('HTTP ' + resp.status);
                    if (saveStatus) { saveStatus.style.display = 'block'; saveStatus.textContent = msg; saveStatus.style.color = 'red'; }
                    console.error('Failed saving wallet:', msg);
                }
            } catch (err) {
                console.error('Network error saving wallet', err);
                if (saveStatus) { saveStatus.style.display = 'block'; saveStatus.textContent = 'Network error'; saveStatus.style.color = 'red'; }
            }
            setTimeout(() => { if (saveStatus) saveStatus.style.display = 'none'; }, 5000);
        });
    }
});