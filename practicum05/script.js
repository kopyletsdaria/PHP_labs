const resultsContainer = document.getElementById('results');
const messageBox = document.getElementById('message-box');
document.addEventListener('DOMContentLoaded', () => {
    loadTasks();
});
document.getElementById('priorityFilter').addEventListener('input', (e) => {
    loadTasks(e.target.value);
});
document.getElementById('addForm').addEventListener('submit', async (e) => {
    e.preventDefault(); 
    const formData = new FormData(e.target);
    try {
        const response = await fetch('api_add.php', {
            method: 'POST',
            body: formData
        });
        if (!response.ok) throw new Error('Помилка при додаванні запису');
        e.target.reset();
        loadTasks(document.getElementById('priorityFilter').value);
        messageBox.innerText = ''; 
    } catch (error) {
        messageBox.innerText = error.message; 
    }
});
async function loadTasks(priority = '') {
    try {
        const url = priority ? `api_list.php?priority=${encodeURIComponent(priority)}` : 'api_list.php';
        const response = await fetch(url);
        if (!response.ok) throw new Error('Помилка завантаження даних');
        const data = await response.json(); 
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
        const isChecked = task.done == 1 ? 'checked' : '';
        tr.innerHTML = `
            <td>
                <input type="checkbox" onchange="toggleDone(${task.id}, this.checked, this.closest('tr'))" ${isChecked}>
            </td>
            <td>${escapeHTML(task.title)}</td>
            <td>${escapeHTML(task.due_date)}</td>
            <td>${escapeHTML(task.priority)}</td>
        `;
        resultsContainer.appendChild(tr);
    });
}
async function toggleDone(id, isDone, rowElement) {
    try {
        const response = await fetch('api_done.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, done: isDone ? 1 : 0 })
        });
        if (!response.ok) throw new Error('Помилка оновлення статусу');
        if (isDone) {
            rowElement.classList.add('done-row');
        } else {
            rowElement.classList.remove('done-row');
        }
    } catch (error) {
        messageBox.innerText = error.message;
    }
}
function escapeHTML(str) {
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}