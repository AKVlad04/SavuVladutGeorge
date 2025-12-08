document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Oprește reîncărcarea paginii

    // Resetăm mesajele de eroare și succes
    document.getElementById('successMessage').style.display = 'none';
    document.getElementById('err-nume').textContent = '';
    document.getElementById('err-email').textContent = '';
    document.getElementById('err-mesaj').textContent = '';

    // Colectăm datele din formular
    const formData = new FormData(this);
    const nume = formData.get('nume');
    const email = formData.get('email');
    const mesaj = formData.get('mesaj');
    
    let hasFrontendErrors = false;

    // --- Validare Frontend (JavaScript) ---
    // Aceasta este rapidă, pentru experiența utilizatorului
    
    if (nume.length < 3) {
        document.getElementById('err-nume').textContent = "JS: Numele este prea scurt (min 3).";
        hasFrontendErrors = true;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        document.getElementById('err-email').textContent = "JS: Email invalid.";
        hasFrontendErrors = true;
    }

    if (mesaj.length < 10) {
        document.getElementById('err-mesaj').textContent = "JS: Mesaj prea scurt (min 10).";
        hasFrontendErrors = true;
    }

    if (hasFrontendErrors) {
        return; // Nu mai trimitem la PHP dacă JS a găsit erori
    }

    // --- Validare Backend (Trimitere către PHP) ---
    // Dacă JS e ok, trimitem la PHP pentru validarea finală
    
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Ascundem formularul și afișăm mesajul
            document.getElementById('contactForm').style.display = 'none';
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = data.message;
            successDiv.style.display = 'block';
        } else {
            // Afișăm erorile venite din PHP
            if (data.errors.nume) document.getElementById('err-nume').textContent = data.errors.nume;
            if (data.errors.email) document.getElementById('err-email').textContent = data.errors.email;
            if (data.errors.mesaj) document.getElementById('err-mesaj').textContent = data.errors.mesaj;
        }
    })
    .catch(error => {
        console.error('Eroare:', error);
    });
});