<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TaskFlow+ Live</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        #log {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 500px;
            overflow-y: auto;
        }
        #log p {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
        }
        .status {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            background: #e8f4fc;
        }
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
    @vite(['resources/js/app.js'])
</head>
<body>
<h2>📡 Події у реальному часі - TaskFlow+</h2>

<div class="status">
    Статус підключення:
    <span id="connection-status" style="color: red;">🔴 Не підключено</span>
</div>

<div style="margin: 20px 0;">
    <button class="btn" onclick="createTestTask()">📝 Створити тестову задачу</button>
    <button class="btn" onclick="updateTaskStatus()">🔄 Змінити статус задачі</button>
    <button class="btn" onclick="addTestComment()">💬 Додати тестовий коментар</button>
    <button class="btn" onclick="clearLog()">🧹 Очистити лог</button>
</div>

<h3>Журнал подій:</h3>
<div id="log"></div>

<div style="margin-top: 30px; font-size: 0.9em; color: #666;">
    <p><strong>Інструкція:</strong> Відкрийте цю сторінку в двох вкладках браузера.
        Виконайте дії в одній вкладці та спостерігайте оновлення в реальному часі в іншій.</p>
</div>

<script>
    const projectId = 1;
    let taskId = null;

    const log = (msg, type = 'info') => {
        const el = document.getElementById('log');
        const time = new Date().toLocaleTimeString();
        const icon = type === 'info' ? '📄' : type === 'success' ? '✅' : '⚠️';
        el.innerHTML += `<p>${icon} [${time}] ${msg}</p>`;
        el.scrollTop = el.scrollHeight;
    };

    const updateConnectionStatus = () => {
        const isConnected = window.Echo.connector.socket?.connected;
        const statusEl = document.getElementById('connection-status');

        if (isConnected) {
            statusEl.innerHTML = '🟢 Підключено до WebSocket';
            statusEl.style.color = 'green';
            log('Успішно підключено до сервера WebSocket', 'success');
        } else {
            statusEl.innerHTML = '🔴 Відключено';
            statusEl.style.color = 'red';
        }
    };

    const subscribeToEvents = () => {
        window.Echo.private(`project.${projectId}`)
            .listen('.task.created', (e) => {
                log(`📝 <strong>Нова задача:</strong> "${e.title}" (ID: ${e.task_id})`, 'success');
            })
            .listen('.task.updated', (e) => {
                const statusNames = {
                    'todo': '📋 До виконання',
                    'in_progress': '🔄 В процесі',
                    'done': '✅ Виконано',
                    'expired': '⏰ Прострочено'
                };
                log(`🔄 <strong>Задача оновлена:</strong> "${e.title}" з "${statusNames[e.old_status] || e.old_status}" на "${statusNames[e.new_status] || e.new_status}"`, 'info');
            })
            .listen('.comment.created', (e) => {
                log(`💬 <strong>Новий коментар:</strong> "${e.body}" (автор: ${e.author})`, 'success');
            });

        log(`Підписано на канал project.${projectId}`, 'success');
    };

    const createTestTask = async () => {
        try {
            const response = await fetch('/api/test/task', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    project_id: projectId,
                    title: 'Тестова задача ' + new Date().toLocaleTimeString(),
                    description: 'Створено через тестовий інтерфейс'
                })
            });

            const data = await response.json();
            taskId = data.task_id;
            log(`Тестова задача створена (ID: ${taskId})`, 'success');
        } catch (error) {
            log(`Помилка при створенні задачі: ${error.message}`, 'error');
        }
    };

    const updateTaskStatus = async () => {
        if (!taskId) {
            log('Спочатку створіть задачу', 'error');
            return;
        }

        try {
            const response = await fetch(`/api/test/task/${taskId}/status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    status: 'in_progress'
                })
            });

            const data = await response.json();
            log(`Статус задачі оновлено`, 'success');
        } catch (error) {
            log(`Помилка при оновленні статусу: ${error.message}`, 'error');
        }
    };

    const addTestComment = async () => {
        if (!taskId) {
            log('Спочатку створіть задачу', 'error');
            return;
        }

        try {
            const response = await fetch('/api/test/comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    task_id: taskId,
                    content: 'Тестовий коментар ' + new Date().toLocaleTimeString()
                })
            });

            const data = await response.json();
            log(`Тестовий коментар додано`, 'success');
        } catch (error) {
            log(`Помилка при додаванні коментаря: ${error.message}`, 'error');
        }
    };

    const clearLog = () => {
        document.getElementById('log').innerHTML = '';
    };

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            updateConnectionStatus();
            subscribeToEvents();

            window.Echo.connector.socket.on('connect', () => {
                updateConnectionStatus();
                subscribeToEvents();
            });

            window.Echo.connector.socket.on('disconnect', () => {
                updateConnectionStatus();
                log('Втрачено підключення до WebSocket', 'error');
            });
        }, 1000);

        log('Сторінка ініціалізована. Очікую події...', 'info');
    });
</script>
</body>
</html>
