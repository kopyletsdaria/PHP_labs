const API_URL = 'api.php?resource=tasks';
const resultsContainer = document.getElementById('results');
const messageBox = document.getElementById('message-box');
// CSRF-токен сесії
const csrfToken = document.querySelector('input[name="csrf_token"]').value;

document.addEventListener('DOMContentLoaded', () => {
    loadTasks();
});

document.getElementById('doneFilter').addEventListener('input', (e) => {
    loadTasks(e.target.value);
});

document.getElementById('addForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        await apiRequest(API_URL, {
            method: 'POST',
            body: formData
        });
        e.target.reset();
        loadTasks(document.getElementById('doneFilter').value);
        messageBox.innerText = '';
    } catch (error) {
        messageBox.innerText = error.message;
    }
});

// Усі відповіді API мають вигляд {success, data} або {success: false, error}
async function apiRequest(url, options = {}) {
    // Кожен POST-запит надсилає CSRF-токен у заголовку
    if (options.method === 'POST') {
        options.headers = { ...(options.headers || {}), 'X-CSRF-Token': csrfToken };
    }
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.error || 'Помилка запиту до API');
    }
    return result.data;
}

async function loadTasks(done = '') {
    try {
        const url = done !== '' ? `${API_URL}&done=${encodeURIComponent(done)}` : API_URL;
        const data = await apiRequest(url);
        renderTable(data);
        messageBox.innerText = '';
    } catch (error) {
        messageBox.innerText = error.message;
    }
}

function renderTable(tasks) {
    resultsContainer.innerHTML = '';

    tasks.forEach(task => {
        const tr = document.createElement('tr');
        if (task.done == 1) tr.classList.add('done-row');
        const isChecked = task.done == 1 ? 'checked disabled' : '';
        tr.innerHTML = `
            <td>
                <input type="checkbox" onchange="completeTask(${task.id}, this.closest('tr'))" ${isChecked}>
            </td>
            <td>${escapeHTML(task.title)}</td>
            <td>${escapeHTML(task.due_date)}</td>
            <td>${escapeHTML(task.priority)}</td>
        `;
        resultsContainer.appendChild(tr);
    });
}

// Доменна дія API: POST ?resource=tasks&action=complete&id=N
async function completeTask(id, rowElement) {
    try {
        await apiRequest(`${API_URL}&action=complete&id=${id}`, { method: 'POST' });
        rowElement.classList.add('done-row');
        rowElement.querySelector('input[type="checkbox"]').disabled = true;
        messageBox.innerText = '';
    } catch (error) {
        messageBox.innerText = error.message;
        rowElement.querySelector('input[type="checkbox"]').checked = false;
    }
}

function escapeHTML(str) {
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}
