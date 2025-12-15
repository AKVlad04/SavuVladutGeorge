
const API_URL = './api.php';
const form = document.getElementById('addStudentForm');
const studentListBody = document.getElementById('studentListBody');
const messageElement = document.getElementById('message');

const editingIdInput = document.getElementById('editingId');
const submitButton = document.getElementById('submitButton');
const cancelButton = document.getElementById('cancelEdit');

const numeInput = document.getElementById('nume');
const anInput = document.getElementById('anul_studiu');
const mediaInput = document.getElementById('media_generala');

function displayMessage(text, isSuccess) {
    messageElement.textContent = text;
    messageElement.classList.remove('hidden', 'success', 'error');
    messageElement.classList.add(isSuccess ? 'success' : 'error');
    
    setTimeout(() => {
        messageElement.classList.add('hidden');
    }, 4000);
}

function resetFormToAddMode() {
    form.reset();
    editingIdInput.value = '';
    submitButton.textContent = 'Adaugă Student';
    submitButton.style.backgroundColor = 'var(--primary-color)';
    cancelButton.classList.add('hidden');
}

function renderStudents(studenti) {
    studentListBody.innerHTML = ''; 

    if (studenti.length === 0) {
        studentListBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Nu există studenți adăugați.</td></tr>'; 
        return;
    }

    studenti.forEach(student => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${student.nume}</td>
            <td>Anul ${student.anul_studiu}</td>
            <td>${student.media_generala.toFixed(2)}</td>
            <td>
                <button class="edit-btn" data-id="${student.id}" 
                        data-nume="${student.nume}" 
                        data-an="${student.anul_studiu}" 
                        data-media="${student.media_generala.toFixed(2)}">Editează</button>
            </td>
            <td>
                <button class="delete-btn" data-id="${student.id}">Șterge</button>
            </td>
        `;
        studentListBody.appendChild(row);
    });
}

function fetchStudents() {
    studentListBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Se încarcă studenții...</td></tr>';
    
    fetch(API_URL)
        .then(response => {
            if (!response.ok) {
                throw new Error('Eroare la preluarea datelor.');
            }
            return response.json(); 
        })
        .then(data => {
            renderStudents(data);
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            studentListBody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">Eroare la încărcarea listei. Verificați conexiunea la API.</td></tr>';
        });
}

function handleFormSubmit(e) {
    e.preventDefault(); 

    const nume = numeInput.value.trim();
    const anul_studiu = parseInt(anInput.value);
    const media_generala = parseFloat(mediaInput.value);
    const studentId = editingIdInput.value ? parseInt(editingIdInput.value) : null;
    
    if (!nume || isNaN(anul_studiu) || isNaN(media_generala)) {
        displayMessage('Vă rugăm completați toate câmpurile corect.', false);
        return;
    }
    
    if (studentId) {
        updateStudent(studentId, nume, anul_studiu, media_generala);
    } else {
        addStudent(nume, anul_studiu, media_generala);
    }
}

function addStudent(nume, anul_studiu, media_generala) {
    const postData = { nume, anul_studiu, media_generala };

    fetch(API_URL, {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(postData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessage(data.message, true);
            resetFormToAddMode(); 
            fetchStudents();
        } else {
            displayMessage(data.message || 'Eroare la adăugarea studentului.', false); 
        }
    })
    .catch(error => {
        console.error('POST Error:', error);
        displayMessage('Eroare de rețea. Nu s-a putut contacta serverul.', false);
    });
}

function updateStudent(id, nume, anul_studiu, media_generala) {
    const putData = { id, nume, anul_studiu, media_generala };

    fetch(API_URL, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(putData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessage(data.message, true);
            resetFormToAddMode();
            fetchStudents();
        } else {
            displayMessage(data.message || 'Eroare la actualizarea studentului.', false);
        }
    })
    .catch(error => {
        console.error('PUT Error:', error);
        displayMessage('Eroare de rețea la actualizare.', false);
    });
}

function deleteStudent(studentId) {
    const confirmDelete = confirm(`Sigur doriți să ștergeți studentul cu ID-ul ${studentId}?`);
    if (!confirmDelete) return;

    fetch(API_URL, {
        method: 'DELETE', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: studentId }) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessage(data.message, true);
            fetchStudents(); 
        } else {
            displayMessage(data.message || 'Eroare la ștergere.', false);
        }
    })
    .catch(error => {
        console.error('DELETE Error:', error);
        displayMessage('Eroare de rețea la ștergere.', false);
    });
}


form.addEventListener('submit', handleFormSubmit);

studentListBody.addEventListener('click', (e) => {
    const target = e.target;
    const studentId = target.getAttribute('data-id');

    if (target.classList.contains('delete-btn')) {
        deleteStudent(parseInt(studentId));
    } else if (target.classList.contains('edit-btn')) {
        editingIdInput.value = studentId;
        numeInput.value = target.getAttribute('data-nume');
        anInput.value = target.getAttribute('data-an');
        mediaInput.value = target.getAttribute('data-media');

        submitButton.textContent = 'Salvează Modificările';
        submitButton.style.backgroundColor = 'var(--success-color)';
        cancelButton.classList.remove('hidden');

        form.scrollIntoView({ behavior: 'smooth' });
    }
});

cancelButton.addEventListener('click', resetFormToAddMode);

document.addEventListener('DOMContentLoaded', fetchStudents);

